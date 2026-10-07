# Human-Style E2E Release Standard

Every authenticated software project must include a reusable browser acceptance gate.

## Required coverage

- Every supported authenticated role.
- Every authenticated GET screen without unresolved route parameters.
- Visible navigation must be exercised by real browser clicks.
- Reachable screens must be rendered and health-checked.
- Desktop and mobile viewport coverage.
- HTTP 5xx detection.
- Browser console error detection.
- Broken image detection.
- Horizontal overflow detection.
- Direct unauthorized-access checks for protected administration.
- Disposable test data/database for destructive or state-changing E2E.
- Production smoke tests remain read-only.

## Authentication rule

Browser automation must never type or submit real credentials. Test sessions are created server-side only in the disposable testing environment, producing normal Laravel sessions that Playwright consumes.

The E2E session routes must never exist in production.

## Release rule

The browser E2E job is a release gate. A testing/release workflow must wait for the ERP CI workflow for the exact commit and refuse to publish when ERP CI is not successful.

## Future modules

When a new module is added, its authenticated GET routes and visible navigation automatically enter the route-manifest crawl. Module-specific workflows should add focused assertions for forms, approvals, data mutations, permissions and business rules.

This standard is intended to be reused for every future Tagore software project.

## Production safety

E2E authentication requires both `APP_ENV=testing` and `TAGORE_E2E_ENABLED=true`. Production must keep `TAGORE_E2E_ENABLED=false` or unset. The CI workflow is the only environment that explicitly enables it.
