# Tagore ERP Continuous Feature Execution Board

Baseline: `integration/erp-7day`
Updated: 2026-11-21 (finance #123 CI 3/4 green; promotion #124 UI + E2E added; current-head CI queued)
Policy: free tooling only; no paid coding agents, subscriptions, or external AI services.

## Operating model

This board is the Git-backed source of truth for parallel implementation. Tracks are persistent work queues, not one-time test workflows. When a feature is accepted, the track takes the next eligible feature from the master feature register.

A feature is **complete** only when:
1. Its server-side implementation and UI/API behavior are present.
2. Authorization and institution/family data boundaries are tested.
3. Relevant automated tests pass on the current integration baseline.
4. Acceptance criteria are evidenced in the PR and the feature register is updated.
5. No live data, production deployment, or merge is performed without the Owner's explicit approval.

Before implementation, inspect open PRs and branches for existing work. Reconcile or extend existing work instead of duplicating it. Keep each implementation isolated on a feature branch. Do not run concurrent edits against shared files without coordinating a single owner for those files.

## Tracks and first queue

| Track | Scope | Initial queue (existing PRs first) | Next queue |
|---|---|---|---|
| T1 Owner, identity & security | Owner-only global administration, authentication, institution scope, audit, permissions | #122 Owner impersonation; #120 gate-pass institution validation; reconcile #86/#88 duplicate account-status work and #52 Owner account controls | SEC-001, ORG-001 and remaining Owner access-control gaps |
| T2 Admissions & family | CRM, applications, lead conversion, family linking, promotion | #103 fieldwork; #94 CRM baseline; reconcile #112–#115 family linking, legacy scoping and conversion; #116 promotion | ADM-001/002, FAM-001/002, FAM-003 |
| T3 Fees & finance | Cashier, family dues, partial allocation, checkout, receipts and reconciliation | #104 payment concurrency before dependent #109 partial allocation; #90 fee scope; #93 cashier; #99 family fee acceptance | FIN-001 through FIN-007 |
| T4 Academics & coaching | Diary, homework, exams, Olympiad, JEE/NEET CBT | #95/#97 diary access and audit; #96 Olympiad grade; reconcile #98/#100 validation; #106 CBT foundation | ACA-001 through ACA-003, EXM-001/002, OLY-001, CBT-001 through CBT-005 |
| T5 HR, attendance & campus | Staff profiles, attendance, leave/payroll, biometric, gate/visitor, hostel | #107 HR leave/payroll; #119 current-baseline attendance reconciliation (supersedes stale #110 patch); reconcile #66/#69 biometric registry; #118 hostel scope | HR-001 through HR-004, OPS-001/002 and remaining hostel features |
| T6 Platform, data & delivery | Backup/restore, mobile/offline, runtime, integration and acceptance | #101 backup/restore; #33 Android emulator smoke; #56 production/mobile operations; #27/#21/#22 deployment/runtime; #118 Owner daily assurance | Platform, mobile, offline, backup and end-to-end acceptance gaps |

## Current constraints and dependency rules

- 2026-11-21 branch audit: finance #104 is 9 ahead / 34 behind integration and admissions candidates #112–#116 are 1–22 ahead / 34 behind (with #94/#103 37 behind). Finance concurrency was rebuilt as current-baseline draft PR #123, 4 commits ahead / 2 behind after documentation advancement; exact-head ERP CI run `44875663835` is running (frontend green, three jobs in progress). Student promotion was rebuilt as current-baseline draft PR #124, latest head `afeb8dbcafb2cb7f52cd5ee91274efb50b8193f3`; ERP CI run `44876079994` queued and Windows Pilot Package run `44876080028` running. The older #124 run was cancelled when newer UI/E2E commits superseded it. Neither feature is accepted or merged. Older drafts remain source material, not current-baseline acceptance candidates. Rebuild patches on current integration and rerun CI before considering integration.

- PR #118 corrected audit service/test syntax and passed ERP CI plus full acceptance on feature SHA `01499b14ddf852c8941b835a44d0019bdceb86f3`; remains draft pending current integration-baseline reconciliation.
- PR #110 passed ERP CI on `5066d8a5fa202f60a68bc75b3e92c780966e53b0` (run `44867682497`); branch comparison is now 5 ahead / 21 behind integration, so patch reconciliation is still required.
- PR #111 passed ERP CI on `3aacf9bf78718cc1ee788f5fb9391aad77cb3d8d` (run `44867898661`); branch comparison is now 2 ahead / 21 behind integration, so patch reconciliation is still required.
- Integration Full Acceptance passed on `ff1bc888c792e42765ac3eed5560f9b28e84c9a8` (run `44867964104`).
- PR #107 latest head `d729b84042748edf9a6a055b71692b5f8418fcf2` passed all five ERP CI jobs in run `44871879262` (backend, frontend, browser E2E, production Docker smoke, acceptance gate). Its branch is 7 ahead / 21 behind integration; payroll/staff controller and test changes still need reconciliation and current-baseline acceptance.
- PR #105, #110 and #111 are respectively 16/21, 5/21 and 2/21 ahead/behind integration. Their earlier green feature-head CI is not a substitute for rebasing/reconciling and testing on the current integration baseline.
- Full Acceptance now ignores documentation-only pushes on integration/main; code + docs mixed commits still trigger the release gate.

- #109 is based on #104, so implement/validate the payment-concurrency foundation before replaying and validating partial allocations.
- Admissions #112–#116 and #94 overlap; compare changed files and data model before consolidating.
- Diary/Olympiad #95–#100 contain overlapping implementation and validation branches; do not treat every PR as an independent feature.
- Biometric #66/#69 and Owner account status #86/#88 contain overlap; reconcile before parallel edits.
- Shared hot spots include FeeController, PayrollController, AdministrationController, dashboard views, route files, role matrix, migrations and seeders. Assign one track as the owner of each shared-file change at a time.
- T304F live integration and CCTV activation remain blocked on vendor protocol/approval. Use synthetic/mock tests only until supplied.
- The six tracks are implementation workstreams. GitHub Actions workflows are validation/build jobs, not autonomous feature developers.

## Execution ledger

| Track | Active feature | Branch / PR | State | Evidence / blocker | Next action |
|---|---|---|---|---|---|
| T1 | Owner impersonation security | #122 current-baseline reconciliation; #105 historical source | #122 draft; exact-head ERP CI green | Original CI exposed that nckg middleware uses `onceUsingId()` and `stopImpersonating()` only clears its target session key. Rebuilt the six-file security patch directly on current integration in draft PR #122, head `600beed9375956f19c728e25f52568409ec1b9aa`; all five exact-head ERP CI jobs passed in run `44873655899`. Comparison: 6 ahead / 3 behind; changed files are the impersonation controller, three views, routes and authorization regression test. The old #105 branch is historical source only. | Review route POST/CSRF, tenant boundaries, original-actor restoration and audit behavior; resolve branch drift before integration. Keep draft; no merge |
| T2 | Admissions/family reconciliation | #94, #112–#116 | Existing drafts; stale/diverged; overlapping | Current integration comparison: #112 6/34, #113 1/34, #114 3/34, #115 13/34, #116 22/34 ahead/behind; #94 19/37 and #103 23/37. #112/#115/#94 overlap `AdmissionsController.php` and admission tests; #113/#114 both change legacy `AdmissionController.php`; #116 is a distinct student-lifecycle controller/migration/test slice but still shares routes/seeders. | Rebuild one dependency-ready slice on current integration; do not merge these stale branches directly |
| T3 | Checkout concurrency | #104 then #109 | Existing drafts; dependency; current-baseline rebuild required | Old #104 was 9/34 ahead/behind current integration. Rebuilt its concurrency foundation directly on current integration as draft PR #123 (`work/online-payment-concurrency-current-baseline`, current head `4402e3a7b1067f34155e8a7fc80bb010d75efec1`): reservation locks, gateway failure release, stale-capture quarantine, deterministic tests and FeeReceiptMailer import fix. Exact-head ERP CI run `44875663835`: backend, frontend and production Docker smoke passed; authenticated browser E2E is still running. #109 remains stacked on old #104 and is not independently eligible. | Complete #123 exact-head ERP CI and focused payment tests; only then replay #109 partial-allocation UI/service changes on a branch based on #123. No live gateway use |
| T4 | Diary/Olympiad reconciliation | #95–#100 | Existing drafts; overlapping | Multiple implementation/validation PRs | Consolidate patches and choose one implementation lineage |
| T5 | HR/attendance | #121 HR/payroll; #119 attendance; #66/#69 | #119 and #121 current-baseline ERP CI green | Rebuilt attendance handling on current integration as #119 head `7b4070ea8f7e8e926ce5800abb88c2fc5a7ea0a2`; all five ERP CI jobs passed run `44872902241`. Rebuilt payroll/leave role-scoping, Owner-wide access and transactional leave decisions as #121 head `e9290301eabac00da1231288bcf8a7fd64ea4067`; all five ERP CI jobs passed run `44873432095`. #121 comparison: 4 ahead / 5 behind. #119: 5 ahead / 9 behind. #107/#110 are historical sources only. | Review focused HR/payroll and attendance regressions, reconcile current-base drift, then move to next queued HR feature. Keep drafts; no merge |
| T6 | Backup/restore | #101 `work/automated-backup-integrity` | Existing draft; historical feature CI green, operational rehearsal pending | ERP CI, Windows pilot package, Prototype and Full Acceptance passed on `c2f7db4b7225439d87ad0750705f7c80527e23a8`; actual encrypted offsite + disposable restore still require a controlled test | Audit backup script safety and restore evidence; do not run against production or create a live backup |

## Capacity and free-only policy

- Do not assume six parallel jobs are a GitHub hard limit. Effective concurrency is bounded by account Actions quota, runner availability, runner labels, CPU/RAM/disk, and workflow concurrency groups.
- Prefer existing free GitHub Actions minutes and the existing self-hosted Windows runner. Add no paid runner, agent, SaaS or subscription without explicit Owner approval.
- A single self-hosted runner normally executes one job at a time. Additional runner services/machines may increase capacity only after hardware and isolation checks.
- Keep expensive broad acceptance on integration/release; run targeted suites on feature PRs and use the manual prototype workflow only when needed.

## Update protocol

After each feature:
- update its feature-register status and this ledger;
- record branch, PR, commit SHA, tests and remaining risks;
- immediately assign the next dependency-ready feature to that track;
- preserve draft status until acceptance evidence is complete;
- never merge, deploy, or load live data without Owner approval.
