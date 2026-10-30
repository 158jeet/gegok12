const { test, expect } = require('@playwright/test');

const enabled = process.env.TAGORE_LIVE_ACCEPTANCE_ENABLED === 'true';
const requiredRoles = ['owner', 'principal', 'coordinator', 'teacher', 'parent', 'student', 'accounts', 'fee-editor'];

function getAccounts() {
  if (!enabled) return {};
  try {
    return JSON.parse(process.env.TAGORE_LIVE_ACCEPTANCE_ACCOUNTS_JSON || '{}');
  } catch {
    throw new Error('TAGORE_LIVE_ACCEPTANCE_ACCOUNTS_JSON must be valid JSON.');
  }
}

function assertHealthyBody(body, context) {
  expect(body, context + ': page body should not be empty').toBeTruthy();
  expect(body, context + ': visible server exception').not.toMatch(/server error|whoops|internal server error/i);
  expect(body, context + ': visible mojibake/replacement characters').not.toMatch(/(?:\u00C3|\u00C2|\u00E2\u20AC|\u00EF\u00BF\u00BD|\uFFFD)/);
}

test.describe('Tagore live-site acceptance', () => {
  test.skip(!enabled, 'Runs only from the manual Tagore Live-Site Acceptance workflow.');

  test('public login page renders correctly on desktop and mobile', async ({ page }, testInfo) => {
    const response = await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
    expect(response, 'login page should return a response').toBeTruthy();
    expect(response.status(), 'login page HTTP status').toBeLessThan(400);
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Sign in to Tagore ERP' })).toBeVisible();
    assertHealthyBody(await page.locator('body').innerText(), 'login page');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(overflow, 'login page must not horizontally overflow ' + testInfo.project.name).toBeFalsy();
    const logo = page.locator('img.tg-login-logo');
    if (await logo.count()) {
      await expect(logo).toBeVisible();
      const imageLoaded = await logo.evaluate(img => img.complete && img.naturalWidth > 0);
      expect(imageLoaded, 'Tagore login logo must load').toBeTruthy();
    }
    await page.screenshot({ path: 'test-results/live-login-' + testInfo.project.name + '.png', fullPage: true });
  });

  for (const role of requiredRoles) {
    test(role + ': sign in, dashboard and render visible navigation pages', async ({ browser }, testInfo) => {
      test.skip(testInfo.project.name !== 'desktop', 'Authenticated route sweep runs once per role on desktop; mobile project checks responsive login.');
      test.setTimeout(150000);
      const accounts = getAccounts();
      test.skip(!accounts[role] || !accounts[role].email || !accounts[role].password,
        'Dedicated live acceptance account is not configured for role: ' + role);

      const context = await browser.newContext({
        baseURL: process.env.E2E_BASE_URL,
        viewport: { width: 1440, height: 900 },
        ignoreHTTPSErrors: false,
      });
      const page = await context.newPage();
      const consoleErrors = [];
      const serverErrors = [];
      page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
      page.on('response', response => { if (response.status() >= 500) serverErrors.push(response.status() + ' ' + response.url()); });

      try {
        const loginResponse = await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
        expect(loginResponse && loginResponse.status(), 'login page must be available').toBeLessThan(400);
        await page.locator('#email').fill(accounts[role].email);
        await page.locator('#password').fill(accounts[role].password);
        await page.getByRole('button', { name: 'Sign in to Tagore ERP' }).click();
        await expect(page).toHaveURL(/\/tagore\/dashboard(?:[/?#]|$)/, { timeout: 30000 });
        await expect(page.locator('body')).not.toContainText(/server error|whoops|internal server error/i);
        assertHealthyBody(await page.locator('body').innerText(), role + ' dashboard');

        const dashboardOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
        expect(dashboardOverflow, role + ' dashboard must not horizontally overflow').toBeFalsy();
        const brokenImages = await page.locator('img').evaluateAll(images => images.filter(img => img.complete && img.naturalWidth === 0).map(img => img.src));
        expect(brokenImages, role + ' dashboard broken images').toEqual([]);
        await page.screenshot({ path: 'test-results/live-' + role + '-dashboard.png', fullPage: true });

        const links = await page.locator('a[href]').evaluateAll(anchors => [...new Set(anchors
          .map(a => a.href)
          .filter(href => {
            try {
              const url = new URL(href);
              return url.origin === location.origin && url.pathname.startsWith('/tagore/') && !/[{}]/.test(url.pathname);
            } catch { return false; }
          }))]);
        expect(links.length, role + ' should expose at least one Tagore navigation link').toBeGreaterThan(0);

        const routeResults = [];
        for (const href of links.slice(0, 80)) {
          const response = await page.goto(href, { waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => null);
          const status = response ? response.status() : 0;
          const finalPath = new URL(page.url()).pathname;
          const body = await page.locator('body').innerText().catch(() => '');
          const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2).catch(() => true);
          const brokenImages = await page.locator('img').evaluateAll(images => images.filter(img => img.complete && img.naturalWidth === 0).map(img => img.src)).catch(() => ['image check failed']);
          const loginRedirect = finalPath === '/login' || finalPath.startsWith('/login/');
          routeResults.push({ path: new URL(href).pathname, status, finalPath, overflow, brokenImages: brokenImages.length });
          expect(status, role + ' route HTTP response for ' + href).toBeGreaterThanOrEqual(200);
          expect(status, role + ' route must not return 401/403/404/5xx for ' + href).toBeLessThan(400);
          expect(loginRedirect, role + ' visible route unexpectedly redirected to login: ' + href).toBeFalsy();
          assertHealthyBody(body, role + ' route ' + href);
          expect(overflow, role + ' route horizontally overflows: ' + href).toBeFalsy();
          expect(brokenImages, role + ' route has broken images: ' + href).toEqual([]);
        }
        expect(serverErrors, role + ' browser requests with 5xx responses').toEqual([]);
        expect(consoleErrors, role + ' browser console errors').toEqual([]);
        console.log(JSON.stringify({ environment: process.env.TAGORE_LIVE_ENVIRONMENT || 'unspecified', role, dashboard: 'passed', renderedRoutesChecked: routeResults.length, routeResults }, null, 2));
      } finally {
        await context.close();
      }
    });
  }
});
