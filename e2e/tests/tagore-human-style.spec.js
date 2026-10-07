const { test, expect } = require('@playwright/test');

const roles = ['owner','principal','coordinator','teacher','parent','student','accounts','fee-editor'];
const safeButtonTypes = new Set(['button', 'reset']);

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
  const errors = [];
  const failedRequests = [];
  const consoleErrors = [];
  const notFoundResources = [];
  const onConsole = msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); };
  const onResponse = response => {
    if (response.status() >= 500) failedRequests.push(response.status() + ' ' + response.url());
    if (response.status() === 404) notFoundResources.push(response.url());
  };
  page.on('console', onConsole);
  page.on('response', onResponse);
  await expect(page.locator('body')).toBeVisible();
  const bodyText = await page.locator('body').innerText().catch(() => '');
  if (!bodyText.trim()) errors.push('empty body; final URL=' + page.url());
  if (/server error|exception|whoops/i.test(bodyText)) errors.push('server error text detected');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  if (overflow) errors.push('horizontal overflow');
  const brokenImages = await page.locator('img').evaluateAll(imgs => imgs.filter(i => !i.complete || i.naturalWidth === 0).map(i => i.src));
  if (brokenImages.length) errors.push('broken images: ' + brokenImages.slice(0, 5).join(', '));
  expect(errors, context + ' page health').toEqual([]);
  expect(failedRequests, context + ' 5xx responses').toEqual([]);
  expect(consoleErrors, context + ' console errors (404 resources: ' + notFoundResources.join(', ') + ')').toEqual([]);
  page.off('console', onConsole);
  page.off('response', onResponse);
}

test.describe('Tagore ERP human-style authenticated UI', () => {
  for (const role of roles) {
    test(role + ' — dashboard, visible navigation and reachable authenticated screens', async ({ page }) => {
      await authenticate(page, role);
      await checkPageHealth(page, role + ' dashboard');
      const initialLinks = unique(await collectVisibleTagoreLinks(page));
      expect(initialLinks.length, role + ' visible Tagore navigation links').toBeGreaterThan(0);
      const routeManifest = await page.request.get('/__e2e/routes').then(async response => {
        expect(response.ok()).toBeTruthy();
        return response.json();
      });
      expect(routeManifest.length, 'authenticated GET route manifest').toBeGreaterThan(0);

      for (const href of initialLinks.slice(0, 80)) {
        await page.goto('/tagore/dashboard', { waitUntil: 'domcontentloaded', timeout: 15000 });
        const target = new URL(href);
        const clicked = await page.locator('a[href="' + target.pathname + target.search + '"]:visible').first().click({ timeout: 3000 }).then(() => true).catch(() => false);
        if (clicked) {
          await page.waitForLoadState('domcontentloaded', { timeout: 10000 }).catch(() => {});
          const path = new URL(page.url()).pathname;
          if (!/\/login(?:$|\/)/.test(path)) await checkPageHealth(page, role + ' clicked ' + target.pathname);
        }
      }

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
        expect(status, role + ' ' + key + ' HTTP status').toBeLessThan(500);
        await checkPageHealth(page, role + ' ' + key);


        for (const next of unique(await collectVisibleTagoreLinks(page))) {
          const nextKey = new URL(next).pathname + new URL(next).search;
          if (!/[{}]/.test(new URL(next).pathname) && !visited.has(nextKey) && !queue.includes(next)) queue.push(next);
        }
      }
      expect(visited.size, role + ' authenticated screens visited').toBeGreaterThan(0);
    });
  }

  test('forbidden owner administration is blocked for every non-owner role', async ({ page }) => {
    for (const role of roles.filter(r => r !== 'owner')) {
      await authenticate(page, role);
      const response = await page.goto('/tagore/admin', { waitUntil: 'networkidle' });
      expect([401, 403]).toContain(response.status());
    }
  });
});
