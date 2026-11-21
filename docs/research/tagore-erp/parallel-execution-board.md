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


## Execution update — 2026-11-21 (current repository verification)

- T1 security: PRs #119 attendance, #120 gate-pass institution isolation, #121 HR/payroll scope and #122 Owner impersonation each have all five exact-head ERP CI jobs successful. They remain drafts and require integration-level functional review.
- T2 admissions: PR #126 latest head `a4e752ecbd81e82c38aa4cf86cde205dd0c6e77e` has all five exact-head ERP CI jobs successful. PR #128's newest test head has been corrected after the earlier backend failure; the latest head is `6c8db9b4d5c1faaf54cc656dc09e45d92550422e`, but no exact-head checks are currently published. Do not treat #128 as accepted until its test suite runs on that exact SHA and the synthetic second-school HTTP fixture passes.
- T3 finance: PR #123 concurrency and PR #125 partial allocation each have all five exact-head ERP CI jobs successful. #125 remains dependent on #123 and must be reviewed together for checkout reservation/partial allocation behavior.
- T4 academics: PR #124 promotion and PR #127 Diary/Olympiad each have all five exact-head ERP CI jobs successful. #124 also has a successful Windows pilot package. Browser/role workflows still require integration review.
- T5 HR/campus: #119 and #121 exact-head CI is green; functional leave/payroll/attendance workflows and current-base reconciliation remain required.
- T6 platform: new current-baseline PR #129 rebuilds encrypted backup and disposable MySQL restore verification directly from `integration/erp-7day`; latest PR #129 branch head (see PR metadata). It replaces stale PR #101 as the current integration candidate. Exact-head CI, PowerShell validation and a synthetic disposable restore rehearsal remain pending.
- The connector-created commits for #128 and #129 currently have no associated GitHub Actions check runs. Do not infer success from earlier SHAs. Obtain exact-head workflow execution before accepting either.
- No PR was merged. No deployment, production backup, live data, payment gateway, messaging or hardware activation was performed.


## Execution update — 2026-11-21 (next current-baseline slices)

- T4 CBT: PR #130 `work/cbt-engine-current-baseline`, head `96b023df9ad383b3818251cacdbbc4b76ce4ef5f`, is a 12-commit, zero-behind rebuild of the historical JEE/NEET CBT engine. It adds nine previously absent feature/test files and applies a focused 20-route registration patch to the current route file. Candidate attempt reads/writes now join the exam and enforce the student's active institution assignments. Exact-head CI and MySQL migration smoke are pending; historical PR #106 green runs are not current-head evidence.
- T6 backup: PR #129 current head is `d4ecb317a32f0250032096eb0d65d537883acca6`; includes the backup scripts, restore verifier, runbook, execution-board update and correction-index entry. Exact-head CI and controlled disposable restore remain pending.
- T2 legacy admissions: PR #128 latest head `6c8db9b4d5c1faaf54cc656dc09e45d92550422e`; corrected the previously failing literal-variable assertion. No checks exist on this exact head.
- Tool limitation affecting validation: connector-authored branch commits have not produced Actions check runs for #128, #129 or #130 (check-run count is zero on each latest head). Do not infer a pass. The latest exact-head test evidence remains the earlier green PRs #119–#127; these new heads require an Actions run before acceptance.
- No merge or production action has been taken.


## Admissions rebuild note — 2026-11-21

- The earlier PR #128 is superseded by clean current-baseline PR #131. PR #131 contains only the legacy admission controller correction and focused regression test, rebuilt from current integration with no behind commits.
- PR #131 head `e9aa07f7435b1966285b4b60a4f4944d5fc2ec36`; exact-head ERP CI run `44883700217` is in progress. Do not use #128's earlier green controller-only CI or failed intermediate test run as acceptance evidence.
- Keep #131 draft until all five ERP CI jobs pass on its exact head and the synthetic second-school HTTP fixture is confirmed in backend output.
