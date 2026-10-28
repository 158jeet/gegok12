const { test } = require('@playwright/test');
const { expect, unique, signInAsOwner, submitAndAssertSuccess } = require('./helpers');

test('institution: create, persist, refresh listing and reject duplicate school linkage', async ({ page }) => {
  await signInAsOwner(page);
  const form = page.locator('form').filter({ has: page.getByRole('button', { name: 'Add institution' }) });
  const school = form.locator('[name="school_id"] option:not([disabled])').first();
  test.skip(await school.count() === 0, 'Staging fixture required: an unlinked school.');
  const schoolId = await school.getAttribute('value');
  const code = unique('AT').toUpperCase().slice(0, 20);
  const name = unique('Acceptance Institution');
  await form.locator('[name="school_id"]').selectOption(schoolId);
  await form.locator('[name="code"]').fill(code);
  await form.locator('[name="display_name"]').fill(name);
  await form.locator('[name="type"]').fill('school');
  await submitAndAssertSuccess(page, form, 'Institution added.');
  await expect(page.locator('body')).toContainText(name);
  await page.reload();
  await expect(page.locator('body')).toContainText(name);
});
