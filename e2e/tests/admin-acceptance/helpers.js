const { test, expect } = require('@playwright/test');

function unique(prefix) {
  return prefix + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 7);
}

async function signInAsOwner(page) {
  if (process.env.TAGORE_ADMIN_MUTATION_ENABLED !== 'true' || (process.env.TAGORE_LIVE_ENVIRONMENT || '').toLowerCase() !== 'staging') {
    test.skip(true, 'Mutating admin acceptance runs only when explicitly enabled against staging.');
  }
  const accounts = JSON.parse(process.env.TAGORE_LIVE_ACCEPTANCE_ACCOUNTS_JSON || '{}');
  const owner = accounts.owner;
  if (!owner?.email || !owner?.password) throw new Error('Dedicated staging owner account is missing.');
  await page.goto('/login');
  await page.locator('#email').fill(owner.email);
  await page.locator('#password').fill(owner.password);
  await page.getByRole('button', { name: 'Sign in to Tagore ERP' }).click();
  await expect(page).toHaveURL(/\/tagore\/dashboard(?:[/?#]|$)/);
  await page.goto('/tagore/admin');
  await expect(page.getByRole('heading', { name: 'Tagore Administration' })).toBeVisible();
}

async function submitAndAssertSuccess(page, form, successText) {
  await form.getByRole('button').click();
  await expect(page.locator('body')).toContainText(successText, { timeout: 15000 });
}

module.exports = { expect, unique, signInAsOwner, submitAndAssertSuccess };
