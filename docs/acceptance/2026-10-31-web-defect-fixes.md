# Web ERP acceptance defect fixes — 31 October 2026

Candidate: `work/web-acceptance-fixes-20261031`

## Defects addressed
- Blank optional numeric inputs in extended operations forms are normalized to their schema defaults before persistence. This prevents explicit NULL values from bypassing database defaults on NOT NULL counters such as inventory quantity.
- Student dashboard acceptance now checks the desktop portal entry point on desktop and the Portal link in the mobile navigation on mobile.
- Operations module-switching acceptance waits for the navigation commit rather than the full load event, avoiding hangs on slow or non-critical page resources.
- Live-site role acceptance now executes on both desktop and mobile projects. Mobile runs authenticate each configured role, verify the dashboard and mobile navigation, check linked navigation routes, and assert no horizontal overflow or server/console errors.

## Regression coverage
- Laravel feature test creates an inventory record with blank quantity and reorder level and asserts both are stored as zero.
- Existing Playwright operations workflow continues to submit blank quantity fields, exercising the production form behavior.
- Live acceptance still requires all eight dedicated role accounts and HTTPS; missing accounts fail the release gate.

## Release policy
This candidate is not production-approved until the full ERP acceptance workflow and an explicitly dispatched live-site acceptance run pass.