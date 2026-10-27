# Tagore ERP — Repeatable Acceptance System

This is the release contract for every ERP change, including changes requested by the review team.

## Required pipeline gates

A change is accepted only when all applicable gates have a recorded result for the exact candidate commit SHA. A previous run, another branch, or a green dashboard screenshot cannot satisfy a gate.

1. **Static and backend gate** — Composer validation, PHP syntax, clean migrations, seeded database, route bootstrap, and Laravel tests.
2. **Browser route and role gate** — authenticated browser suite for owner, principal, coordinator, teacher, parent, student, accounts, and fee-editor. Every discovered concrete Tagore route must return an expected status; server errors, broken images, console errors, visible mojibake, and horizontal overflow are failures.
3. **Workflow gate** — business transactions must be tested, not just pages opened. Include positive and negative permission tests, persisted data assertions, duplicate/retry behavior, and cross-role visibility.
4. **Responsive and visual gate** — desktop and narrow mobile viewports; capture screenshots and Playwright traces for failures; review every changed page and representative role views.
5. **Production packaging gate** — Docker build, migrations, Laravel caches, Nginx configuration, HTTPS health response, and Windows deployment script syntax.
6. **Client gate** — Android build and emulator launch for parent and teacher apps; Windows client package and startup/sync smoke test when client code changes.
7. **CI release gate** — the `Acceptance gate (release blocking)` job depends on backend tests, authenticated browser acceptance, production Docker/HTTPS smoke, and frontend build. It fails if any dependency is failed, skipped, cancelled, or otherwise not `success`; its job summary records the exact candidate SHA and every dependency result. Configure this named check as required in repository branch protection before treating it as a hard merge block.

## Live-site acceptance

The manual GitHub Actions workflow **Tagore Live-Site Acceptance** tests the deployed website from a GitHub-hosted browser runner. It is read-only: it signs into dedicated test accounts, checks dashboard rendering and visible navigation routes, and verifies responsive login rendering. It does not submit forms or mutate student, fee, or attendance data.

Before the first run, configure the repository Actions secret `TAGORE_LIVE_ACCEPTANCE_ACCOUNTS_JSON` with a JSON object containing a dedicated test account for each role. Example shape (use test credentials, never production staff passwords):

```json
{
  "owner": {"email": "owner-acceptance@example.invalid", "password": "replace-with-test-password"},
  "principal": {"email": "principal-acceptance@example.invalid", "password": "replace-with-test-password"},
  "coordinator": {"email": "coordinator-acceptance@example.invalid", "password": "replace-with-test-password"},
  "teacher": {"email": "teacher-acceptance@example.invalid", "password": "replace-with-test-password"},
  "parent": {"email": "parent-acceptance@example.invalid", "password": "replace-with-test-password"},
  "student": {"email": "student-acceptance@example.invalid", "password": "replace-with-test-password"},
  "accounts": {"email": "accounts-acceptance@example.invalid", "password": "replace-with-test-password"},
  "fee-editor": {"email": "fee-editor-acceptance@example.invalid", "password": "replace-with-test-password"}
}
```

Run **Actions → Tagore Live-Site Acceptance → Run workflow**, enter the HTTPS base URL and environment label, and inspect the result and the uploaded screenshot/trace artifact. If the production server firewall does not permit GitHub-hosted runner access, use a self-hosted runner with a restricted network path; do not expose an administrative port or disable authentication to make the test pass.

The live workflow fails closed when the base URL or any role account is missing. Test accounts should be least-privileged, contain synthetic records, and never be reused by real users. Rotate their passwords and remove the accounts when no longer needed. A passing live read-only sweep is evidence for those pages and routes only; it does not certify every mutating business workflow.

## Mandatory business workflow scenarios

- **Admission/family linking:** link an existing student to the correct parent; reject a parent from another institution, invalid student, unauthorized role, and nonexistent parent account; link siblings to one parent; verify idempotent repeat submission and audit history.
- **Family fees:** compare the consolidated family payable/paid/outstanding against the sum of individual student statements; include a child with no fee obligation; verify institution boundaries and parent visibility.
- **Daily diary:** automated Laravel feature tests verify parent/school field ownership, persistence across updates, same-day uniqueness, role-visible diary page, future-date rejection, and invalid study hours. Playwright desktop acceptance submits as parent, updates as school staff, then verifies both sides' saved content; the mobile project checks the narrow layout. Continue adding explicit unrelated-parent and out-of-scope-staff negative tests as fixture coverage is extended.
- **Fees/payments:** record an allowed offline payment, verify balance and receipt, prevent unauthorized payment/reconciliation, and verify reconciliation mismatch handling without mutating production data.
- **Core operations:** admissions, tasks, leave, accounts, fee management, communication, reports, academic setup, administration, and operations. Test create/read/update/validation/authorization for the actions each role is allowed to perform.

## How to run it repeatedly

- Every pull request runs the ERP CI workflow and prototype workflow automatically. The ERP CI workflow also emits one aggregate `Acceptance gate (release blocking)` check after its four required jobs complete.
- In GitHub repository Settings → Rules → Rulesets (or Branches → Branch protection rules), create a rule for `main` that requires pull requests and the status check `Acceptance gate (release blocking)`. Also block force pushes/deletion and require branches to be up to date if your team policy permits. The GitHub connection used during this task can create code changes but does not have repository administration permission to enforce that settings change; do not assume this rule is already active.
- Use the ERP CI workflow's **Run workflow** action for a deliberate re-run against a candidate branch or main SHA.
- Read the run for the exact commit SHA, then inspect job summaries and download the `tagore-human-style-e2e-report` artifact. A green check is not accepted if the artifact or required workflow is missing.
- For a failed test, retain the failing URL, role, expected/actual result, console/network error, screenshot/trace, and test data identifiers. Fix the cause, commit the fix, and rerun the full gate.
- Never use real parent/student data or production writes in CI. Use disposable seeded databases and a non-production environment for destructive workflow tests.

## Status vocabulary

- **PASS** — completed test and evidence exist for the exact SHA.
- **FAIL** — a reproducible assertion or safety check failed.
- **BLOCKED** — test could not run due to missing runner, environment, credentials, or test fixture.
- **PENDING** — GitHub has not completed the check; it is neither pass nor fail.
- **NOT APPLICABLE** — only permitted when the change genuinely does not touch that surface and the reason is recorded.

The overall status is PASS only when all required gates are PASS. Pending and blocked are not passes.
