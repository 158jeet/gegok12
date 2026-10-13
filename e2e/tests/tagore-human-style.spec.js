const { test, expect } = require('@playwright/test');

const roles = ['owner','principal','coordinator','teacher','parent','student','accounts','fee-editor'];

function unique(items) { return [...new Set(items)]; }

async function authenticate(page, role) {
  await page.goto('/__e2e/session/' + role, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await expect(page).toHaveURL(/\/tagore\/dashboard/);
}

async function collectVisibleTagoreLinks(page) {
  return await page.locator('a[href]').evaluateAll(anchors => anchors
    .map(a => ({ href: a.href, text: (a.textContent || '').trim() }))
    .filter(x => x.href && new URL(x.href).pathname.startsWith('/tagore/'))
    .map(x => x.href));
}

async function checkPageHealth(page, context) {
  const findings = [];
  const failedRequests = [];
  const notFoundResources = [];
  const consoleErrors = [];
  const onConsole = msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); };
  const onResponse = response => {
    if (response.status() >= 500) failedRequests.push(response.status() + ' ' + response.url());
    if (response.status() === 404) notFoundResources.push(response.url());
  };
  page.on('console', onConsole);
  page.on('response', onResponse);
  const bodyVisible = await page.locator('body').isVisible().catch(() => false);
  if (!bodyVisible) findings.push('body not visible; final URL=' + page.url());
  const bodyText = await page.locator('body').innerText().catch(() => '');
  if (!bodyText.trim()) findings.push('empty body; final URL=' + page.url());
  if (/server error|exception|whoops/i.test(bodyText)) findings.push('server error text detected');
  if (/(?:\u00C3|\u00C2|\u00E2\u20AC|\u00EF\u00BF\u00BD|\uFFFD)/.test(bodyText)) findings.push('mojibake or replacement characters detected in visible text');
  const overflowDetails = await page.evaluate(() => {
    const viewport = window.innerWidth;
    const offenders = [...document.querySelectorAll('body *')].map(el => {
      const r = el.getBoundingClientRect();
      let p = el.parentElement;
      let insideHorizontalScroller = false;
      while (p && p !== document.body) {
        const ox = getComputedStyle(p).overflowX;
        if (ox === 'auto' || ox === 'scroll') { insideHorizontalScroller = true; break; }
        p = p.parentElement;
      }
      return { el, tag: el.tagName.toLowerCase(), cls: (el.className && typeof el.className === 'string') ? el.className.slice(0,120) : '', left: Math.round(r.left), right: Math.round(r.right), width: Math.round(r.width), insideHorizontalScroller };
    }).filter(x => x.width > 0 && !x.insideHorizontalScroller && (x.right > viewport + 2 || x.left < -2))
      .sort((a,b) => Math.max(b.right-viewport, -b.left) - Math.max(a.right-viewport, -a.left))
      .slice(0,5)
      .map(({el,...x}) => x);
    return { overflow: offenders.length > 0, offenders };
  });
  if (overflowDetails.overflow) findings.push('horizontal overflow: ' + JSON.stringify(overflowDetails.offenders));
  const brokenImages = await page.locator('img').evaluateAll(imgs => imgs.filter(i => !i.complete || i.naturalWidth === 0).map(i => i.src));
  if (brokenImages.length) findings.push('broken images: ' + brokenImages.slice(0, 5).join(', '));
  await page.waitForTimeout(100);
  if (failedRequests.length) findings.push('5xx responses: ' + failedRequests.slice(0, 10).join(', '));
  if (notFoundResources.length) findings.push('404 responses/resources: ' + [...new Set(notFoundResources)].slice(0, 10).join(', '));
  if (consoleErrors.length) findings.push('console errors: ' + consoleErrors.slice(0, 10).join(' | '));
  page.off('console', onConsole);
  page.off('response', onResponse);
  return findings.map(finding => context + ': ' + finding);
}

test.describe('Tagore ERP human-style authenticated UI', () => {
  for (const role of roles) {
    test(role + ' — dashboard, visible navigation and reachable authenticated screens', async ({ page }) => {
      const findings = [];
      await authenticate(page, role);
      findings.push(...await checkPageHealth(page, role + ' dashboard'));
      const initialLinks = unique(await collectVisibleTagoreLinks(page));
      expect(initialLinks.length, role + ' visible Tagore navigation links').toBeGreaterThan(0);
      const routeManifest = await page.request.get('/__e2e/routes').then(async response => {
        expect(response.ok()).toBeTruthy();
        return response.json();
      });
      expect(routeManifest.length, 'authenticated GET route manifest').toBeGreaterThan(0);

      if (role === 'owner') {
        const visited = new Set();
        const queue = unique([
          ...initialLinks.filter(h => !/[{}]/.test(new URL(h).pathname)),
          ...routeManifest.map(r => new URL(r.uri, page.url()).href),
        ]);
        while (queue.length && visited.size < 1000) {
          const href = queue.shift();
          const url = new URL(href, page.url());
          const key = url.pathname + url.search;
          if (visited.has(key)) continue;
          visited.add(key);
          const response = await page.goto(url.href, { waitUntil: 'domcontentloaded', timeout: 15000 });
          const status = response ? response.status() : 0;
          if ([401, 403].includes(status) || /\/login(?:\?|$)/.test(new URL(page.url()).pathname)) continue;
          if (status >= 500 || status === 0) findings.push(role + ' ' + key + ' HTTP status ' + status);
          findings.push(...await checkPageHealth(page, role + ' ' + key));
        }
        expect(visited.size, role + ' authenticated screens visited').toBeGreaterThan(0);
      }
      expect(findings, role + ' aggregate UI health findings').toEqual([]);
    });
  }

  test('Olympiad registration is mobile-first and marketing consent stays optional', async ({ page }) => {
    await page.goto('/olympiad/register', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Join the Tagore Olympiad' })).toBeVisible();
    await expect(page.getByLabel('Student name *')).toBeVisible();
    await expect(page.getByLabel('Parent / guardian mobile *')).toBeVisible();
    await expect(page.getByRole('checkbox')).not.toBeChecked();
    const desktopOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(desktopOverflow, 'Olympiad registration must not overflow desktop viewport').toBeFalsy();
    await page.screenshot({ path: 'test-results/olympiad-registration-desktop.png', fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Join the Tagore Olympiad' })).toBeVisible();
    const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(mobileOverflow, 'Olympiad registration must not overflow mobile viewport').toBeFalsy();
    await page.screenshot({ path: 'test-results/olympiad-registration-mobile.png', fullPage: true });
  });
  test('platform growth and quality workspace is readable and responsive', async ({ page }) => {
    const findings = [];
    await authenticate(page, 'owner');
    await page.goto('/tagore/platform', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Growth & quality workspace' })).toBeVisible();
    await expect(page.getByRole('link', { name: /Social post drafts/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Creative templates/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Olympiad lead follow-up/i })).toBeVisible();
    await expect(page.locator('#quality-gates')).toBeVisible();
    findings.push(...await checkPageHealth(page, 'platform growth workspace'));
    const desktopOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(desktopOverflow, 'platform page must not overflow desktop viewport').toBeFalsy();
    await page.screenshot({ path: 'test-results/platform-growth-desktop.png', fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Growth & quality workspace' })).toBeVisible();
    const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(mobileOverflow, 'platform page must not overflow mobile viewport').toBeFalsy();
    await page.screenshot({ path: 'test-results/platform-growth-mobile.png', fullPage: true });
    expect(findings, 'platform page integrity findings').toEqual([]);
  });
  test('task workspace is readable and responsive on desktop and mobile', async ({ page }) => {
    await authenticate(page, 'owner');
    await page.goto('/tagore/tasks', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Team tasks' })).toBeVisible();
    await expect(page.locator('.task-stats > .card')).toHaveCount(4);
    await expect(page.locator('#assign-work form[action*="/tagore/tasks"]')).toBeVisible();
    await expect(page.locator('#task-list')).toBeVisible();
    await expect(page.getByRole('button', { name: /apply filters/i })).toBeVisible();
    const desktopOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(desktopOverflow, 'task page must not overflow the desktop viewport').toBeFalsy();
    await page.screenshot({ path: 'test-results/task-workspace-desktop.png', fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Team tasks' })).toBeVisible();
    const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(mobileOverflow, 'task page must not overflow the mobile viewport').toBeFalsy();
    await page.screenshot({ path: 'test-results/task-workspace-mobile.png', fullPage: true });
  });
  test('forbidden owner administration is blocked for every non-owner role', async ({ page }) => {
    for (const role of roles.filter(r => r !== 'owner')) {
      await authenticate(page, role);
      const response = await page.goto('/tagore/admin', { waitUntil: 'networkidle' });
      expect([401, 403]).toContain(response.status());
    }
  });
});
