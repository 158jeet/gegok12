# Tagore ERP Parallel Execution Board

## Mission
Complete the multi-institution ERP rapidly through bounded, independently testable workstreams. Integrate only after evidence-backed acceptance. Do not use live institutional data during development.

## Boundaries
- Active development checkout: G:\Projects\TagoreERP only.
- Preserve dirty and untracked work; no destructive Git cleanup or blanket checkout.
- ERP source changes belong in the ERP repository; private operations repository is orchestration only.
- Do not deploy or merge unverified work to production.
- Every workstream adds automated tests and records verified corrections in docs/release/deployment-correction-index.md.

## Verified baseline (2026-11-23)
- Laravel 12 / PHP 8.4; Vue 3 frontend.
- 7,847 tracked files; 275 controllers; 123 models; 144 migrations.
- 20 feature tests, 2 unit tests, and 2 Playwright E2E specs observed.
- G-drive branch dev/server-workspace-2026-10-09 at 39557dc (2026-10-08).
- Deep inventory succeeded: tagore-erp-deep-inventory-20261123-08, runner exit code 0.
- Worktree has 283 status entries; preserve all current work.
- Olympiad and parent-Olympiad controllers, migrations, views and feature tests exist as untracked work and require integration/regression verification.
- PR #153 is open and draft; frontend, backend and Docker smoke passed; authenticated browser E2E remains in progress. Do not merge yet.

## Workstreams and acceptance
| Workstream | Scope | Acceptance |
|---|---|---|
| A. Identity/access | Sole Owner admin; Accounts, Cashier, Principal, Coordinator, Teacher, Parent, Student, Fee Editor; institution/department scoping | Role matrix, cross-scope denial, recovery, session/logout tests |
| B. Admissions/family | Lead-to-admission, family links, siblings, promotion, parent mapping | Lifecycle, duplicate, sibling visibility and promotion tests |
| C. Fees/accounts | Fee plans, installments, partial payments, wallets, receipts, reconciliation, alerts | Ledger invariants, multi-child allocation, duplicate webhook safety, receipt tests |
| D. Academics | Timetable, attendance, diary/homework, exams, NEP/HPC reports | Role-specific CRUD, academic-year/section scope, export tests |
| E. HR/payroll | Staff, biometric imports, leave, substitution, payroll | Idempotent imports, approval segregation, calculations and audit tests |
| F. Parent/student | Multi-child dashboard, notifications, diary, homework, results, payments | Authenticated mobile/desktop browser flows |
| G. Hostel/transport/security | Hostel, routes/GPS/boarding, visitor and gate pass | Check-in/out, permission, audit and integration tests |
| H. Coaching/Olympiad | JEE/NEET engine, Q-bank, mock player, Olympiad registration/exam/results | Attempt security, scoring/rank correctness, consent and E2E tests |
| I. Platform/operations | Inventory, communications, documents, tasks, KPIs, reports | CRUD permissions, audit, export and dashboard acceptance |
| J. Release engineering | Windows app, Android apps, offline sync, backup/restore, deployment | Build/signing, sync conflict, restore drill, security and release gates |

## Execution protocol
1. Inspect existing implementation and dirty worktree before editing.
2. Give parallel tasks disjoint file ownership; avoid concurrent edits to shared routes, migrations, lockfiles or generated assets.
3. Implement one vertical slice at a time: UI, route, authorization/validation, service/model, migration if required, and tests.
4. Run focused tests first, then full backend suite and browser E2E after integration.
5. Record evidence and corrections; regenerate bundles only after source changes are accepted.
6. Integrate without discarding G-drive work; verify exact diff and rollback path.

## Immediate next action
Run focused Olympiad and parent-Olympiad feature tests on the G-drive checkout, inspect failures, and repair only relevant files. Then run the complete backend suite. Keep PR #153 browser E2E under observation; do not merge on partial CI success.