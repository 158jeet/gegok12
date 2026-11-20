# Tagore ERP — OSS Market Review and Integration Decisions
Date: 2026-10-13
Branch: work/parallel-erp-olympiad-ai-social-20261013
Status: Research decisions recorded; feature integration and full acceptance remain in progress.

## Constraints
- No paid subscription is required for the baseline.
- ERP remains the system of record for staff, roles, institutions, admissions leads and audit history.
- Keep third-party pilots private and isolated until security, data retention, backup/restore, localization, performance and role-based acceptance pass.
- Never scrape social networks or ask staff to paste social account passwords. Use official OAuth/API support or an approved manual-export workflow.
- AI output is advisory/draft only; staff approval is mandatory before public-facing content is published.
- Do not write to production tables from a pilot or test run.

## Competitor and open-source review

| Problem | Candidate | Relevant product pattern | Decision for Tagore |
|---|---|---|---|
| Staff task management | Vikunja (AGPL-3.0, self-hostable) | List, Kanban, Gantt and table views; projects, assignees, priorities, due dates, reminders, recurring tasks, labels, saved filters and sharing | Use these as UX benchmarks. Keep the first implementation ERP-native to preserve institution scoping, role checks and a single source of truth. Reconsider importing Vikunja only after API/identity mapping and AGPL obligations are reviewed. |
| Olympiad/exams | Exameow (Apache-2.0, private pilot) | Lightweight exam engine and automated scoring | Retain as a private technical pilot only. The existing pilot report documents seven-day automatic deletion, limited candidate profile fields, missing Hindi UI, broad CORS and no completed public rate-limit/retention review. Registration must stay in the ERP-owned validated lead intake; correlate results with a random candidate reference, not phone numbers. |
| Larger question bank / quiz system | Moodle (open source) | Reusable question bank; configurable quizzes, feedback and quiz reports | Keep as the fallback evaluation if Exameow cannot satisfy durable question-bank, retention, Hindi UX and reporting needs. Do not add a second LMS until a real pilot comparison is completed. |
| Social scheduling | OpenPost (AGPL-3.0-only, isolated local pilot) | Self-hosted post creation, scheduling and tracking; platform support varies by official API and provider approval | Continue isolated evaluation. The pilot is not ready for staff use: first-admin setup and authenticated draft/approval/publishing workflows still need acceptance. No production social credentials are configured. |
| Alternative social scheduler | Postiz (AGPL-3.0, self-hostable) | Scheduling, collaboration, analytics, public API and automation integrations | Do not run a second scheduler alongside OpenPost yet. Re-evaluate only if OpenPost fails a documented acceptance test; review AGPL obligations and each platform's developer-app approval before adoption. |
| AI/testing platform | Playwright + axe-core; PHPUnit/Laravel tests; Lighthouse; k6; OWASP ZAP | Browser automation, screenshots/traces, accessibility scans, performance/load tests and security checks | Use the existing Playwright role-based suite and isolated SQLite CI workflow as the baseline. Add axe scans and bounded staging load/security tests. Never run destructive tests against the production database. |

## Source references
- Vikunja features and AGPLv3: https://vikunja.io/features/ and https://vikunja.io/docs/
- Moodle reusable question bank and quiz reports: https://docs.moodle.org/401/en/Quiz_activity
- OpenPost self-hosting, provider support and AGPL-3.0-only license: https://github.com/getopenpost/openpost
- Postiz self-hosted scheduler and AGPL-3.0 license: https://github.com/gitroomhq/postiz-app
- Playwright accessibility testing and its limits: https://playwright.dev/docs/accessibility-testing
- Playwright trace viewer: https://playwright.dev/docs/trace-viewer

## Acceptance method to apply
1. Static checks: PHP syntax, Composer validation/dependency resolution, frontend build, migration status and route cache in an isolated environment.
2. Backend tests: authorization, institution scoping, input validation, lifecycle transitions, data persistence, audit records and queue failure handling.
3. Browser tests: all eight ERP roles; real clicks and form submission; console/network errors; broken assets; text/mojibake checks; desktop and 390px mobile layouts; screenshot evidence and traces on failure.
4. Visual/accessibility: human review plus axe checks; keyboard focus, labels, contrast, clipped text, horizontal overflow and readable error/empty states.
5. Security: cross-institution access, CSRF, auth/session boundaries, test-only endpoints disabled in production, upload validation, rate limits, secrets and CORS.
6. Performance/recovery: bounded load tests against staging, backup/restore rehearsal, queue/Redis/database recovery and certificate-renewal test.
7. Release gate: all P0 checks pass; failed checks have an owner and evidence; no public launch while a critical privacy, security or retention gap remains.

## Current verified state and blockers
- ERP production Docker services were running, but the unauthenticated browser route sweep initially found that HTTPS was broken because Windows-mounted Certbot live symlinks appeared as zero-byte files inside Nginx.
- A separate Nginx certificate-export directory now contains concrete PEM copies; the web container has been recreated from the existing validated image and Nginx configuration test passed. A real browser login/protected-route smoke subsequently passed (2/2).
- Certificate renewal task action now points at the current repository script. A manual renewal run is still in progress because Certbot applied its random renewal delay; its final result must be checked.
- The unauthenticated route sweep passed /login and correctly redirected protected routes to login, but /tagore/performance returned 404 in the running container even though the source branch defines that route. This proves the running image does not yet contain all current source changes; rebuild/staging verification is required before claiming those changes are deployed.
- Full authenticated eight-role acceptance, task create/update/review flows, Olympiad lead write/read/delete verification in a disposable DB, social provider authentication/approval/publishing, AI model integration, Android device/emulator testing, load testing, backup/restore and final visual review are not yet complete.
- The production acceptance script previously reported 21 PASS / 0 FAIL / 1 INFO for service/config/route health; that is not equivalent to full end-user acceptance.
