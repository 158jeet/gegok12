const { test, expect } = require('@playwright/test');

async function authenticate(page, role) {
  await page.goto('/__e2e/session/' + role, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await expect(page).toHaveURL(/\\/tagore\\/dashboard/);
}

test.describe('Daily diary acceptance workflow', () => {
  test('parent and school share a diary entry; desktop/mobile layout remains usable', async ({ page }, testInfo) => {
    await authenticate(page, 'parent');
    await page.goto('/tagore/diary', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Daily learning diary' })).toBeVisible();
    await expect(page.getByText('Home study time (hours)')).toBeVisible();
    await expect(page.getByLabel('Parent feedback / questions')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save diary entry' }).first()).toBeVisible();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(overflow, 'daily diary must not horizontally overflow its viewport').toBeFalsy();

    if (testInfo.project.name === 'mobile') {
      await page.screenshot({ path: 'test-results/daily-diary-parent-mobile.png', fullPage: true });
      return;
    }

    const parentNote = 'E2E parent note ' + Date.now();
    const date = await page.locator('input[name="diary_date"]').first().inputValue();
    await page.locator('input[name="study_hours"]').first().fill('2.5');
    await page.getByLabel('Parent feedback / questions').first().fill(parentNote);
    await page.getByRole('button', { name: 'Save diary entry' }).first().click();
    await expect(page.getByRole('status')).toContainText('Daily diary saved.');

    await authenticate(page, 'owner');
    await page.goto('/tagore/diary?date=' + encodeURIComponent(date), { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Daily learning diary' })).toBeVisible();
    await expect(page.locator('body')).toContainText(parentNote);
    await expect(page.getByLabel('Homework / classwork').first()).toBeVisible();
    const homework = 'E2E homework ' + Date.now();
    await page.getByLabel('Homework / classwork').first().fill(homework);
    await page.getByLabel('School feedback').first().fill('E2E school feedback');
    await page.getByRole('button', { name: 'Save diary entry' }).first().click();
    await expect(page.getByRole('status')).toContainText('Daily diary saved.');

    await authenticate(page, 'parent');
    await page.goto('/tagore/diary?date=' + encodeURIComponent(date), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText(parentNote);
    await expect(page.locator('body')).toContainText(homework);
    await expect(page.locator('body')).toContainText('E2E school feedback');
    await expect(page.locator('input[name="study_hours"]').first()).toHaveValue('2.50');
    await page.screenshot({ path: 'test-results/daily-diary-parent-desktop.png', fullPage: true });
  });
});
