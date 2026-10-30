const { test } = require('@playwright/test');
const { expect, unique, signInAsOwner, submitAndAssertSuccess } = require('./helpers');

test('department: create, persist and validate duplicate code', async ({ page }) => {
  await signInAsOwner(page);
  const form = page.locator('form').filter({ has: page.getByRole('button', { name: 'Add department' }) });
  test.skip(await form.locator('[name="institution_id"] option').count() === 0, 'Staging fixture required: an active institution.');
  await form.locator('[name="institution_id"]').selectOption({ index: 0 });
  const institutionId = await form.locator('[name="institution_id"]').inputValue();
  const name = unique('Acceptance Department');
  const code = unique('AT').toUpperCase().slice(0, 20);
  await form.locator('[name="name"]').fill(name);
  await form.locator('[name="code"]').fill(code);
  await submitAndAssertSuccess(page, form, 'Department added.');
  await page.reload();
  await expect(page.locator('body')).toContainText(name);
  await form.locator('[name="institution_id"]').selectOption(institutionId);
  await form.locator('[name="name"]').fill(unique('Duplicate Department'));
  await form.locator('[name="code"]').fill(code.toLowerCase());
  await form.getByRole('button', { name: 'Add department' }).click();
  await expect(page.locator('body')).toContainText('Department code already exists in this institution.');
});
