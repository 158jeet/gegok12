const { test, expect } = require('@playwright/test');

function getOwner() {
  let accounts;
  try {
    accounts = JSON.parse(process.env.TAGORE_LIVE_ACCEPTANCE_ACCOUNTS_JSON || '{}');
  } catch {
    throw new Error('TAGORE_LIVE_ACCEPTANCE_ACCOUNTS_JSON must be valid JSON.');
  }
  if (!accounts.owner?.email || !accounts.owner?.password) {
    throw new Error('Secret must include owner.email and owner.password.');
  }
  return accounts.owner;
}

function assertHealthyBody(body, context) {
  expect(body, context + ': page body should not be empty').toBeTruthy();
  expect(body, context + ': visible server exception').not.toMatch(/server error|whoops|internal server error/i);
  expect(body, context + ': visible mojibake/replacement characters').not.toMatch(/(?:\u00C3|\u00C2|\u00E2\u20AC|\u00EF\u00BF\u00BD|\uFFFD)/);
}

test('public login page renders without horizontal overflow', async ({ page }, testInfo) => {
  const response = await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
  expect(response, 'login page should return a response').toBeTruthy();
  expect(response.status()).toBeLessThan(400);
  await expect(page.locator('#email')).toBeVisible();
  await expect(page.locator('#password')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Sign in to Tagore ERP' })).toBeVisible();
  assertHealthyBody(await page.locator('body').innerText(), 'login page');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow, 'login page must not horizontally overflow on ' + testInfo.project.name).toBeFalsy();
  await page.screenshot({ path: 'test-results/owner-smoke-login-' + testInfo.project.name + '.png', fullPage: true });
});

test('owner can sign in and read dashboard and visible navigation routes', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop', 'Owner authenticated route sweep runs on desktop; mobile project checks responsive login.');
  test.setTimeout(150000);
  let owner;
  try {
    owner = getOwner();
  } catch (error) {
    if (/Secret must include owner\.email and owner\.password/.test(error.message)) {
      test.skip(true, 'Authenticated owner smoke requires dedicated credentials; role-authenticated E2E is covered by the seeded test harness.');
    }
    throw error;
  }
  const consoleErrors = [];
  const serverErrors = [];
  page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
  page.on('response', response => { if (response.status() >= 500) serverErrors.push(response.status() + ' ' + response.url()); });

  const loginResponse = await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
  expect(loginResponse && loginResponse.status()).toBeLessThan(400);
  await page.locator('#email').fill(owner.email);
  await page.locator('#password').fill(owner.password);
  await page.getByRole('button', { name: 'Sign in to Tagore ERP' }).click();
  await expect(page).toHaveURL(/\/tagore\/dashboard(?:[/?#]|$)/, { timeout: 30000 });
  assertHealthyBody(await page.locator('body').innerText(), 'owner dashboard');
  expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2), 'owner dashboard horizontal overflow').toBeFalsy();
  const dashboardBrokenImages = await page.locator('img').evaluateAll(images => images.filter(img => img.complete && img.naturalWidth === 0).map(img => img.src));
  expect(dashboardBrokenImages, 'owner dashboard broken images').toEqual([]);
  await page.screenshot({ path: 'test-results/owner-smoke-dashboard.png', fullPage: true });

  const links = await page.locator('a[href]').evaluateAll(anchors => [...new Set(anchors.map(a => a.href).filter(href => {
    try {
      const url = new URL(href);
      return url.origin === location.origin && url.pathname.startsWith('/tagore/') && !/[{}]/.test(url.pathname);
    } catch { return false; }
  }))]);
  expect(links.length, 'owner dashboard should expose Tagore navigation links').toBeGreaterThan(0);

  const routeResults = [];
  for (const href of links.slice(0, 80)) {
    const response = await page.goto(href, { waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => null);
    const status = response ? response.status() : 0;
    const finalPath = new URL(page.url()).pathname;
    const body = await page.locator('body').innerText().catch(() => '');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2).catch(() => true);
    const brokenImages = await page.locator('img').evaluateAll(images => images.filter(img => img.complete && img.naturalWidth === 0).map(img => img.src)).catch(() => ['image check failed']);
    routeResults.push({ path: new URL(href).pathname, status, finalPath, overflow, brokenImages: brokenImages.length });
    expect(status, 'owner route HTTP response for ' + href).toBeGreaterThanOrEqual(200);
    expect(status, 'owner route must not return 401/403/404/5xx for ' + href).toBeLessThan(400);
    expect(finalPath === '/login' || finalPath.startsWith('/login/'), 'owner route unexpectedly redirected to login: ' + href).toBeFalsy();
    assertHealthyBody(body, 'owner route ' + href);
    expect(overflow, 'owner route horizontal overflow: ' + href).toBeFalsy();
    expect(brokenImages, 'owner route broken images: ' + href).toEqual([]);
  }
  expect(serverErrors, 'owner browser requests with 5xx responses').toEqual([]);
  expect(consoleErrors, 'owner browser console errors').toEqual([]);
  console.log(JSON.stringify({ environment: process.env.TAGORE_LIVE_ENVIRONMENT || 'unspecified', role: 'owner', dashboard: 'passed', renderedRoutesChecked: routeResults.length, routeResults }, null, 2));
});
