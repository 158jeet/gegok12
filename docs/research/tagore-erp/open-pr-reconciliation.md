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
