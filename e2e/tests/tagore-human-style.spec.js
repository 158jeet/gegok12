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
    const oxScrollable = value => value === 'auto' || value === 'scroll';
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
      const ownOverflowX = getComputedStyle(el).overflowX;
      const elementIsHorizontalScroller = oxScrollable(ownOverflowX);
      return { el, tag: el.tagName.toLowerCase(), cls: (el.className && typeof el.className === 'string') ? el.className.slice(0,120) : '', left: Math.round(r.left), right: Math.round(r.right), width: Math.round(r.width), insideHorizontalScroller, elementIsHorizontalScroller };
    }).filter(x => x.width > 0 && !x.insideHorizontalScroller && !x.elementIsHorizontalScroller && (x.right > viewport + 2 || x.left < -2))
      .sort((a,b) => Math.max(b.right-viewport, -b.left) - Math.max(a.right-viewport, -a.left))
      .slice(0,5)
      .map(({el,...x}) => x);
    return { overflow: offenders.length > 0, offenders };
  });
  if (overflowDetails.overflow) findings.push('horizontal overflow: ' + JSON.stringify(overflowDetails.offenders));
  const brokenImages = await page.locator('img').evaluateAll(imgs => imgs.filter(i => i.complete && i.naturalWidth === 0).map(i => i.src));
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

      {
        // Smoke every concrete route through the authenticated request context; reserve full DOM/overflow/image checks for a representative set of rendered screens so the suite stays inside CI's per-test timeout.
        const visited = new Set();
        const visuallyChecked = new Set();
        const maxPages = role === 'owner' ? 1000 : 100;
        const concreteRoutes = routeManifest.filter(r => !/[{}]/.test(new URL(r.uri, page.url()).pathname));
        const queue = unique([
          ...initialLinks.filter(h => !/[{}]/.test(new URL(h).pathname)),
          ...concreteRoutes.slice(0, maxPages).map(r => new URL(r.uri, page.url()).href),
        ]);
        while (queue.length && visited.size < maxPages) {
          const batch = [];
          while (queue.length && batch.length < 20 && visited.size + batch.length < maxPages) {
            const href = queue.shift();
            const url = new URL(href, page.url());
            if (url.origin !== new URL(page.url()).origin || /[{}]/.test(url.pathname)) continue;
            const key = url.pathname + url.search;
            if (visited.has(key)) continue;
            visited.add(key);
            batch.push({ url, key });
          }
          const results = await Promise.all(batch.map(async item => {
            const response = await page.request.get(item.url.href, { timeout: 10000 }).catch(() => null);
            return { ...item, response, status: response ? response.status() : 0, contentType: response?.headers()['content-type'] || '' };
          }));
          for (const item of results) {
            if ([401, 403].includes(item.status)) continue;
            const expectedUnconfiguredCctv = item.url.pathname === '/tagore/platform/cctv/status' && item.status === 503;
            if (!expectedUnconfiguredCctv && (item.status >= 500 || item.status === 0 || item.status === 404)) findings.push(role + ' ' + item.key + ' HTTP status ' + item.status);
            if (item.response && item.status < 400 && item.contentType.includes('text/html') && visuallyChecked.size < 15) {
              await page.goto(item.url.href, { waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null);
              findings.push(...await checkPageHealth(page, role + ' ' + item.key));
              visuallyChecked.add(item.key);
            }
          }
        }
        expect(visited.size, role + ' authenticated routes checked').toBeGreaterThan(0);
      }
      expect(findings, role + ' aggregate UI health findings').toEqual([]);
    });
  }

  test('accounts dashboard supports responsive family and payment search without exposing transaction history to parents', async ({ page }) => {
    await authenticate(page, 'accounts');
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/tagore/accounts/fees', { waitUntil: 'load' });
    await expect(page.getByRole('heading', { name: 'Accounts & fee collection' })).toBeVisible();
    await expect(page.locator('#family-table')).toBeVisible();
    await expect(page.locator('#payments-table')).toBeVisible();
    await expect(page.getByRole('link', { name: /Manage fee structures/i })).toBeVisible();

    const globalSearch = page.getByRole('searchbox', { name: 'Search fee accounts and transactions' });
    await globalSearch.fill('no-match-tagore-acceptance');
    await expect(page.locator('.tg-accounts-table tbody tr:visible')).toHaveCount(0);
    await globalSearch.fill('');
    await expect(page.locator('.tg-accounts-table tbody tr:visible').first()).toBeVisible();

    const familySearch = page.getByRole('searchbox', { name: 'Filter family fee balances' });
    await familySearch.fill('no-match-family-acceptance');
    await expect(page.locator('#family-table tbody tr:visible')).toHaveCount(0);
    await familySearch.fill('');
    await expect(page.locator('#family-table tbody tr:visible').first()).toBeVisible();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'load' });
    const viewportAudit = await page.evaluate(() => ({
      viewport: document.documentElement.clientWidth,
      document: document.documentElement.scrollWidth,
      tableScroller: !!document.querySelector('.tg-table-wrap') &&
        getComputedStyle(document.querySelector('.tg-table-wrap')).overflowX === 'auto',
    }));
    expect(viewportAudit.document).toBeLessThanOrEqual(viewportAudit.viewport + 2);
    expect(viewportAudit.tableScroller).toBeTruthy();

    await authenticate(page, 'parent');
    const denied = await page.goto('/tagore/accounts/fees', { waitUntil: 'domcontentloaded' });
    expect(denied.status()).toBe(403);
  });

  test('academic-year archive controls are owner-only in the fee editor', async ({ page }) => {
    await authenticate(page, 'fee-editor');
    await page.goto('/tagore/accounts/fees/editor', { waitUntil: 'load' });
    await expect(page.getByRole('heading', { name: 'Close an academic year' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Archive original records' })).toHaveCount(0);

    await authenticate(page, 'owner');
    await page.goto('/tagore/accounts/fees/editor', { waitUntil: 'load' });
    await expect(page.getByRole('heading', { name: 'Close an academic year' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Archive original records' })).toBeVisible();
  });

  test('fee editor form contrast and navigation spacing remain readable', async ({ page }) => {
    await authenticate(page, 'fee-editor');
    await page.goto('/tagore/accounts/fees/editor', { waitUntil: 'load' });
    await expect(page.getByRole('heading', { name: 'Fee Editor' })).toBeVisible();
    await expect.poll(() => page.locator('.tg-nav').evaluate((nav) => getComputedStyle(nav).display)).toBe('flex');

    const styles = await page.evaluate(() => {
      const nav = document.querySelector('.tg-nav');
      const label = document.querySelector('.tg-field label');
      const input = document.querySelector('input[name="academic_year_id"]');
      const user = document.querySelector('.tg-user');
      return {
        navDisplay: getComputedStyle(nav).display,
        navGap: parseFloat(getComputedStyle(nav).columnGap),
        labelColor: getComputedStyle(label).color,
        inputBackground: getComputedStyle(input).backgroundColor,
        inputColor: getComputedStyle(input).color,
        userBackground: getComputedStyle(user).backgroundColor,
        userColor: getComputedStyle(user).color,
      };
    });
    expect(styles.navDisplay).toBe('flex');
    expect(styles.navGap).toBeGreaterThanOrEqual(8);
    expect(styles.labelColor).toBe('rgb(213, 222, 234)');
    expect(styles.inputBackground).toBe('rgb(17, 26, 36)');
    expect(styles.inputColor).toBe('rgb(237, 242, 247)');
    expect(styles.userBackground).toBe('rgb(26, 34, 45)');
    expect(styles.userColor).toBe('rgb(237, 242, 247)');
  });

  test('fee editor has a visible authorized entry point while coordinator fee management stays hidden', async ({ page }) => {
    await authenticate(page, 'fee-editor');
    await expect(page.locator('a[href$="/tagore/accounts/fees/editor"]:visible').first()).toBeVisible();

    await authenticate(page, 'coordinator');
    await expect(page.locator('a[href$="/tagore/accounts/fees/manage"]:visible')).toHaveCount(0);
    await expect(page.locator('a[href$="/tagore/accounts/fees/editor"]:visible')).toHaveCount(0);
    await expect(page.locator('a[href$="/tagore/accounts/fees"]:visible').first()).toBeVisible();
  });

  for (const role of roles) {
    test(role + ' — dashboard search filters visible items and restores them when cleared', async ({ page }) => {
      await authenticate(page, role);
      const search = page.getByRole('searchbox', { name: 'Search dashboard modules and visible items' });
      await expect(search).toBeVisible();

      if (role === 'student') {
        const overview = page.locator('.tg-side-link.active').filter({ hasText: 'Overview' });
        await expect(overview).toBeVisible();
        await search.fill('Overview');
        await expect(overview).toBeVisible();
        await search.fill('tagore-no-matching-dashboard-item');
        await expect(page.locator('.tg-side-link:visible, .tg-kpi:visible, .tg-module:visible, .tg-list-item:visible')).toHaveCount(0);
        await expect(page.locator('#tg-search-status')).toContainText('No matching modules or items');
        await search.press('Escape');
        await expect(search).toHaveValue('');
        await expect(page.locator('#tg-search-status')).toBeHidden();
        await expect(overview).toBeVisible();
      } else {
        const primaryLabel = role === 'parent' ? 'My children' : 'Students';
        const secondaryLabel = role === 'parent' ? 'Fee items due' : 'Fee outstanding';
        const primary = page.locator('.tg-kpi').filter({ hasText: primaryLabel }).first();
        const secondary = page.locator('.tg-kpi').filter({ hasText: secondaryLabel }).first();
        await expect(primary).toBeVisible();
        await expect(secondary).toBeVisible();
        await search.fill(primaryLabel);
        await expect(primary).toBeVisible();
        await expect(secondary).toBeHidden();
        await search.fill('tagore-no-matching-dashboard-item');
        await expect(page.locator('.tg-kpi:visible, .tg-module:visible, .tg-list-item:visible')).toHaveCount(0);
        await expect(page.locator('#tg-search-status')).toContainText('No matching modules or items');
        await search.press('Escape');
        await expect(search).toHaveValue('');
        await expect(page.locator('#tg-search-status')).toBeHidden();
        await expect(primary).toBeVisible();
        await expect(secondary).toBeVisible();
      }

      await page.keyboard.press('Control+/');
      await expect(search).toBeFocused();
    });
  }

  test('Olympiad registration is mobile-first and marketing consent stays optional', async ({ page }, testInfo) => {
    await page.goto('/olympiad/register', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Join the Tagore Olympiad' })).toBeVisible();
    await expect(page.getByLabel('Student name *')).toBeVisible();
    await expect(page.getByLabel('Parent / guardian mobile *')).toBeVisible();
    await expect(page.getByRole('checkbox')).not.toBeChecked();
    const consentBox = await page.getByRole('checkbox').boundingBox();
    expect(consentBox).toBeTruthy();
    expect(consentBox.width, 'marketing consent checkbox should remain compact').toBeLessThanOrEqual(24);
    expect(consentBox.height, 'marketing consent checkbox should remain compact').toBeLessThanOrEqual(24);
    const desktopOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(desktopOverflow, 'Olympiad registration must not overflow desktop viewport').toBeFalsy();
    if (testInfo.project.name === 'desktop') await page.screenshot({ path: 'test-results/olympiad-registration-desktop.png', fullPage: true });

    if (testInfo.project.name === 'desktop') await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Join the Tagore Olympiad' })).toBeVisible();
    const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(mobileOverflow, 'Olympiad registration must not overflow mobile viewport').toBeFalsy();
    await page.screenshot({ path: 'test-results/olympiad-registration-mobile.png', fullPage: true });
  });
  test('Olympiad registration saves a reference and returns a usable confirmation', async ({ page }, testInfo) => {
    const studentName = 'Playwright Olympiad ' + testInfo.project.name + ' ' + Date.now();
    await page.goto('/olympiad/register?utm_source=playwright&utm_campaign=e2e-smoke', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Student name *').fill(studentName);
    await page.getByLabel('Class *').selectOption('8');
    await page.getByLabel('Parent / guardian name *').fill('Playwright Guardian');
    await page.getByLabel('Parent / guardian mobile *').fill('9876501234');
    await page.getByLabel('Preferred language *').selectOption('hindi');
    await expect(page.getByRole('checkbox')).not.toBeChecked();
    const registrationResponse = page.waitForResponse(response =>
      response.request().method() === 'POST' && new URL(response.url()).pathname === '/olympiad/register'
    );
    await page.getByRole('button', { name: 'Create registration' }).click();
    const posted = await registrationResponse;
    const successHeading = page.getByRole('heading', { name: "You're registered" });
    try {
      await expect(successHeading).toBeVisible({ timeout: 15000 });
    } catch (error) {
      const body = (await page.locator('body').innerText().catch(() => '')).slice(0, 1800);
      throw new Error(`Olympiad registration did not reach confirmation (POST ${posted.status()}, URL ${page.url()}). Page text: ${body}. Original: ${error.message}`);
    }
    const reference = (await page.getByTestId('candidate-reference').innerText()).trim();
    expect(reference).toMatch(/^OLY-[A-Z0-9]{10}$/);
    await expect(page.getByText(/public exam link is not enabled yet/i)).toBeVisible();
  });
  test('platform growth and quality workspace is readable and responsive', async ({ page }, testInfo) => {
    const findings = [];
    await authenticate(page, 'owner');
    await page.goto('/tagore/platform', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Growth & quality workspace' })).toBeVisible();
    await expect(page.getByRole('link', { name: /Social post drafts/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Creative templates/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Open Olympiad leads/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Open Olympiad leads/i })).toHaveAttribute('href', /\/tagore\/admissions\?source=olympiad/);
    const leadAction = page.locator('.tg-module .tg-inline-link').first();
    await expect(leadAction).toHaveCSS('display', 'flex');
    const leadActionMargin = await leadAction.evaluate(el => parseFloat(getComputedStyle(el).marginTop));
    expect(leadActionMargin, 'module action links need visible spacing').toBeGreaterThan(0);
    await expect(page.locator('#quality-gates')).toBeVisible();
    await expect(page.locator('#cctv-health')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Check connection' })).toBeVisible();
    findings.push(...await checkPageHealth(page, 'platform growth workspace'));
    await page.getByRole('link', { name: 'Check connection' }).click();
    await expect(page.getByRole('heading', { name: 'CCTV connector status' })).toBeVisible();
    await expect(page.locator('body')).toContainText(/CCTV analytics is not configured/i);
    await page.goto('/tagore/platform', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Growth & quality workspace' })).toBeVisible();
    const desktopOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(desktopOverflow, 'platform page must not overflow desktop viewport').toBeFalsy();
    if (testInfo.project.name === 'desktop') await page.screenshot({ path: 'test-results/platform-growth-desktop.png', fullPage: true });

    if (testInfo.project.name === 'desktop') await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Growth & quality workspace' })).toBeVisible();
    const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(mobileOverflow, 'platform page must not overflow mobile viewport').toBeFalsy();
    await page.screenshot({ path: 'test-results/platform-growth-mobile.png', fullPage: true });
    expect(findings, 'platform page integrity findings').toEqual([]);
  });
  test('task workspace is readable and responsive on desktop and mobile', async ({ page }, testInfo) => {
    await authenticate(page, 'owner');
    await page.goto('/tagore/tasks', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Team tasks' })).toBeVisible();
    await expect(page.locator('.task-stats > .card')).toHaveCount(4);
    await expect(page.locator('#assign-work form[action*="/tagore/tasks"]')).toBeVisible();
    await expect(page.locator('#task-list')).toBeVisible();
    await expect(page.getByRole('button', { name: /apply filters/i })).toBeVisible();
    const desktopOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(desktopOverflow, 'task page must not overflow the desktop viewport').toBeFalsy();
    if (testInfo.project.name === 'desktop') await page.screenshot({ path: 'test-results/task-workspace-desktop.png', fullPage: true });

    if (testInfo.project.name === 'desktop') await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Team tasks' })).toBeVisible();
    const mobileMetrics = await page.evaluate(() => ({
      viewportWidth: window.innerWidth,
      documentWidth: document.documentElement.scrollWidth,
      tableWrap: (() => {
        const wrap = document.querySelector('.task-table-wrap');
        if (!wrap) return null;
        const table = wrap.querySelector('table');
        return {
          clientWidth: wrap.clientWidth,
          scrollWidth: wrap.scrollWidth,
          tableWidth: table ? Math.round(table.getBoundingClientRect().width) : 0,
          overflowX: getComputedStyle(wrap).overflowX
        };
      })()
    }));
    expect(mobileMetrics.documentWidth, 'task page must not overflow the mobile viewport').toBeLessThanOrEqual(mobileMetrics.viewportWidth + 2);
    expect(mobileMetrics.tableWrap, 'task table must have a dedicated responsive scroll container').toBeTruthy();
    expect(['auto', 'scroll']).toContain(mobileMetrics.tableWrap.overflowX);
    expect(mobileMetrics.tableWrap.scrollWidth, 'wide task columns must remain reachable by horizontal scrolling').toBeGreaterThan(mobileMetrics.tableWrap.clientWidth);
    await page.screenshot({ path: 'test-results/task-workspace-mobile.png', fullPage: true });
  });
  test('parent and student dashboards show role-appropriate summaries instead of misleading school-wide zeros', async ({ page }) => {
    await authenticate(page, 'parent');
    const familySummary = page.getByRole('region', { name: 'Family dashboard summary' });
    await expect(familySummary.getByText('My children', { exact: true })).toBeVisible();
    await expect(familySummary.getByText('Fee items due', { exact: true })).toBeVisible();
    await expect(familySummary.getByText('Open feedback', { exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Family shortcuts' })).toBeVisible();
    await expect(page.getByRole('link', { name: /Fee wallet/ })).toBeVisible();
    await expect(page.locator('.tg-module-grid').getByRole('link', { name: /Daily diary/ })).toBeVisible();
    await expect(page.locator('.tg-mobile-nav').getByRole('link', { name: /Children/ })).toBeVisible();
    await expect(page.getByText('Students', { exact: true })).toHaveCount(0);
    await expect(page.getByText('Staff', { exact: true })).toHaveCount(0);

    await authenticate(page, 'student');
    await expect(page.getByRole('heading', { name: 'Your student workspace' })).toBeVisible();
    await expect(page.getByRole('link', { name: /Open student portal/ })).toHaveAttribute('href', '/student/dashboard');
    await expect(page.getByRole('heading', { name: 'Go to work' })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Institutions' })).toHaveCount(0);
    await expect(page.locator('.tg-mobile-nav').getByRole('link', { name: /Portal/ })).toBeVisible();
    const studentPortalResponse = await page.goto('/student/dashboard', { waitUntil: 'domcontentloaded' });
    expect(studentPortalResponse?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
    await expect(page.getByText('Fee outstanding', { exact: true })).toHaveCount(0);
    await expect(page.getByText('Active work', { exact: true })).toHaveCount(0);
  });

  test('mobile dashboard navigation and search match the dark theme and remain usable', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await authenticate(page, 'parent');

    const search = page.getByRole('searchbox', { name: 'Search dashboard modules and visible items' });
    await expect(search).toBeVisible();
    await search.fill('Fee wallet');
    await expect(page.locator('#tg-search-status')).toContainText('matching item');
    await search.press('Escape');
    await expect(search).toHaveValue('');

    const nav = page.locator('.tg-mobile-nav');
    await expect(nav).toBeVisible();
    await expect(nav).toHaveCSS('background-color', 'rgba(26, 34, 45, 0.97)');
    await expect(nav.getByRole('link', { name: /Children/ })).toHaveCSS('color', 'rgb(192, 202, 215)');
    await expect(nav.locator('b').first()).toHaveCSS('color', 'rgb(83, 223, 168)');

    const dimensions = await page.evaluate(() => ({
      viewport: document.documentElement.clientWidth,
      document: document.documentElement.scrollWidth,
      navClient: document.querySelector('.tg-mobile-nav').clientWidth,
      navScroll: document.querySelector('.tg-mobile-nav').scrollWidth,
    }));
    expect(dimensions.document, 'mobile dashboard must not overflow the viewport').toBeLessThanOrEqual(dimensions.viewport + 2);
    expect(dimensions.navScroll, 'mobile navigation links must fit the visible navigation bar').toBeLessThanOrEqual(dimensions.navClient + 2);
    await page.screenshot({ path: 'test-results/dashboard-mobile-navigation.png', fullPage: true });

    await authenticate(page, 'student');
    await expect(page.locator('.tg-mobile-nav').getByRole('link', { name: /Portal/ })).toBeVisible();
    await expect(page.getByRole('searchbox', { name: 'Search dashboard modules and visible items' })).toBeVisible();
  });

  test('operations records support typed status filters and live search', async ({ page }) => {
    await authenticate(page, 'owner');
    await page.goto('/tagore/operations?module=inventory', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Inventory & Assets', exact: true })).toBeVisible();
    await expect(page.locator('#tg-operations-search')).toBeVisible();
    await expect(page.locator('#tg-operations-status')).toBeVisible();

    await page.locator('#tg-operations-module').selectOption('expense');
    await page.getByRole('button', { name: 'Open module' }).click();
    await expect(page.getByRole('heading', { name: 'Expenses & Purchases', exact: true })).toBeVisible();
    await page.locator('#tg-operations-module').selectOption('inventory');
    await page.getByRole('button', { name: 'Open module' }).click();
    await expect(page.getByRole('heading', { name: 'Inventory & Assets' })).toBeVisible();

    const itemName = 'QA searchable inventory ' + Date.now();
    const form = page.locator('form[action*="/tagore/operations/inventory"]').first();
    await form.locator('input[name="item_name"]').fill(itemName);
    await form.locator('input[name="category"]').fill('Acceptance QA');
    await form.locator('input[name="sku"]').fill('QA-' + Date.now());
    await form.locator('select[name="status"]').selectOption('active');
    await form.getByRole('button', { name: 'Create Inventory & Assets record' }).click();

    let row = page.locator('tr.tg-operations-record').filter({ hasText: itemName });
    await expect(row).toBeVisible();
    await page.locator('#tg-operations-search').fill(itemName);
    await expect(row).toBeVisible();
    await expect(page.locator('#tg-operations-count')).toContainText('1 of');

    await page.locator('#tg-operations-status').selectOption('inactive');
    await expect(row).toBeHidden();
    await expect(page.locator('#tg-operations-empty')).toBeVisible();

    await page.locator('#tg-operations-status').selectOption('');
    await expect(row).toBeVisible();
    await page.locator('#tg-operations-search').fill('');

    page.once('dialog', dialog => dialog.accept());
    row = page.locator('tr.tg-operations-record').filter({ hasText: itemName });
    await row.getByRole('button', { name: 'Delete' }).click();
    await expect(page.locator('tr.tg-operations-record').filter({ hasText: itemName })).toHaveCount(0);
  });

  test('task lifecycle creates work, records progress, and completes it', async ({ page }) => {
    await authenticate(page, 'owner');
    const title = 'E2E task lifecycle ' + Date.now();
    await page.goto('/tagore/tasks', { waitUntil: 'domcontentloaded' });
    const form = page.locator('#assign-work form[action*="/tagore/tasks"]');
    await form.locator('input[name="title"]').fill(title);
    await form.locator('textarea[name="description"]').fill('Disposable staging acceptance workflow.');
    await form.locator('select[name="priority"]').selectOption('high');
    await form.getByRole('button', { name: 'Create task' }).click();
    let taskRow = page.locator('.task-table tr').filter({ hasText: title }).filter({ has: page.locator('select[name="status"]') }).first();
    await expect(taskRow).toBeVisible();

    let updateForm = taskRow.locator('td').last().locator('form');
    await updateForm.locator('select[name="status"]').selectOption('in_progress');
    await updateForm.locator('input[name="progress"]').fill('35');
    await updateForm.getByRole('button', { name: 'Save' }).click();
    taskRow = page.locator('.task-table tr').filter({ hasText: title });
    await expect(taskRow.locator('.task-status')).toHaveText('In progress');
    await expect(taskRow.locator('.task-progress b')).toHaveText('35%');

    updateForm = taskRow.locator('td').last().locator('form');
    await updateForm.locator('select[name="status"]').selectOption('completed');
    await updateForm.locator('input[name="progress"]').fill('35');
    await updateForm.getByRole('button', { name: 'Save' }).click();
    taskRow = page.locator('.task-table tr').filter({ hasText: title });
    await expect(taskRow.locator('.task-status')).toHaveText('Completed');
    await expect(taskRow.locator('.task-progress b')).toHaveText('100%');
  });

  test('social content saves as an internal draft without claiming external publishing', async ({ page }) => {
    await authenticate(page, 'owner');
    const title = 'E2E social draft ' + Date.now();
    await page.goto('/tagore/platform', { waitUntil: 'domcontentloaded' });
    const form = page.locator('#social-post form[action*="/tagore/platform/post"]');
    await form.locator('input[name="title"]').fill(title);
    await form.locator('textarea[name="body"]').fill('Disposable acceptance draft; do not publish to external networks.');
    await form.locator('select[name="status"]').selectOption('draft');
    await form.getByRole('button', { name: 'Save social post' }).click();
    await expect(page.getByText('Social post draft saved.')).toBeVisible();
    const draftRow = page.locator('#social-post tbody tr').filter({ hasText: title });
    await expect(draftRow).toBeVisible();
    await expect(draftRow).toContainText('Draft');
    await expect(page.getByText(/Drafts stay inside Tagore ERP/)).toBeVisible();
  });

  test('AI test-plan studio degrades safely when no private model endpoint is configured', async ({ page }) => {
    await authenticate(page, 'owner');
    await page.goto('/tagore/platform', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'AI test-plan studio' })).toBeVisible();
    await expect(page.getByText('Model not configured')).toBeVisible();
    const form = page.locator('form[action*="/tagore/platform/ai-test-plan"]');
    await form.locator('select[name="module"]').selectOption('tasks');
    await form.locator('select[name="role"]').selectOption('teacher');
    await form.locator('select[name="test_type"]').selectOption('workflow');
    await form.locator('textarea[name="scenario"]').fill('A teacher creates a task and cannot open owner-only administration.');
    await form.getByRole('button', { name: 'Generate test plan' }).click();
    await expect(page.getByRole('status')).toContainText('AI test-plan generation is not enabled yet');
    await expect(page.getByRole('heading', { name: 'AI test-plan studio' })).toBeVisible();
  });

  test('CCTV connector is restricted to owner/principal and safely reports unconfigured status', async ({ page }) => {
    await authenticate(page, 'owner');
    await page.goto('/tagore/platform', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'CCTV & AI analytics health' })).toBeVisible();
    await page.getByRole('link', { name: 'Check connection' }).click();
    await expect(page.getByRole('heading', { name: 'CCTV connector status' })).toBeVisible();
    await expect(page.locator('body')).toContainText('CCTV analytics is not configured');
    const ownerResponse = await page.goto('/tagore/platform/cctv/status', { waitUntil: 'domcontentloaded' });
    expect(ownerResponse.status()).toBe(503);
    await expect(page.locator('body')).toContainText('CCTV analytics is not configured');

    await authenticate(page, 'teacher');
    const teacherResponse = await page.goto('/tagore/platform/cctv/status', { waitUntil: 'domcontentloaded' });
    expect(teacherResponse.status()).toBe(403);
  });

  test('forbidden owner administration is blocked for every non-owner role', async ({ page }) => {
    for (const role of roles.filter(r => r !== 'owner')) {
      await authenticate(page, role);
      const response = await page.goto('/tagore/admin', { waitUntil: 'networkidle' });
      expect([401, 403]).toContain(response.status());
    }
  });

  test('login form is keyboard-usable and visually consistent on desktop and mobile', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    const email = page.locator('input[name="email"]');
    const password = page.locator('input[name="password"]');
    const remember = page.locator('input[name="remember"]');
    const submit = page.getByRole('button', { name: /sign in to tagore erp/i });
    const loginCard = page.locator('.tg-login-card');

    await expect(email).toBeVisible();
    await expect(password).toBeVisible();
    await expect(remember).toBeVisible();
    await expect(submit).toBeVisible();
    await expect(page.locator('.tg-login-brand')).toBeVisible();
    await expect(loginCard).toHaveCSS('background-color', 'rgb(26, 34, 45)');
    await expect(submit).toHaveCSS('background-image', /linear-gradient/);

    await email.fill('runner-test@example.invalid');
    await password.fill('not-a-real-password');
    await remember.check();
    await expect(email).toHaveValue('runner-test@example.invalid');
    await expect(password).toHaveValue('not-a-real-password');
    await expect(remember).toBeChecked();
    expect(new URL(page.url()).pathname).toBe('/login');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('.tg-login-brand')).toBeVisible();
    await expect(page.locator('.tg-login-copy')).toBeHidden();
    await expect(loginCard).toBeVisible();
    const mobile = await page.evaluate(() => ({
      viewport: document.documentElement.clientWidth,
      document: document.documentElement.scrollWidth,
      brandWidth: document.querySelector('.tg-login-brand').getBoundingClientRect().width,
      cardWidth: document.querySelector('.tg-login-card').getBoundingClientRect().width,
    }));
    expect(mobile.document, 'mobile login must not overflow the viewport').toBeLessThanOrEqual(mobile.viewport + 2);
    expect(mobile.brandWidth).toBeGreaterThan(0);
    expect(mobile.cardWidth).toBeLessThanOrEqual(mobile.viewport);
    await page.screenshot({ path: 'test-results/login-mobile-charcoal-green.png', fullPage: true });
  });});
