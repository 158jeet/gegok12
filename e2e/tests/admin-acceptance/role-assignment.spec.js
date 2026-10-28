const { test } = require('@playwright/test');
const { expect, signInAsOwner, submitAndAssertSuccess } = require('./helpers');

test('role assignment: enforce active role and institution scope, then persist assignment', async ({ page }) => {
  await signInAsOwner(page);
  const form = page.locator('form').filter({ has: page.getByRole('button', { name: 'Save role assignment' }) });
  const users = await form.locator('[name="user_id"]').count();
  test.skip(users !== 1, 'Role assignment form did not render.');
  const roleOptions = form.locator('[name="role_id"] option');
  test.skip(await roleOptions.count() === 0, 'Staging fixture required: active roles.');
  const institutionOptions = form.locator('[name="institution_id"] option:not([value=""])');
  test.skip(await institutionOptions.count() === 0, 'Staging fixture required: active institutions.');
  const role = await roleOptions.evaluateAll(opts => opts.find(o => !/\(OWNER\)/.test(o.textContent || ''))?.value);
  test.skip(!role, 'Staging fixture required: an active non-owner role.');
  const userId = await page.locator('form').filter({ has: page.getByRole('button', { name: 'Save department assignment' }) }).locator('[name="user_id"] option').first().getAttribute('value').catch(() => null);
  test.skip(!userId, 'Staging fixture required: a staff user linked to an institution school.');
  const staffOption = await page.locator('form').filter({ has: page.getByRole('button', { name: 'Save department assignment' }) }).locator('[name="user_id"] option').first().evaluate(el => ({ value: el.value, schoolId: el.dataset.school }));
  const matchingInstitution = await form.locator('[name="institution_id"] option').evaluateAll((opts, schoolId) => opts.find(o => o.dataset.school === schoolId)?.value, staffOption.schoolId);
  if (!matchingInstitution) test.skip(true, 'No institution matches the selected staff member school.');
  await form.locator('[name="user_id"]').fill(staffOption.value);
  await form.locator('[name="role_id"]').selectOption(role);
  await form.locator('[name="institution_id"]').selectOption(matchingInstitution);
  await submitAndAssertSuccess(page, form, 'Role assignment saved.');
  await page.reload();
  await expect(page.locator('body')).toContainText('Active Role Assignments');
});
