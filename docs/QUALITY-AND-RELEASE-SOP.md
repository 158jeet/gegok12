# Software Quality and Release SOP

**Status:** Mandatory project standard  
**Applies to:** ERP, web, Android, Windows, APIs, AI features, marketing/media tools, and infrastructure automation.

## Release rule
A release is blocked while any mandatory gate is FAIL, BLOCKED, or NOT RUN. Exceptions require an explicitly authorized written decision, scope, risk, mitigation, and expiry. A successful HTTP response or build alone never constitutes acceptance.

## Required gates
1. **Requirements baseline:** define user stories, roles, acceptance criteria, supported devices, data/security boundaries, and rollback requirements.
2. **Source/build integrity:** inspect Git diff and commit; validate dependency manifests/lockfiles, secrets, static analysis, syntax, build, migrations, and reproducibility.
3. **Unit/integration/API:** test business rules, validation, persistence, authorization, queues, integrations, duplicate submissions, and error paths.
4. **Browser E2E:** Playwright tests log in as each supported role, traverse every in-scope route, operate every critical control, complete end-to-end workflows, and verify resulting state.
5. **Visual/responsive:** capture desktop and mobile screenshots; check text encoding, overlap, clipping, overflow, missing assets, console/page errors, failed requests, and unintended visual regressions. Review screenshot differences rather than auto-accepting them.
6. **Security/accessibility/performance:** verify least privilege and sensitive-data isolation; run authorized OWASP ZAP checks, axe-core/Lighthouse, and k6 when relevant.
7. **Operations:** verify health/readiness, migrations, queue/worker behavior, logs, backups, restore, restart/reboot recovery, and rollback.
8. **Release:** publish an evidence-backed report and approve only when mandatory gates pass.

## Status vocabulary
- **PASS:** executed and acceptance criteria met, with evidence.
- **FAIL:** executed and criteria not met.
- **BLOCKED:** could not execute due to environment/access/dependency.
- **NOT RUN:** not executed.
- **NOT APPLICABLE:** excluded with a reason.

Never represent BLOCKED, NOT RUN, or NOT APPLICABLE as PASS. Each report records commit/build, environment, tests and counts, defects/severity, screenshots/traces/logs, retest results, known risks, and release decision.

## Safety and data integrity
- Use disposable databases and synthetic records for automated/destructive tests.
- Never run destructive test cases against production student, parent, finance, or staff data.
- Keep test-only login routes gated by both testing environment and explicit test enablement; prove they are absent in production.
- Do not print secrets into CI logs or commit real credentials.
- Back up before production migrations or deployment; verify a recovery path.
- Fix root causes; do not weaken assertions simply to make a pipeline green.

## Default toolchain
- Playwright for browser E2E, traces, screenshots, and videos.
- PHPUnit/Pest and native framework tests for application logic.
- GitHub Actions or a controlled self-hosted runner for CI.
- OWASP ZAP for authorized web security scans; axe-core/Lighthouse for accessibility/performance; k6 for load tests.
- Appium for native mobile apps; FFmpeg/OpenCV/Tesseract for media pipeline checks when applicable.
- Prefer free/open-source/self-hosted tools where practical; use a paid tool when a concrete requirement justifies it.

## Project-specific acceptance
Each project maintains its own route/role/workflow matrix and thresholds under version control. The common gates remain mandatory; tests are adapted to the product and technology.