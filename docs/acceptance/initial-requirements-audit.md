# Tagore ERP — initial requirements and product-quality audit

Audit basis: requirements explicitly discussed in the Tagore ERP project history and implementation evidence available in the application repository. This is a candidate-branch audit, not a production certification. Candidate branch: `admin-acceptance-parallel`.

## Executive decision

**Not release-ready yet.** The candidate has meaningful implementation and automated test coverage, but a successful build or live-site workflow alone is not proof that every operational requirement works in production. Keep production unchanged until the full candidate acceptance gate and required live checks pass.

## Initial requirements traceability

| Requirement from project planning | Evidence in candidate | Assessment / remaining proof |
|---|---|---|
| Parent receives fee receipts by email | `FeeReceiptMailer` generates a PDF, sends it to the linked parent, and records sent/skipped/failed delivery status. Offline payment controller invokes it. | **Implemented with configuration dependency.** Verify real SMTP delivery using a controlled test parent mailbox; confirm missing email and SMTP failure are visible to staff and safely retryable. A successful PHP test does not prove external email delivery. |
| Parents must not see internal transaction history | Student fee controller returns an empty payment-history collection to Parent and Student roles; staff payment records are role-protected. | **Implemented in the inspected path; acceptance required.** Test parent and student sessions against every fee/payment/receipt endpoint, including direct URLs and APIs, not just hidden navigation links. |
| Yearly backups / preservation of historical financial data | Fee vault encrypts year-close payloads, stores an integrity hash and audit event, and a scheduled command checks completed academic years daily. | **Partial.** This is an academic-year fee archive, not proof of a full ERP database/files backup. Add/verify separate full database + uploads backups, retention, off-machine copy, and a restore drill before release. Confirm the scheduler is active on the production host. |
| Only authorised users can edit fee data | Fee editor is limited to Owner/Fee Editor roles; non-owner scope is restricted to assigned institutions. Bulk changes are range-validated and opening balances excluded. | **Implemented in inspected controller/service; verify role matrix and cross-institution requests in CI.** |
| Percentage-range fee adjustment | Fee editor accepts minimum and maximum percentages, records each applied percentage, and records before/after values in `tagore_fee_editor_events`. | **Implemented.** The current behavior deliberately applies an independently generated percentage to each eligible record within the range; the UI explains this. Test rollback on invalid ranges and amounts that would undercut already collected payments. |
| Preserve original transaction/history records separately | Payment ledger, allocations, financial transactions, audit events and fee-editor before/after events are separate records; archived year data is encrypted and hash-checked. | **Implemented in core code; needs adversarial regression tests.** Verify edits never rewrite payment rows or allocations and that archive integrity failures are rejected. |
| Windows application can work offline and sync with server | Device registration, bootstrap, push/pull sync service and sync database tables exist. | **API foundation exists; end-to-end acceptance is not complete.** Test duplicate/out-of-order changes, reconnect, permission changes, conflict handling, device revocation and data-loss recovery on the actual Windows client. Windows packaging success is not proof of offline parity. |
| Android parent and teacher applications | Parent/teacher mobile submodules and API endpoints exist in the repository. | **Deferred by current project priority.** Do not count as accepted until the web ERP is accepted; then run device-level login, fee privacy, notifications, attendance, offline/network failure and upgrade tests. |
| Offline sync should not create duplicate or cross-school records | Sync service scopes devices to authenticated users and school; API exposes registration/bootstrap/push paths. | **Requires stronger operational evidence.** Acceptance should include idempotency, cross-school isolation and recovery tests against a disposable MySQL environment. |
| Easy-to-use, attractive interface | Dashboard has a consistent dark/charcoal surface, green accents, KPI cards, role-aware navigation and search. Accounts screen was redesigned to use the same design system, responsive table scrollers and family/payment filters. | **Improved, not yet globally certified.** Continue a page-by-page visual/interaction pass for all authenticated roles, empty/error states, forms, tables, accessibility, keyboard focus, and mobile widths. Legacy screens may still use different layouts. |
| Dashboard/module search and fast navigation | Dashboard search filters visible dashboard modules/items. Accounts page now has page-wide search and separate family/payment table filters. | **Partially implemented per page.** This is not yet a universal ERP search across all records/modules. Do not advertise it as global search. |
| No live release until acceptance is complete | Candidate changes are held in PR #46 and are not deployed by these code changes. | **Required release gate.** Merge/deploy only after all blocking workflows pass and the deployed site is separately verified. |

## UI benchmark principles

The candidate was compared against publicly described patterns from established products, not by copying their branding:

- **Fedena:** role-specific dashboards and configurable dashboard widgets, plus at-a-glance operational updates. Tagore should keep role-based visibility and put actionable work first.
- **Zoho Books:** clear sidebar navigation, recent history, quick-create actions, alerts, readable contrast and adaptive layouts. Tagore should use these patterns for accounts and finance.
- **Indian school ERP expectations:** broad school workflows, parent/teacher accessibility, multi-campus scope, and clear finance/audit trails. Tagore should prioritize daily tasks and readable Indian-currency finance summaries over decorative density.

Sources: Fedena dashboard overview https://fedena.com/feature-tour/customizable-dashboard ; Fedena feature tour https://fedena.com/feature-tour ; Zoho Books navigation https://www.zoho.com/ca/books/help/getting-started/navigating-zoho-books.html ; Zoho Books refreshed UI guidance https://help.zoho.com/portal/en-gb/community/topic/introducing-a-refreshed-design-to-enhance-your-user-experience-in-zoho-books .

## Required release-blocking checks

1. Candidate backend tests, frontend build, production image build and authenticated Playwright suite all pass on the **same exact commit**.
2. Candidate full acceptance convergence passes with no required browser tests skipped or flaky.
3. Role matrix runs against the candidate commit (not only `main`) and verifies direct URL/API authorization for all eight roles.
4. Fee workflow acceptance covers payment allocation, ledger totals, receipts, parent privacy, overpayment rollback, fee-editor audit history, archive integrity and cross-institution isolation.
5. Visual acceptance covers desktop and mobile for owner, principal, coordinator, teacher, accounts, fee editor, parent and student; no horizontal page overflow, broken images, console errors or dead primary actions.
6. Production HTTPS smoke is supplemented by a real authenticated live-site test against the intended deployed version. A workflow that skips the live browser job is not production acceptance.
7. Full database/files backup and restore are demonstrated separately from fee-year archive.
8. Only after web acceptance is complete should Windows offline-sync and Android parent/teacher acceptance become the active release focus.

## Current UI changes in this candidate

- Reworked the Accounts screen into the shared Tagore ERP shell and dark/green design system.
- Added responsive table containers, family-level fee summary, settlement status badges, explicit empty states and family/payment filters.
- Added browser acceptance coverage for Accounts search, mobile layout and parent denial.
- Made cross-institution fee-editor test data self-contained so the authorization test does not depend on the seed database accidentally having multiple institutions.

This document is a living audit. Mark a requirement accepted only after the corresponding test or operational evidence is attached to the same candidate commit.
