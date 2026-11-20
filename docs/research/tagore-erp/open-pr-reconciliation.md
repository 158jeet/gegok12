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
