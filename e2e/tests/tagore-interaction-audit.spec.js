const { test, expect } = require('@playwright/test');

const roles = ['owner', 'principal', 'coordinator', 'teacher', 'parent', 'student', 'accounts', 'fee-editor'];
const baseDir = 'test-results/interaction-audit';

async function signInFixture(page, role) {
  await page.goto('/__e2e/session/' + role, { waitUntil: 'domcontentloaded', timeout: 20000 });
  await expect(page).toHaveURL(/\/tagore\/dashboard/);
}

function slug(value) {
  return String(value || 'unnamed').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60) || 'unnamed';
}

async function inspectPage(page, role, index, findings) {
  const pathname = new URL(page.url()).pathname;
  const name = role + '-' + String(index).padStart(4, '0') + '-' + slug(pathname);
  const body = await page.locator('body').innerText().catch(() => '');
  if (!body.trim()) findings.push({ role, pathname, type: 'empty-page' });
  if (/server error|whoops|internal server error/i.test(body)) findings.push({ role, pathname, type: 'server-error-visible' });
  const geometry = await page.evaluate(() => ({
    viewportWidth: innerWidth,
    documentWidth: document.documentElement.scrollWidth,
    title: document.title,
    visibleControls: [...document.querySelectorAll('button, input, select, textarea, [role="button"], a[href]')]
      .filter(el => {
        const r = el.getBoundingClientRect();
        const s = getComputedStyle(el);
        return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none';
      }).map((el, index) => ({
        index,
        tag: el.tagName.toLowerCase(),
        type: (el.getAttribute('type') || '').toLowerCase(),
        role: el.getAttribute('role') || '',
        text: (el.innerText || el.getAttribute('aria-label') || el.getAttribute('title') || el.getAttribute('placeholder') || '').trim().slice(0, 100),
        disabled: !!el.disabled,
        href: el.tagName.toLowerCase() === 'a' ? el.getAttribute('href') : null,
        name: el.getAttribute('name') || '',
        id: el.id || '',
        form: !!el.closest('form')
      }))
  }));
  if (geometry.documentWidth > geometry.viewportWidth + 2) {
    findings.push({ role, pathname, type: 'horizontal-overflow', viewport: geometry.viewportWidth, document: geometry.documentWidth });
  }
  await page.screenshot({ path: baseDir + '/' + name + '.png', fullPage: true, animations: 'disabled' });
  return { role, pathname, title: geometry.title, controls: geometry.visibleControls };
}

test.describe('Full page-by-page interaction inventory (disposable CI data)', () => {
  for (const role of roles) {
    test(role + ' — screenshot every concrete authenticated HTML route and inventory every visible control', async ({ page }, testInfo) => {
      test.setTimeout(15 * 60 * 1000);
      await require('fs').promises.mkdir(baseDir, { recursive: true });
      const findings = [];
      const pageInventory = [];
      const visited = new Set();
      const consoleErrors = [];
      const failedResponses = [];
      const expectedAccessDenials = [];
      const expectedUnavailableIntegrations = [];
      page.on('console', msg => {
        if (msg.type() !== 'error') return;
        const message = msg.text();
        // Chromium emits generic console errors for failed HTTP resources; retain genuine JS/runtime errors here.
        // HTTP failures are assessed separately from response events below.
        if (/Failed to load resource: the server responded with a status of (?:403|404|503) \((?:Forbidden|Not Found|Service Unavailable)\)/i.test(message)) return;
        consoleErrors.push(message);
      });
      page.on('response', response => {
        const pathname = new URL(response.url()).pathname;
        // CCTV is an optional integration. In the disposable test environment its documented
        // unconfigured state is HTTP 503; every other 5xx remains a hard failure.
        if (pathname === '/tagore/platform/cctv/status' && response.status() === 503) {
          expectedUnavailableIntegrations.push({ status: response.status(), url: response.url() });
          return;
        }
        if (response.status() >= 500) failedResponses.push({ status: response.status(), url: response.url() });
      });

      await signInFixture(page, role);
      const manifestResponse = await page.request.get('/__e2e/routes', { timeout: 20000 });
      expect(manifestResponse.ok(), 'authenticated route manifest must load').toBeTruthy();
      const routes = await manifestResponse.json();
      const paths = [...new Set([
        '/tagore/dashboard',
        ...routes.map(route => new URL(route.uri, page.url()).pathname + new URL(route.uri, page.url()).search)
      ])].filter(path => path.startsWith('/tagore/') && !/[{}]/.test(path));

      for (const path of paths) {
        if (visited.has(path)) continue;
        visited.add(path);
        const response = await page.goto(path, { waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => null);
        if (!response) {
          findings.push({ role, path, type: 'navigation-failed' });
          continue;
        }
        if ([401, 403].includes(response.status())) {
          // The route manifest intentionally lists routes across roles. A 401/403 on a route
          // the current role is not allowed to use is expected and is tracked separately.
          expectedAccessDenials.push({ path, status: response.status() });
          continue;
        }
        if (response.status() === 404) {
          findings.push({ role, path, type: 'route-not-found', status: response.status() });
          continue;
        }
        const expectedCctvUnavailable = path === '/tagore/platform/cctv/status' && response.status() === 503;
        if (response.status() >= 500 && !expectedCctvUnavailable) {
          findings.push({ role, path, type: 'unexpected-http-status', status: response.status() });
          continue;
        }
        const record = await inspectPage(page, role, pageInventory.length, findings);
        pageInventory.push(record);

        // Probe only safe, non-submitting controls. Destructive actions, payment/fee/attendance
        // submissions, delete buttons and form submits are never fired by this inventory pass;
        // those require dedicated workflow assertions with known synthetic fixtures.
        const safeIndexes = record.controls.filter(control => {
          const text = (control.text + ' ' + control.name + ' ' + control.id).toLowerCase();
          const isLink = control.tag === 'a' && !!control.href;
          const isNonSubmitButton = ['button', '[role="button"]'].includes(control.tag) &&
            control.type !== 'submit' && control.type !== 'reset' && !control.form;
          const destructive = /delete|remove|destroy|pay|payment|fee|reconcile|submit|save|create|update|approve|reject|admit|promote|attendance|logout|sign out/i.test(text);
          return !control.disabled && !destructive && (isLink || isNonSubmitButton);
        });

        // Record controls on every page; exercise safe links and standalone buttons in isolated
        // navigations, then return to the route. This avoids sharing mutated UI state across probes.
        for (const control of safeIndexes) {
          if (control.tag === 'a' && control.href) {
            const target = new URL(control.href, page.url());
            if (target.origin !== new URL(page.url()).origin || !target.pathname.startsWith('/tagore/')) continue;
            const probe = await page.request.get(target.href, { timeout: 10000 }).catch(() => null);
            const expectedCctvUnavailable = target.pathname === '/tagore/platform/cctv/status' && probe?.status() === 503;
            if (!probe || ([401, 403, 404].includes(probe.status())) || (probe.status() >= 500 && !expectedCctvUnavailable)) {
              findings.push({ role, path, type: 'link-target-failed', control: control.text, target: target.pathname, status: probe?.status() || 0 });
            }
          } else {
            const locator = page.locator('button:not([type="submit"]):not([type="reset"]), [role="button"]').filter({ hasText: control.text || '__no_text__' }).first();
            // No click without a stable, non-empty accessible label; click probes are best-effort
            // for standalone safe buttons only, and page state is restored on the next route iteration.
            if (control.text && await locator.count().catch(() => 0)) {
              const clickError = await locator.click({ timeout: 1500, noWaitAfter: true }).then(() => null).catch(error => error.message);
              if (clickError) findings.push({ role, path, type: 'safe-button-click-failed', control: control.text, error: clickError.slice(0, 180) });
              await page.goto(path, { waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => null);
            }
          }
        }
      }

      const report = { role, viewport: testInfo.project.name, routesVisited: visited.size, htmlPagesScreenshotted: pageInventory.length, pages: pageInventory, expectedAccessDenials, expectedUnavailableIntegrations, findings, consoleErrors, failedResponses };
      await require('fs').promises.mkdir(baseDir, { recursive: true });
      await require('fs').promises.writeFile(baseDir + '/' + role + '-' + testInfo.project.name + '-inventory.json', JSON.stringify(report, null, 2));
      expect(failedResponses, role + ' 5xx responses during route sweep').toEqual([]);
      expect(consoleErrors, role + ' browser console errors during route sweep').toEqual([]);
      expect(findings, role + ' page/control interaction findings').toEqual([]);
      expect(pageInventory.length, role + ' should render at least one HTML page').toBeGreaterThan(0);
    });
  }
});
