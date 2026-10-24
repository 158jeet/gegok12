const { test, expect } = require('@playwright/test');

const roles = ['owner','principal','coordinator','teacher','parent','student','accounts','fee-editor'];

function unique(items) { return [...new Set(items)]; }

async function authenticate(page, role) {
  await page.goto('/__e2e/session/' + role, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await expect(page).toHaveURL(/\/tagore\/dashboard/);
}

async function collectVisibleTagoreLinks(page) {
  return await page.locator('a[href]').evaluateAll(anchors => anchors
    .filter(a => a.getClientRects().length > 0)
    .map(a => ({ href: a.getAttribute('href'), text: (a.textContent || '').trim() }))
    .filter(x => x.href && new URL(x.href, location.href).pathname.startsWith('/tagore/'))
    .map(x => x.href));
}

async function checkPageHealth(page, context) {
  const errors = [];
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
  await expect(page.locator('body')).toBeVisible();
  const bodyText = await page.locator('body').innerText().catch(() => '');
  if (!bodyText.trim()) errors.push('empty body; final URL=' + page.url());
  if (/server error|exception|whoops/i.test(bodyText)) errors.push('server error text detected');
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
      .slice(0,5).map(({el,...x}) => x);
    return { overflow: offenders.length > 0, offenders };
  });
  if (overflowDetails.overflow) errors.push('horizontal overflow: ' + JSON.stringify(overflowDetails.offenders));
  const brokenImages = await page.locator('img').evaluateAll(imgs => imgs.filter(i => !i.complete || i.naturalWidth === 0).map(i => i.src));
  if (brokenImages.length) errors.push('broken images: ' + brokenImages.slice(0, 5).join(', '));
  await page.waitForTimeout(100);
  expect(errors, context + ' page health').toEqual([]);
  expect(failedRequests, context + ' 5xx responses').toEqual([]);
  expect(consoleErrors, context + ' console errors; 404 resources: ' + unique(notFoundResources).join(', ')).toEqual([]);
  page.off('console', onConsole);
  page.off('response', onResponse);
}

test.describe('Tagore ERP human-style authenticated UI', () => {
  for (const role of roles) {
    test(role + ' — click visible navigation and check desktop/mobile layout', async ({ page }) => {
      await authenticate(page, role);
      await checkPageHealth(page, role + ' desktop dashboard');

      const links = unique(await collectVisibleTagoreLinks(page));
      expect(links.length, role + ' visible Tagore navigation links').toBeGreaterThan(0);

      // Exercise real visible navigation links for every role, not just the owner.
      for (const href of links.slice(0, 20)) {
        await authenticate(page, role);
        const clicked = await page.locator('a[href]').evaluateAll((anchors, target) => {
          const a = anchors.find(node => node.getAttribute('href') === target && node.getClientRects().length > 0);
          if (!a) return false;
          a.click();
          return true;
        }, href);
        expect(clicked, role + ' visible navigation link ' + href).toBeTruthy();
        await page.waitForLoadState('domcontentloaded').catch(() => {});
        await expect(page.locator('body')).toBeVisible();
        const path = new URL(page.url()).pathname;
        expect(path.startsWith('/tagore/'), role + ' navigation destination ' + href).toBeTruthy();
        await checkPageHealth(page, role + ' navigation ' + href);
      }

      // Check responsive layout at a phone-sized viewport for each role.
      await page.setViewportSize({ width: 390, height: 844 });
      await authenticate(page, role);
      await checkPageHealth(page, role + ' mobile dashboard');
      const dims = await page.evaluate(() => ({ viewport: document.documentElement.clientWidth, content: document.documentElement.scrollWidth }));
      expect(dims.content, role + ' mobile horizontal overflow').toBeLessThanOrEqual(dims.viewport + 2);

      // The route manifest is a second read-only coverage signal; only owner crawls
      // every parameter-free route because other roles intentionally lack access.
      const routeManifest = await page.request.get('/__e2e/routes').then(async response => {
        expect(response.ok()).toBeTruthy();
        return response.json();
      });
      expect(routeManifest.length, 'authenticated GET route manifest').toBeGreaterThan(0);
      if (role === 'owner') {
        const visited = new Set();
        const queue = unique([
          ...links.map(h => new URL(h, page.url()).href),
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
        }
        expect(visited.size, role + ' authenticated screens visited').toBeGreaterThan(0);
      }
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