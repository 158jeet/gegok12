const { test, expect } = require('@playwright/test');

async function authenticate(page) {
  await page.goto('/__e2e/session/owner', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await expect(page).toHaveURL(/\\/tagore\\/dashboard/);
}

test.describe('Student promotion workspace', () => {
  test('Owner can load institution/year filters and the batch selection workspace', async ({ page }) => {
    await authenticate(page);
    await page.goto('/tagore/admin/student-promotion', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: 'Student Promotion' })).toBeVisible();
    await expect(page.getByLabel('Institution')).toBeVisible();
    await expect(page.getByLabel('Source academic year')).toBeVisible();
    await expect(page.getByLabel('Target academic year')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Select destination class and students' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Process Promotion Batch' })).toBeVisible();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    expect(overflow, 'promotion workspace must not overflow its viewport').toBeFalsy();
  });
});
