# Tagore ERP — Initial Requirements and Product-Quality Review

Date: 2026-10-29  
Candidate branch: `admin-acceptance-parallel`  
Release status: **Not approved for production**. This review records source-level evidence and known gaps; passing CI on a candidate is not proof that the deployed site is ready.

## Design benchmarks

The following official product pages were reviewed for patterns, not copied branding:

- Entab One describes role-specific management, staff, and parent/student experiences, a central operational dashboard, fee collection, reports, and mobile access: https://www.entab.in/entab-one.html
- Fedena documents role-specific dashboards and broad academic/administrative modules: https://fedena.com/feature-tour
- Fedena's fee workflow includes fee structures, receipts, defaulter reports, and financial reporting: https://fedena.com/feature-tour/school-fees-management-system
- Edutris promotes a role-specific "what needs attention" workflow with one next action per item: https://www.edutris.com/
- ClassNet emphasizes unified student records, fees, attendance, communication, results, and per-school data isolation: https://classnet.in/

Tagore's UI direction should be: one coherent ERP shell, role-appropriate navigation, actionable dashboards, readable data tables, explicit empty/error states, responsive layouts, and auditability for financial changes. Do not treat marketing claims on vendor sites as independent proof of quality.

## Initial requirements matrix

| Requirement | Current source evidence | Status / remaining proof |
|---|---|---|
| Role-specific access for owner, principal, coordinator, teacher, parent, student, accounts, and fee editor | Role matrix feature test and route-level authorization checks; fee-management route denies Coordinator and fee editor | Candidate CI and authenticated browser results must pass for exact SHA; test live accounts separately |
| Parent fee view should not expose internal transaction history | `FeeController::student` returns an empty payment collection to Parent/Student roles while still showing fee obligations and balances | Verify parent screens and every alternate payment/history endpoint in authenticated browser and API tests |
| Email fee receipts to parents | `FeeReceiptMailer` creates a PDF receipt, sends email through configured mail transport, and records delivery status | SMTP/profile configuration, queue/runtime delivery, bounce/failure handling, and a real non-production email test remain required |
| Percentage-range fee adjustment | `FeeEditorService::bulkAdjust` accepts a bounded percentage range and logs old/new values per obligation | Recent change also updates line items and rejects a decrease below amounts already paid; test must pass on the exact candidate SHA |
| Original fee history kept separately from edits | Fee-year archive service encrypts the closed-year payload, stores a SHA-256 integrity hash, and records a vault/audit event; opening balances are excluded from bulk adjustment | Verify owner-only access, archive/restore readability, retention, and reconciliation against the live ledger |
| Annual/year-end fee archive | `ArchiveClosedTagoreFeeYears` can archive closed academic years | Confirm scheduler registration and successful run; this is not a full-system disaster-recovery backup |
| Full database and uploaded-file backup, with off-machine copy and restore drill | Production deployment README explicitly says to establish daily off-machine backups; no verified full-system backup/restore acceptance evidence is recorded here | **Open release blocker** until automated backup, off-machine retention, integrity checks, and a documented restore rehearsal are proven |
| Editing controlled by separate user access | Fee Editor role and dedicated fee-editor routes exist; owner-only vault access is separately guarded | Verify all edit endpoints, bulk actions, cross-institution scopes, and audit events through negative tests |
| Original payment/financial transactions preserved | Payments are recorded with separate financial ledger entries and audit events; overpayment is rejected transactionally | Verify no ordinary role can alter/delete prior payment rows; test refunds/reversals and ledger reconciliation before release |
| Search and day-to-day navigation | Dashboard module/item search exists; recent UI update adds result/no-result feedback, Escape-to-clear, and Ctrl+/ focus shortcut | Browser tests are queued for the changed candidate; review screenshots at desktop and mobile widths |
| Responsive UI and usable forms/tables | Shared Tagore stylesheet includes responsive layouts, visible keyboard focus, table overflow handling, and mobile navigation | Automated overflow/console/asset checks plus visual review of representative role pages still required |
| Web ERP complete before Windows/Android acceptance | Web acceptance is the current priority; client checks remain separate | Do not call overall project complete until all required web gates pass; only then resume Windows/Android acceptance |
| Live production behavior matches candidate | Live acceptance workflow can run read-only browser checks, but latest inspected runs skipped the live browser job due missing live-account configuration | **Open release blocker**: configure dedicated synthetic live test accounts and run the live browser gate; do not deploy candidate merely to make the gate pass |
| AI, CCTV analytics, social publishing, and other provider integrations | Some screens deliberately show unconfigured/disabled status; internal social drafts are not external publishing | Treat as not operational until provider credentials, approvals, and end-to-end acceptance tests exist. Do not represent a placeholder as a working integration |

## Changes made during this review

1. Dashboard search now filters role-visible sidebar links as well as modules, KPIs, and list items.
2. Search provides an accessible live result count/no-results message; Escape clears the query and Ctrl+/ focuses the search.
3. Added browser assertions for search feedback and keyboard controls across all eight role fixtures.
4. Fixed fee percentage adjustments to keep obligation totals aligned with fee line items and to reject changes that would reduce a payable below amounts already collected.
5. Fixed payment allocation so payments are distributed to line items when the line-item outstanding total agrees with the parent obligation, preserving line-item balances. Legacy mismatches are not silently redistributed.
6. Added regression coverage for partial payment, overpayment rollback, line-item balances, and fee-editor reductions below collected amounts.

## Release gate

The candidate is not release-ready until the exact current SHA has all required backend, authenticated browser, production packaging, route/role, responsive/visual, and live-site gates passing; the full-system backup/restore gap is closed; and the owner has reviewed the remaining provider-integration scope. A skipped job is not a pass. No production deployment is authorized by this review.
