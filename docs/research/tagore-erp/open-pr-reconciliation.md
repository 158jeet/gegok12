# ERP Delivery Backlog Reconciliation

**Snapshot date:** 2026-11-20  
**Repository:** 158jeet/gegok12  
**Purpose:** Prevent parallel workstreams from becoming an unreviewed collection of abandoned or conflicting changes. This is a programme-control document, not an implementation authorisation.

## Current repository state

A live GitHub API snapshot found **56 open pull requests: 46 drafts and 10 non-drafts**. Of these, **29 target `integration/erp-7day`**, 18 target `main`, and the remainder target several different intermediate branches. This is not a single coherent release train.

PR #118 (Owner command centre / daily assurance / hostel scope) remains open, draft, unmerged, based on `integration/erp-7day`, at head `a8f4f5bbe4c821b50e331bfcffad51420d12041a`. All six push/PR CI runs for that SHA completed with failure. The implementation slice in that PR is therefore **not accepted** and must not be described as complete. Its requirements and research documents remain useful.

## Immediate delivery risks visible from the open-PR inventory

1. **Multiple bases:** PRs are spread across `main`, `integration/erp-7day`, and intermediate feature branches. A PR against an intermediate branch is not independently releasable until its parent chain is understood.
2. **Overlapping admissions changes:** PRs #113, #114 and #115 all touch admission/student conversion or institution scoping; PR #94 is an admissions port onto the integration baseline. They need a single dependency-ordered review, not independent assumptions of completion.
3. **Payment dependency chain:** PR #104 is payment reservation/settlement hardening; PR #109 targets #104's branch. #99 is a multi-child fee acceptance test. Review as one ordered family-fee/payment workstream.
4. **Diary/Olympiad cluster:** PRs #95, #97, #98 and #100 are related family/class access, diary audit history and Olympiad validation changes. Resolve dependencies and duplicate validation before acceptance.
5. **Repeated/legacy variants:** The open inventory includes multiple older variants for role boundaries, identity, T304F, UI foundation, market audit and execution orchestration. A title alone cannot establish whether a branch is superseded or safe to close.
6. **Integration branch saturation:** 29 open PRs target `integration/erp-7day`. Running or reviewing each independently without a merge order risks repeated CI, stale bases, conflicts and cancelled workflows.

## Required next phase — reconcile before coding

For every open PR, create a ledger row with:

- PR number, title, head branch/SHA and base branch/SHA
- changed files and feature IDs affected
- dependency/parent PRs
- overlap/conflict group
- latest CI conclusion and failing job summary
- status: keep / retarget / rebase / superseded / duplicate / blocked / ready for owner review
- required action and owner approval gate

Then perform the following in order:

1. **Preserve:** do not delete or close any branch/PR solely from its title. Retain all work until its diff and dependencies are inspected.
2. **Group:** classify PRs into identity/security, fees/payments, admissions/student lifecycle, academics/diary/exams, HR/attendance, platform/backup, UI/mobile, and research/scope.
3. **Compare:** inspect diffs and commit ancestry within each group; identify duplicated patches, dependent branches and incompatible base commits.
4. **Select canonical work:** nominate one canonical PR/branch per coherent feature; port only missing unique changes from variants.
5. **Sequence:** establish a dependency graph and a single integration queue. Do not run broad parallel implementation on dependent features.
6. **Verify:** require CI and focused regression evidence on the final canonical branch; cancelled workflows are neither passes nor failures and must be rerun only after the branch is stable.
7. **Owner gate:** keep PRs draft until their scope, dependencies, tests and acceptance evidence are clear. No merge or deployment without explicit owner approval.

## Scope register status

The structured `feature-gaps.csv` currently contains **168 traceable feature rows**, including the 27 recent assurance/hostel requirements (AUD-097–AUD-108 and HOST-001–HOST-015). The count is a row count, not a completion count. Preliminary statuses are not equivalent to tested acceptance.

The roadmap already defines the phase sequence and the Phase 0 exit checklist. The next discovery deliverable is to reconcile this 168-row register against the actual open PR inventory and repository evidence so each feature has one of: verified-present, partial, not-found, unknown, blocked-by-dependency, or accepted. Do not create duplicate feature IDs for work already represented.

## Explicit guardrails

- Requirements and scope changes are documented first in `feature-gaps.csv` and the relevant specification.
- This document records programme state only; it adds no application code and does not approve implementation.
- Do not start a new feature branch while the PR reconciliation is incomplete, except a narrowly scoped critical security/data-integrity correction approved by the Owner.
- Do not merge or deploy. Do not use live student, parent, staff or financial data during acceptance; use synthetic fixtures.


## Phase-one PR reconciliation findings — 20 November 2026

### Candidate health: PR #118

All six workflows triggered by head `2c9c6b0234255ee10deec92ab084e2ac4df3ec36` completed as failures. The primary blocker is PHP syntax validation at `app/Services/Tagore/OperationalAuditService.php:5`: `unexpected namespaced name "Carbon\\CarbonInterface"`. Backend tests and subsequent full-acceptance steps are skipped after this failure. Frontend build, browser E2E, production Docker smoke, and MySQL migration smoke passed; these do not override the syntax blocker or establish full acceptance. PR #118 remains open/draft/unmerged. Do not expand implementation on this branch until the blocker is separately authorised and fixed.

### Overlap and dependency groups

| PR(s) | Evidence | Provisional disposition |
|---|---|---|
| #116 | Adds StudentLifecycleController, lifecycle migration, routes, seeder adjustment and promotion authorization/idempotency tests. | Keep as a distinct candidate; verify converted-student identity and academic-year boundaries. |
| #115 + #112 | Both modify Tagore AdmissionsController and TagoreAdmissionsTest. #115 creates student records/credentials; #112 hardens verified cross-institution family linking and preserves the invariant that admitted status alone is not conversion. | One coordinated admissions/family-link integration group; reconcile shared controller/test hunks first. |
| #113 + #114 | Both modify Admin/AdmissionController. #113 is a one-commit scope change; #114 is a three-commit hardening variant with additional error/404 behavior. | Overlap confirmed; compare full final diff and tests, choose canonical implementation only after equivalence review. |
| #94 + #115 | #94 changes Tagore CRM lead workflow/model/views/routes; #115 adds conversion in the same domain. #94 is based on older baseline. | Complementary, not duplicates; reconcile model/schema/controller and migration sequencing. |
| #104 → #109 | Both change OnlinePaymentService, controller, view and tests. #109 base SHA is 9c28ffa09ee4f5409b0394197d9ffb8024816ffa, a commit in #104's history. | Explicit dependency: payment reservation/settlement hardening first (#104), then replay/rebase partial allocations (#109) onto the final #104 result. |
| #95 / #97 / #98 / #100 / #96 | #95/#98/#100 share DailyDiaryController and class-scope test; #97/#100 include diary audit work; #96's Olympiad grade fix is repeated in #98 and #100. #100 has the broadest visible diary/audit/view/Olympiad file set. | One overlapping diary/Olympiad cluster. #100 is only an apparent consolidation candidate; verify full diffs/tests before selecting it. Avoid duplicating grade fix. |
| #99 | Adds a focused multi-child family-fee acceptance test; no production files changed. | Keep as acceptance-test candidate; verify it matches the required one-parent/multiple-child dues behavior. |
| #93 | Adds Cashier controller/view/role/migration and fee-scope tests, but also touches shared dashboard/seeder/role matrix and includes unrelated Olympiad/operations-search hunks. | Keep as high-value candidate; isolate unrelated changes and verify Cashier permissions against single-Owner/global and institution-scoped staff model. |
| #102 | Documentation-only coordination board based on older integration SHA 494b737bbd0e72ddce32d1e5d702f8b488237e74. | Reference only until reconciled with current feature register and baseline. |

### Next review sequence

1. Complete the remaining open-PR inventory with base/head SHA, changed paths, checks, dependencies, overlaps and disposition.
2. Inspect complete diffs and tests for the groups above; file overlap alone does not prove correctness or supersession.
3. Select one canonical implementation per overlap group only after content-level equivalence is established. Do not close branches during review.
4. Align retained work to the current integration baseline in dependency order and test each integration in isolation.
5. Only after the feature register is reconciled and the Owner approves scope should implementation remediation or integration proceed.

No merge, deployment, production-data mutation, or live student/employee data is authorised by this ledger. A route, UI, migration, or narrow green test alone does not prove a feature complete. Keep draft PRs open until their work is accounted for.


## Phase-two findings — wider open-PR inventory

The repository still reports 56 open PRs. The following changed-file review covers the remaining older and parallel streams. These are provisional classifications from paths and patch size; exact semantic equivalence and runtime correctness still require full diff/test review.

### Additional overlap, duplicate and dependency clusters

| PR(s) | Evidence | Provisional disposition |
|---|---|---|
| #85 + #87 | Same three changed paths: ExaminationController, examinations view, and role-boundary test; both report the same 41 additions/10 deletions. | Strong duplicate candidate. Compare patch IDs/content and checks; retain one canonical PR only after exact equivalence is verified. |
| #86 + #88 | Same two paths: AdministrationController and role access matrix test; nearly identical 16/1 vs 15/1 patch sizes. | Strong duplicate candidate; compare final diff and base ancestry before selecting canonical. |
| #66 + #69 | Both change BiometricDeviceController, the same biometric-device migration, TagorePrototypeSeeder, PayrollController, dashboard, routes and modernization documentation. #69 adds a workflow and role/device mapping test. | One biometric multi-site implementation line. #69 appears to be an expanded successor; inspect ancestry and extra changes before deciding whether #66 is superseded. |
| #65 + #68 | Both deliver the biometric/bulk-import integration document; #65 additionally changes the profile self-service test. | Documentation overlap. Merge content into one canonical reference; keep the profile test change with the profile feature owner. |
| #67 + #71 | Both add `docs/ERP_MARKET_BENCHMARK_AND_MODERNIZATION_REGISTER.md`; different addition counts (704 vs 899) indicate related versions, not proven byte-identical copies. | Select one consolidated benchmark after content comparison; avoid maintaining two competing market registers. |
| #72 + #78 | Both touch the feature register, roadmap, role matrix, route/authorization inventory, repository evidence audit and authentication plan; #78 also adds the programme charter/master index. | Treat as one documentation baseline lineage. Compare chronology and content; designate a single authoritative register and avoid parallel drift. |
| #74 + #75 | Both modify `routes/admin.php` and the same legacy mutation-method test; one handles academic-year deletion, the other holiday deletion. | Complementary security fixes sharing tests/routes; integrate as a combined HTTP-method regression set, not duplicates. |
| #49 + #59 + #70 | All change `.github/workflows/live-acceptance.yml`; #59 adds acceptance documentation and live-site E2E; #70 is a one-line workflow-scope change. | One live-acceptance workflow lineage. Keep production acceptance read-only and fail-closed; compare workflow triggers/permissions before selecting final version. |
| #42 with #95/#99/#112/#115 and fee/diary work | #42 changes AdmissionsController, DailyDiaryController, FeeController, ParentDashboardController, parent views and family-diary acceptance test. Newer PRs split these domains into targeted family linking, conversion, class access and fee acceptance work. | Legacy broad feature candidate, not safe to merge wholesale. Use it as historical implementation evidence; compare each domain against newer scoped PRs and avoid reintroducing stale code. |
| #90 + #93 + #69 + #107 | Shared FeeController/PayrollController/dashboard/role matrix/administration surfaces appear across fee visibility, cashier, biometric and HR work. | Cross-domain integration hotspot. Sequence role/tenant authorization first; then fee/cashier and HR/biometric behavior with shared regression tests. |
| #52 + #86/#88 | All touch AdministrationController; #52 adds owner-managed account controls and an audit migration, while #86/#88 adjust account-status display. | Complementary but overlapping owner-console work. Preserve single Owner authority; reconcile account lifecycle, audit history and status rendering in one controller review. |
| #64 + #65 | #64 implements profile/logout UI and controller/routes; #65 adds profile self-service test coverage while documenting biometric/import workflows. | #65 test belongs with #64 profile work; keep biometric documentation separate from profile implementation. |
| #56 + #90 | Both modify `resources/views/tagore/operations/index.blade.php`; #56 also touches production deployment workflow, #90 adds broader operations/fee authorization behavior. | Shared operations page conflict risk. Reconcile page changes; do not merge the deployment workflow change as an incidental side effect. |
| #31 + #33 | Both modify `e2e/tests/tagore-human-style.spec.js`; #33 adds Android emulator and desktop client workflows/scripts. | Shared E2E contract. Preserve one stable human-style suite and extend it with mobile/desktop client checks without duplicating or weakening acceptance. |

### Distinct candidates that should remain visible

- **#111 Gate pass institution validation:** focused security fix in Tagore SecurityController with dedicated cross-institution test.
- **#110 Staff attendance failure handling:** controller, academic process trait and Vue form changes with failure-path test.
- **#108 Free parallel feature CI:** adds a workflow matrix across major feature areas; validate it does not mask, cancel, or replace release-blocking full acceptance.
- **#107 HR leave/payroll scope:** distinct HR authorization work; coordinate with shared PayrollController changes in #69.
- **#106 CBT/JEE-NEET engine:** substantial dedicated question-bank/attempt/scoring foundation with migrations, routes, UI and tests; retain as a distinct feature stream.
- **#105 Impersonation security:** focused authorization/CSRF hardening; retain and verify against Owner-only administration.
- **#103 Admissions fieldwork:** dedicated campaign/form workflow with migration and tests; coordinate with CRM and admission conversion schema.
- **#101 Backup/restore:** Windows backup scripts and restore documentation; must eventually be tested on a disposable restore target, not production data.
- **#77 MSG91 transport:** documentation/security plan only; SMS is not a current requirement for Owner recovery because email OTP is acceptable.
- **#61 Shared UI foundation and #38 dashboard search:** foundational UI work; verify whether already present in the current integration baseline before considering any replay.
- **#60 product audit, #67/#71 benchmark, #72/#78 programme docs:** research/documentation lineage; consolidate into one authoritative requirements source, not multiple competing documents.
- **#27/#22/#21 production fixes:** deployment/runtime hardening candidates; verify whether their changes are already present on main and the deployed production baseline before replay.
- **#33 Android/desktop CI, #64 profile/logout, #69 biometric, #106 CBT, #101 backup:** important functional/operational streams but still require end-to-end acceptance evidence.

### Updated CI diagnosis for PR #118

The latest head `b4790d863c9d76cb0c15ff0a1c54e02bd30a51f0` has six failed workflow runs (three push and three pull-request events). Logs identify two independent syntax blockers:

1. `app/Services/Tagore/OperationalAuditService.php:5`: PHP parse error, unexpected namespaced name `Carbon\\CarbonInterface`.
2. `tests/Feature/TagoreOperationalAuditFoundationTest.php:112`: PHPUnit cannot load the test file due to an unexpected backslash token.

The MySQL migration smoke, frontend build, browser E2E and production Docker smoke passed. The backend syntax gate and full ERP acceptance failed; dependent backend/acceptance steps were skipped. Do not treat these partial green checks as acceptance.

## Phase-two integration order

1. Establish a single authoritative requirements/market-audit document lineage (#67/#71, #72/#78, #78 programme index) and reconcile it against the current 168-row feature register.
2. Resolve exact duplicate candidates (#85/#87 and #86/#88) through patch comparison; keep branches open until verified.
3. Reconcile security prerequisites: owner-console identity (#52/#86/#88), role boundaries (#85/#87), impersonation (#105), gate pass (#111), legacy admission scope (#113/#114), fee visibility (#90), and legacy mutation methods (#74/#75).
4. Integrate domain work in dependency order: admissions CRM/fieldwork/conversion/family links; payment reservation then partial allocation; diary authorization/audit; cashier/fees; HR/biometric/attendance; CBT; backup/restore; client/E2E.
5. Reconcile shared UI and workflow hotspots (#31/#33, #49/#59/#70, #56/#90, #90/#93/#69/#107) only after the underlying feature owners agree on the canonical files.
6. Repair PR #118 syntax blockers only as a separately authorised task, then rerun the complete backend, browser, migration and full acceptance gates.

No PR is closed, merged, retargeted, or deployed by this review. Branches remain preserved pending content-level verification and Owner approval.


## Patch-level duplicate verification

A direct per-file patch comparison was completed for the strongest suspected duplicate pairs:

- **#85 and #87: exact patch duplicate confirmed.** Both have the same three paths and every file patch is byte-for-byte identical, including 5/3 changes in ExaminationController, 7/7 in the examination view, and 29 additions in the role-boundary test. Keep both branches preserved for now; nominate one as canonical after checking commit ancestry/checks and confirm the duplicate adds no unique commits.
- **#86 and #88: not exact duplicates.** AdministrationController patch is identical, but the role-access matrix test differs (15 additions vs 14); compare the one-line test difference and ancestry before choosing a canonical candidate.
- **#67 and #71: not exact duplicates.** Same benchmark document path, but different content sizes (704 vs 899 additions); consolidate the more complete/reliable research rather than declaring either superseded by title.
- **#66 and #69: not exact duplicates.** #69 expands the controller and fee-scope behavior, adds live-acceptance workflow change and a dedicated role/device mapping test; shared migration and several files are similar. Treat #69 as an expanded candidate, but inspect the changed migration/controller hunks and test quality before superseding #66.

This patch comparison upgrades #85/#87 from “suspected duplicate” to “exact patch duplicate”; no branch was closed or changed.
