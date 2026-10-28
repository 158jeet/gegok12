const { test } = require('@playwright/test');
const { expect, unique, signInAsOwner, submitAndAssertSuccess } = require('./helpers');

test('academic year: create, persist, and reject reversed dates', async ({ page }) => {
  await signInAsOwner(page);
  const form = page.locator('form').filter({ has: page.getByRole('button', { name: 'Add academic year' }) });
  test.skip(await form.locator('[name="institution_id"] option').count() === 0, 'Staging fixture required: an active institution.');
  await form.locator('[name="institution_id"]').selectOption({ index: 0 });
  const name = unique('Acceptance AY');
  await form.locator('[name="name"]').fill(name);
  await form.locator('[name="start_date"]').fill('2036-04-01');
  await form.locator('[name="end_date"]').fill('2037-03-31');
  await form.locator('[name="status"]').selectOption('inactive');
  await submitAndAssertSuccess(page, form, 'Academic year added.');
  await page.reload();
  await expect(page.locator('body')).toContainText(name);
  await form.locator('[name="institution_id"]').selectOption({ index: 0 });
  await form.locator('[name="name"]').fill(unique('Invalid AY'));
  await form.locator('[name="start_date"]').fill('2037-04-01');
  await form.locator('[name="end_date"]').fill('2037-03-31');
  await form.getByRole('button', { name: 'Add academic year' }).click();
  await expect(page.locator('body')).toContainText(/after start date|end date/i);
});
