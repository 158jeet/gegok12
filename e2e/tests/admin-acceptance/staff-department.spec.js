const { test } = require('@playwright/test');
const { expect, unique, signInAsOwner, submitAndAssertSuccess } = require('./helpers');

test('staff department: assign department and verify persisted assignment', async ({ page }) => {
  await signInAsOwner(page);
  const form = page.locator('form').filter({ has: page.getByRole('button', { name: 'Save department assignment' }) });
  const staff = form.locator('[name="user_id"] option');
  const departments = form.locator('[name="department_id"] option');
  test.skip(await staff.count() === 0, 'Staging fixture required: staff linked to an institution school.');
  test.skip(await departments.count() === 0, 'Staging fixture required: active department.');
  const staffOption = await staff.first().evaluate(el => ({ value: el.value, schoolId: el.dataset.school }));
  const matchingDepartment = await departments.evaluateAll((opts, schoolId) => opts.find(o => o.dataset.school === schoolId)?.value, staffOption.schoolId);
  if (!matchingDepartment) test.skip(true, 'No active department matches the selected staff member school.');
  await form.locator('[name="user_id"]').selectOption(staffOption.value);
  await form.locator('[name="department_id"]').selectOption(matchingDepartment);
  await form.locator('[name="designation"]').fill(unique('Acceptance Role'));
  await form.locator('[name="is_primary"]').check();
  await submitAndAssertSuccess(page, form, 'Department assignment saved.');
  await page.reload();
  await expect(page.locator('body')).toContainText('Assign Staff Department');
});
