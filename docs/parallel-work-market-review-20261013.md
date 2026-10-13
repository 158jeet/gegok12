# Tagore ERP parallel work: market review and integration decisions

Date: 2026-10-13

## Selected free/open-source building blocks

| Need | Candidate | Decision for Tagore |
|---|---|---|
| Browser acceptance and visual checks | [Playwright](https://playwright.dev/) | Adopt as the primary browser test runner. The suite checks eight ERP roles, reachable authenticated pages, console errors, 5xx/404 responses, broken images, visible-text encoding, viewport overflow and dedicated task/platform layouts. |
| Laravel backend checks | [Laravel testing](https://laravel.com/docs/12.x/testing) and PHPUnit/Pest | Keep backend tests in disposable CI/staging. The production runtime image intentionally excludes development test dependencies, so php artisan test is not available inside that image. |
| Social publishing/scheduling | [Postiz self-hosted Compose](https://github.com/gitroomhq/postiz-docker-compose) | Do not deploy the full stack blindly. The upstream stack requires PostgreSQL, Redis and Temporal services plus platform OAuth credentials. Keep ERP drafts, creatives and automation rules native; add Postiz as a separate service only after storage/resource checks and approved social-platform credentials. |
| Olympiad/exam engine | Existing private Exameow-based pilot | Retain as a private pilot; smoke checks have verified the current engine, but public registration, Hindi/English UX, parent consent, lead attribution, result retention and authenticated ERP result sync are still release gates. |
| Work/task management | Existing Tagore task module; compare workflow patterns with self-hosted project-management products | Keep tasks inside ERP so role permissions, institutions, departments and staff records remain consistent. Improve the existing interface rather than creating a disconnected second staff account system. |

## Why we are not deploying every candidate as a separate product

A free licence does not mean zero operating cost. Separate products add another authentication boundary, database, backup/restore path, update cadence, storage usage and security surface. Postiz is a strong candidate for the external publishing layer, but it cannot publish to school accounts until the correct platform developer applications and OAuth secrets are configured. Credentials must be stored in server-side secrets, never committed to Git or exposed in browser code.

## Current implementation

- Added test-only /__e2e/* routes behind both APP_ENV=testing and TAGORE_E2E_ENABLED=true. They are not registered in production mode.
- Changed the visual acceptance harness to collect page-level failures and report after the complete crawl instead of stopping on the first visual defect.
- Added visible-text corruption checks and responsive acceptance tests for the task workspace and Growth & Quality platform panel.
- Redesigned the task workspace with a clear header, action links, summary cards, stronger filter controls, readable table spacing, priority/status chips, progress bars and responsive behavior.
- Added an ERP Growth & Quality panel that links existing social drafts, creative templates, automation rules and admissions follow-up, and clearly labels the Olympiad engine as a private pilot.
- Fixed the Windows acceptance script's route-JSON parsing and changed MySQL/Nginx health checks to use their actual health/exit results.

## Release policy

Do not release based only on HTTP 200 or container health. A release requires backend tests, role-based browser tests, mobile viewport checks, no unexplained console/404/5xx/text-corruption findings, operational checks, backups/rollback readiness and a human visual review. Any remaining failure must be recorded with evidence and an owner.