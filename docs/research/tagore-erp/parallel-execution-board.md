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
| T1 | Owner / security hardening | #122 impersonation; #120 gate-pass tenant isolation | Both current-baseline drafts; all five exact-head ERP CI jobs green | #122 head `600beed9375956f19c728e25f52568409ec1b9aa`, run `44873655899` green. #120 head `fbfe6b9cc2ea0d13e6d8f76032574e10a110556f`, run `44873348882` green. Both remain drafts; #122 branch compare needs normal drift reconciliation before integration. | Continue security review and queue next access-control feature; no merge |
| T2 | Admissions / family / conversion | #126 admitted-lead conversion + verified family linking; #128 legacy admission tenant isolation | #126 added ambiguous-parent test; awaiting exact-head rerun. #128 ERP CI run queued/running | #126 updated head `a4e752ecbd81e82c38aa4cf86cde205dd0c6e77e`: first exact-head run passed all five jobs, then added a deterministic ambiguous verified-mobile rejection test; new exact-head run not yet surfaced. #128 head `1f1c2e9010e84a098adf698e1810690851b0cc7b`, run `44878442906` running. #128 rebuilds historical #114 directly on current baseline; source was 3 ahead / 49 behind and controller matched merge-base exactly. Adds school scope for list/show/edit/update/delete, section options and generic error handling. | Verify new #126 exact-head CI; add deterministic tests for #128 before acceptance; no merge |
| T3 | Checkout concurrency / partial allocation | #123 validated foundation; #125 stacked parent-selected partial allocations | #123 all five exact-head jobs green; #125 backend/frontend/Docker green, browser E2E running | #123 head `4402e3a7b1067f34155e8a7fc80bb010d75efec1`, run `44875663835` all five success. #125 head `9741e99f21591ab7bd356fac005ac13849651751`, run `44877160843`: backend, frontend and production Docker smoke success; browser E2E in progress. #125 includes #123 dependency diff until #123 is integrated. | Finish #125 browser E2E and review partial allocation UI/service; no live gateway or merge |
| T4 | Academics / diary / Olympiad | #124 student promotion; #127 Daily Diary scope + audit | #124 exact-head ERP CI and Windows Pilot green; #127 frontend green, backend/browser/Docker running | #124 head `9cd09eda3c0c97a4debce2c6b42fdc83352f9d0a`, run `44876972392`: all five ERP CI jobs success; Windows Pilot Package run `44876972367` success. #127 head `5cb8847b7903ddb1ba18a7c5d5bcc7c9346cfb16`, run `44877552307`: frontend passed; backend, browser E2E and production Docker smoke running. | Finish #127 exact-head validation; then continue to next exam/diary workflow. Keep both drafts |
| T5 | HR / attendance | #119 attendance; #121 HR/payroll | Current-baseline drafts; all five exact-head ERP CI jobs green | #119 head `7b4070ea8f7e8e926ce5800abb88c2fc5a7ea0a2`, run `44872902241` all five success. #121 head `e9290301eabac00da1231288bcf8a7fd64ea4067`, run `44873432095` all five success. #107/#110 historical sources only. | Review functional role-scoped workflows and reconcile branch drift before integration; then queue next HR feature. Keep drafts |
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


## Latest execution update — 2026-11-21 12:17 IST

- Finance #123 exact-head ERP CI completed all five jobs successfully; #125 partial-fee allocation is stacked on #123 and its exact-head ERP CI is run `44877160843` (in progress).
- Academics #124 corrected head `9cd09eda3c0c97a4debce2c6b42fdc83352f9d0a`: backend and frontend passed in run `44876972392`; browser E2E and production Docker smoke were still running at the last check. Windows Pilot Package run `44876972367` was also running.
- Security/HR drafts #119–#122 have all five exact-head ERP CI jobs green. Green CI is not functional acceptance and does not authorize merge/deployment.


## Execution update — 2026-11-21 12:20 IST

- T2 admissions: PR #126 exact-head ERP CI run `44877363759` started on head `522f4c76e69aaed0720d22cae7b2d4739436bf85`.
- T3 finance: PR #123 exact-head CI run `44875663835` all five jobs success; PR #125 exact-head run `44877160843` backend/frontend passed while browser E2E and Docker smoke were running.
- T4 academics: PR #124 corrected head `9cd09eda3c0c97a4debce2c6b42fdc83352f9d0a`; backend/frontend/Docker smoke passed, browser E2E remained in progress; Windows Pilot Package run `44876972367` passed.


## Execution update — 2026-11-21

- #125 exact-head run `44877160843`: frontend, backend, production Docker smoke, browser E2E and release acceptance gate all success.
- #126 first exact-head run `44877363759`: all five jobs success. Added explicit duplicate verified-parent-mobile regression on new head `a4e752ecbd81e82c38aa4cf86cde205dd0c6e77e`; fresh exact-head run has not appeared yet and is required before acceptance.
- #127 exact-head run `44877552307`: all five jobs success.
- #128 current head `1f1c2e9010e84a098adf698e1810690851b0cc7b`, ERP CI run `44878442906` in progress. Dedicated functional regression tests remain a release-blocking gap.


## Execution update — 2026-11-21 (latest repository reconciliation)

Integration baseline verified: `integration/erp-7day` = `af308809e43e6e36b3b5cf8dc62752719379a330`. PRs #129–#132 were opened against this exact baseline. No feature PR has been merged; no production/live data touched.

| Track | Current work | Exact-head evidence | State / next action |
|---|---|---|---|
| T1 Owner, identity & security | #120 gate-pass institution isolation; #122 Owner impersonation | #120 run `44873348882` success; #122 run `44873655899` success (all five ERP CI jobs) | Keep drafts; complete role/tenant workflow review and current-integration acceptance. |
| T2 Admissions & family | #126 admitted-lead conversion + verified family linking; #131 legacy admission isolation | #126 run `44878488189` success; #131 run `44883714444` success (all five ERP CI jobs). #128 was closed unmerged and superseded by #131. | Review duplicate/ambiguous family behavior and the synthetic second-school admission test; then queue remaining CRM/family lifecycle gaps. |
| T3 Fees & finance | #123 checkout concurrency; #125 parent-selected partial fee allocations | #123 run `44875663835` success; #125 run `44877160843` success (all five ERP CI jobs) | #125 depends on #123. Review allocation ledger/idempotency and reconcile both as one finance slice; no live gateway. |
| T4 Academics & coaching | #124 student promotion; #127 Daily Diary/audit/Olympiad; #130 JEE/NEET CBT engine | #124 run `44876972392` success + Windows Pilot Package `44876972367` success; #127 run `44877552307` success; #130 run `44883870085` success (all five ERP CI jobs) | Review role-based journeys and CBT exam lifecycle (question bank, mock, scoring, analytics); then queue next assessment feature. |
| T5 HR, attendance & campus | #119 attendance safe failure handling; #121 HR/payroll institution scope | #119 run `44872902241` success; #121 run `44873432095` success (all five ERP CI jobs) | Review attendance and manager/Owner payroll boundaries; then queue leave/substitution and staff profile gaps. |
| T6 Platform, data & delivery | #129 encrypted backup/restore; #132 Owner assurance/hostel foundation | #129 ERP CI run `44884350353` and Windows Pilot run `44884350403` in progress; #132 ERP CI run `44884210186` in progress. #129 backend/frontend passed so far; browser E2E and Docker smoke still running. #132 frontend/backend/Docker passed so far; browser E2E still running. | Poll exact-head runs; inspect backup/restore safety and Owner assurance evidence. No production restore, backup, deployment or live data. |

### Superseded / stale queue notes
- PR #128 was closed unmerged; PR #131 is the active replacement. Do not validate or merge #128.
- Historical PRs #101–#118 remain source material unless explicitly rebuilt/current-baseline validated; the newer current-baseline PRs above are the active candidates.
- CI success is necessary but not sufficient: each feature still needs code review, role/institution acceptance, and combined integration testing.
- Current baseline for the newer #129–#132 PRs is `af308809e43e6e36b3b5cf8dc62752719379a330`; keep any subsequent PR-base drift visible and reconcile before release.


## Execution update — 2026-11-21 (validation refresh and cashier track)

- PR #129 exact-head ERP CI run `44884350353` completed successfully across backend, frontend, browser E2E, production Docker smoke and release acceptance gate. Windows Pilot Package run `44884350403` also passed. This is build/CI validation only; the isolated disposable restore rehearsal and offsite recovery proof remain mandatory.
- PR #132 exact-head ERP CI run `44884210186` completed successfully across all five ERP CI jobs. It remains a foundation slice; Owner daily assurance and hostel workflows need role-based functional acceptance.
- PR #130 CBT scope review found that management institution assignments were checked for active status but not effective start/end dates. Added both date predicates and a regression assertion. New exact-head ERP CI run `44884930247` is in progress on head `a099fab90f12349c877ce8d5281395af1961b04d`; earlier green run `44883870085` does not validate this fix.
- **T3 new queue item — PR #133 Cashier:** branch `work/cashier-role-institution-fee-operations`, head `d2af80013d5a670c450182e19a0efa2a5b609e03`. Adds a dedicated least-privilege CASHIER role and synthetic demo account, institution-scoped fee account/statement and offline collection access, denies reconciliation and fee-master editing, and applies effective dates to FeeController role/institution checks. ERP CI run `44884866775` and Windows Pilot Package run `44884866727` are in progress. Backend, frontend and cross-institution negative tests must pass before acceptance.
- PR #133 explicitly does not close the separate Teacher/Coordinator class/grade fee-visibility gap (AUD-002), nor Cashier admissions/promotions workflows. Those remain on the P0/P1 backlog.
- Integration docs were updated on this branch after the PR #129–#132 snapshot. These are documentation-only commits; feature PRs remain drafts and no merge/deployment/live data occurred.


## Execution update — 2026-11-21 (finance and CBT acceptance refresh)

- PR #130 CBT engine: corrected current-baseline head `a099fab90f12349c877ce8d5281395af1961b04d` passed all five exact-head ERP CI jobs in run `44884930247`. The earlier assertion-only failure is fixed. CBT-002 randomisation is not implemented in this slice and remains open; paper sections, marks, timers and negative marking are present. Do not mark the entire CBT blueprint requirement complete.
- PR #133 Cashier: exact-head head `a80c67b4a07f6f8566e6c8b5f86e4c9a3cdfb41b` passed all five ERP CI jobs in run `44885119214`; Windows Pilot Package run `44885119208` passed. Cashier role and negative authorization tests are CI-green. Functional review and Owner role-assignment UX acceptance remain pending.
- PR #134 academic fee visibility: branch `work/teacher-coordinator-fee-class-scope`, current head `a549c751dac076b5d41a4e8f06f774b217271c94`. It includes the #133 prerequisite and adds teacher class / coordinator exact standard-link scoping for statements and account aggregates, hides sibling family balances and settlement controls, and adds positive/negative tests. Exact-head ERP CI run `44885549135` and Windows Pilot Package run `44885549143` are in progress. Do not accept until all five ERP CI jobs and browser acceptance pass.
- PR #129 backup tooling and #132 Owner assurance foundation remain CI-green; restore rehearsal and operational Owner/hostel acceptance are still open.
- Feature gaps CSV was refreshed: AUD-001 Cashier implementation in #133, AUD-002 class/grade scope in #134, CBT-001/003/004 in #130, CBT-002 partial because randomisation is absent, PLT-001 restore pending, OWN-001 foundation only.
