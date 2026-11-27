const { test, expect } = require('@playwright/test');
function localDateTime(value) {
  const timeZone = process.env.TIMEZONE || 'Asia/Kolkata';
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
  }).formatToParts(value);
  const fields = Object.fromEntries(parts.map(part => [part.type, part.value]));
  return `${fields.year}-${fields.month}-${fields.day}T${fields.hour}:${fields.minute}:${fields.second}`;
}
const path = require('path');

async function signInAs(page, role) {
  await page.goto('/__e2e/session/' + role, { waitUntil: 'commit', timeout: 30000 });
  await expect(page).toHaveURL(/\/tagore\/dashboard/, { timeout: 15000 });
}

test.describe('New ERP acceptance slices', () => {
  test('manager performance dashboard loads without browser errors', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    await signInAs(page, 'owner');
    const response = await page.goto('/tagore/performance', { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Staff performance' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Staff workload' })).toBeVisible();
    expect(errors).toEqual([]);
  });

  test('manager can create a draft Olympiad, import questions, approve, and publish', async ({ page }) => {
    const title = 'QA Olympiad ' + Date.now();
    const code = 'QA-' + String(Date.now()).slice(-9);
    await signInAs(page, 'owner');
    const response = await page.goto('/tagore/olympiad', { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(200);
    await page.locator('#institution_id').selectOption({ index: 0 });
    await page.locator('#title').fill(title);
    await page.locator('#code').fill(code);
    await page.locator('#max_marks').fill('1');
    await page.locator('#exam_starts_at').fill(localDateTime(new Date(Date.now() + 60000)));
    await page.locator('#exam_ends_at').fill(localDateTime(new Date(Date.now() + 60 * 60000)));
    await page.locator('input[name="target_classes[]"][value="6"]').check();
    await page.locator('button[type="submit"]').filter({ hasText: 'Save draft' }).click();
    await expect(page.getByRole('status')).toContainText('saved as draft');

    const eventCard = page.locator('article').filter({ hasText: title });
    await eventCard.getByRole('link', { name: 'Manage questions' }).click();
    await page.getByLabel('Question bank JSON').fill(JSON.stringify([{
      question: 'Which planet is known as the Red Planet?',
      options: { A: 'Venus', B: 'Mars', C: 'Jupiter', D: 'Mercury' },
      answer: 'B', marks: 1,
    }]));
    await page.getByRole('button', { name: 'Import as pending review' }).click();
    await expect(page.getByText('Pending review', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Approve question' }).click();
    await expect(page.getByText('Approved', { exact: true })).toBeVisible();

    await page.goto('/tagore/olympiad', { waitUntil: 'domcontentloaded' });
    const publishedCard = page.locator('article').filter({ hasText: title });
    await publishedCard.getByRole('button', { name: 'Publish' }).click();
    await expect(page.getByRole('status')).toContainText('Olympiad published');
    await expect(page.locator('article').filter({ hasText: title }).getByText('Published')).toBeVisible();
  });

  test('student sees the student Olympiad page but not admin controls', async ({ page }) => {
    await signInAs(page, 'student');
    const response = await page.goto('/tagore/olympiad', { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Olympiads for you' })).toBeVisible();
    await expect(page.locator('#institution_id')).toHaveCount(0);
    const adminResponse = await page.goto('/tagore/olympiad/1', { waitUntil: 'commit' });
    expect(adminResponse.status()).toBe(403);
  });

  test('manager can import an Exameow XLSX template and preserve review metadata', async ({ page }) => {
    const title = 'Exameow Import QA ' + Date.now();
    const code = 'EX-' + String(Date.now()).slice(-9);
    await signInAs(page, 'owner');
    await page.goto('/tagore/olympiad', { waitUntil: 'domcontentloaded' });
    await page.locator('#institution_id').selectOption({ index: 0 });
    await page.locator('#title').fill(title);
    await page.locator('#code').fill(code);
    await page.locator('#max_marks').fill('1');
    await page.locator('#exam_starts_at').fill(localDateTime(new Date(Date.now() + 60000)));
    await page.locator('#exam_ends_at').fill(localDateTime(new Date(Date.now() + 60 * 60000)));
    await page.locator('input[name="target_classes[]"][value="6"]').check();
    await page.locator('button[type="submit"]').filter({ hasText: 'Save draft' }).click();
    const eventCard = page.locator('article').filter({ hasText: title });
    await eventCard.getByRole('link', { name: 'Manage questions' }).click();

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('link', { name: 'Download Excel template' }).click(),
    ]);
    expect(download.suggestedFilename()).toBe('tagore-olympiad-question-template.xlsx');
    await page.locator('#question_bank_file').setInputFiles(path.join(__dirname, '..', 'fixtures', 'exameow_template.xlsx'));
    await page.getByRole('button', { name: 'Import spreadsheet/file' }).click();
    await expect(page.getByText('What type of AI API protocol does Exameow use?')).toBeVisible();
    await expect(page.getByText('Pending review', { exact: true })).toBeVisible();
    await expect(page.getByText('Answer explanation')).toBeVisible();
    await page.getByText('Answer explanation').click();
    await expect(page.getByText('Exameow works with any OpenAI-compatible API provider.')).toBeVisible();
    await page.getByRole('button', { name: 'Approve question' }).click();
    await expect(page.getByText('Approved', { exact: true })).toBeVisible();
  });


  test('eligible student can register, sit the scheduled exam, and receive a server-scored result', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile', 'The timed exam lifecycle is exercised once on desktop; mobile layout/role checks run separately.');
    test.setTimeout(240000);
    const title = 'Student Exam QA ' + Date.now();
    const code = 'ST-' + String(Date.now()).slice(-9);
    const examStart = new Date(Date.now() + 3 * 60000);
    const examEnd = new Date(Date.now() + 20 * 60000);
    await signInAs(page, 'owner');
    await page.goto('/tagore/olympiad', { waitUntil: 'domcontentloaded' });
    // The demo student is enrolled in this configured institution; do not rely on display order.
    const institutionId = process.env.TAGORE_OLYMPIAD_INSTITUTION_ID || '1';
    await expect(page.locator(`#institution_id option[value="${institutionId}"]`)).toHaveCount(1);
    await page.locator('#institution_id').selectOption(institutionId);
    await page.locator('#title').fill(title);
    await page.locator('#code').fill(code);
    await page.locator('#max_marks').fill('1');
    await page.locator('#exam_starts_at').fill(localDateTime(examStart));
    await page.locator('#exam_ends_at').fill(localDateTime(examEnd));
    await page.locator('input[name="target_classes[]"][value="6"]').check();
    await page.locator('button[type="submit"]').filter({ hasText: 'Save draft' }).click();
    await page.locator('article').filter({ hasText: title }).getByRole('link', { name: 'Manage questions' }).click();
    await page.getByLabel('Question bank JSON').fill(JSON.stringify([{question:'What is 2 + 2?',options:{A:'3',B:'4',C:'5',D:'6'},answer:'B',marks:1}]));
    await page.getByRole('button', { name: 'Import as pending review' }).click();
    await page.getByRole('button', { name: 'Approve question' }).click();
    await page.goto('/tagore/olympiad', { waitUntil: 'domcontentloaded' });
    await page.locator('article').filter({ hasText: title }).getByRole('button', { name: 'Publish' }).click();

    await signInAs(page, 'student');
    await page.goto('/tagore/olympiad', { waitUntil: 'domcontentloaded' });
    const studentCard = page.locator('article').filter({ hasText: title });
    await expect(studentCard).toBeVisible();
    await studentCard.getByRole('button', { name: 'Register' }).click();
    await expect(page.getByRole('status')).toContainText('Registration confirmed');
    const waitMs = examStart.getTime() - Date.now() + 1200;
    if (waitMs > 0) await page.waitForTimeout(waitMs);
    await page.goto('/tagore/olympiad', { waitUntil: 'domcontentloaded' });
    const readyCard = page.locator('article').filter({ hasText: title });
    await readyCard.getByRole('link', { name: 'Start exam' }).click();
    await expect(page.getByRole('heading', { name: 'Question 1' })).toBeVisible();
    await expect(page.getByText('Correct option')).toHaveCount(0);
    await page.locator('input[type="radio"][value="B"]').check();
    await page.locator('#exam-form').evaluate(form => { const forged=document.createElement('input'); forged.name='score'; forged.value='999'; form.appendChild(forged); });
    await page.getByRole('button', { name: 'Submit exam' }).click();
    await expect(page.getByRole('heading', { name: 'Exam submitted successfully' })).toBeVisible();
    await expect(page.getByText('1.00 / 1.00')).toBeVisible();
  });
});