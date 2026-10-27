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
7. **Release gate** — no merge/deploy while any required check is queued, pending, skipped unexpectedly, failed, or missing evidence.

## Mandatory business workflow scenarios

- **Admission/family linking:** link an existing student to the correct parent; reject a parent from another institution, invalid student, unauthorized role, and nonexistent parent account; link siblings to one parent; verify idempotent repeat submission and audit history.
- **Family fees:** compare the consolidated family payable/paid/outstanding against the sum of individual student statements; include a child with no fee obligation; verify institution boundaries and parent visibility.
- **Daily diary:** parent saves study hours and parent feedback; school saves homework and school feedback for the same date; each side sees the other's saved values; parent cannot alter school-owned fields; school cannot alter parent-owned fields; unrelated parent and out-of-scope staff are denied; future dates and invalid hours are rejected.
- **Fees/payments:** record an allowed offline payment, verify balance and receipt, prevent unauthorized payment/reconciliation, and verify reconciliation mismatch handling without mutating production data.
- **Core operations:** admissions, tasks, leave, accounts, fee management, communication, reports, academic setup, administration, and operations. Test create/read/update/validation/authorization for the actions each role is allowed to perform.

## How to run it repeatedly

- Every pull request runs the ERP CI workflow and prototype workflow automatically.
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
