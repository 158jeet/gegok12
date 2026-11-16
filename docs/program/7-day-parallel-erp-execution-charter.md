# Tagore ERP — 7-Day Parallel Execution Charter

**Status:** Proposed execution charter  
**Target:** Seven-day sprint to a tested, integrated core ERP release candidate  
**Source of truth:** `docs/INITIAL-REQUIREMENTS-AND-UX-REVIEW.md`, the role matrix, security audit, route inventory and feature-gap register already maintained in this repository.  
**Data boundary:** Synthetic/disposable data only. No production database, real student/family records, live payment, email/SMS, account/session changes or deployment as part of this sprint without separate explicit approval.

## 1. Sprint objective and definition of done

The sprint is successful when the repository contains a coherent release candidate that:
1. Implements or verifies the P0 journeys listed below across the existing product, rather than merely making pages render.
2. Passes exact-head CI, integration regression and role-based acceptance on synthetic data.
3. Has no unresolved release-blocking authorization, tenant-isolation, financial-integrity, data-loss or migration defect.
4. Has a documented list of P1/P2 items and known limitations; nothing is silently removed from the requirements.
5. Has a tested deployment/rollback runbook and an Owner-facing release decision. Production deployment remains a separate approval.

A seven-day target is an execution constraint, not permission to bypass quality gates. If an acceptance gate fails, the release candidate is not declared ready.

## 2. Operating model

Use parallel workstreams with isolated branches and small pull requests. Keep one integration coordinator responsible for shared contracts, dependency order, integration, duplicate-work prevention and release status.

### Shared contracts — central ownership
Before parallel implementation, freeze the minimum contracts for:
- Identity, role identifiers and the single top-level Owner boundary.
- Institution membership, campus/class/family scoping and academic-year context.
- Audit-event format and actor/subject attribution.
- API response/error conventions, validation and pagination.
- UI components, navigation and permission-aware rendering.
- Migration naming, rollback expectations, test fixtures and synthetic-account conventions.

Shared-contract changes require central review. Workstreams must not independently redefine these primitives.

## 3. Workstreams and boundaries

| ID | Workstream | Primary ownership | Explicit boundary |
|---|---|---|---|
| WS-SEC | Identity, authorization and audit | Owner-only authority, role matrix, route/API/policy scope, recovery, audit | Sole owner of shared auth/role primitives |
| WS-ADM | Admissions and fieldwork | Leads, family linking, configurable forms/campaigns, field submissions, entrance-test campaign linkage | No automatic enrolment or silent overwrite of existing student/family records |
| WS-ACA | Academics | Class/section, attendance, timetable, diary/homework, exams/results and learning workflows | Institution/class/academic-year scope must be enforced server-side |
| WS-FIN | Finance | Fee ledgers, cashier/Accounts separation, family payment allocation, receipts, concessions and reconciliation | No cross-child ledger mixing; no unauthorized refunds, discounts or adjustments |
| WS-HR | HR and workforce | Employee records, leave routing, staff attendance and payroll workflows | Multi-institution approvals and self-approval prevention |
| WS-OPS | Campus operations | Gate pass, visitors, transport, hostel, library and inventory foundations | No claim of CCTV/GPS vendor integration without verified end-to-end tests |
| WS-APP | Applications and integrations | Parent/teacher app, Windows client, notifications, offline sync and external integrations | Inspect submodules and vendor interfaces before claiming parity or integration |
| WS-QA | Quality and acceptance | Cross-workstream integration, browser journeys, visual checks, role matrix, regression and release evidence | Independent release gate; cannot waive a failing security/finance test |

Each workstream owns its implementation and tests for its bounded area. WS-QA owns the combined acceptance suite. WS-SEC reviews every cross-tenant and privileged-action change.

## 4. Seven-day execution sequence

### Day 1 — Lock, map and launch
- Confirm the authoritative requirements and classify every requirement P0/P1/P2 without deleting any.
- Map each P0 requirement to existing code, a workstream, a named test and an acceptance result.
- Publish shared contracts and migration/API ownership.
- Create isolated workstream branches and draft PRs with explicit file/module ownership.
- Establish a single sprint board/report with owner, status, blocker, latest commit and exact-head CI.
- Run a baseline full CI and capture current failures before new code is introduced.

### Days 2–3 — Parallel build and repair
- All workstreams implement in parallel against the agreed contracts.
- Prefer completing and repairing existing foundations over rewriting modules.
- Submit small PRs as soon as a vertical slice is independently testable.
- Run workstream CI on every PR update; integrate green slices continuously.
- QA builds end-to-end synthetic journeys while implementation proceeds.

### Day 4 — Integration convergence
- Integrate completed slices in dependency order.
- Resolve migration, route, policy, API and UI conflicts centrally.
- Run combined migrations from a clean disposable database and upgrade-path tests.
- Freeze new shared-contract changes unless required to fix a blocker.

### Days 5–6 — Full acceptance and hardening
- Run role-based journeys for Owner, Principal, Coordinator, Teacher, Accounts/Cashier, Parent and Student, plus any explicitly approved Technology Controller scope.
- Test cross-institution, unassigned-class, unlinked-family and unauthorized-record denial cases.
- Test financial invariants, duplicate submissions, idempotency, audit trails, file access and concurrent updates.
- Run browser/mobile viewport checks, accessibility basics, performance smoke and production-build/container smoke.
- Fix release blockers; rerun failed jobs and the complete affected regression suite.

### Day 7 — Release candidate decision
- Verify exact-head workflow results for the final integration SHA.
- Produce acceptance evidence, open defects, deferred P1/P2 register and rollback plan.
- Verify backup/restore procedure in a non-production environment.
- Present the release candidate for Owner approval. Do not deploy automatically.

## 5. P0 release-candidate journeys

At minimum, the release candidate must prove these journeys end to end, using synthetic accounts and records:

1. Owner can manage the institution group and assign operational roles without creating a second Owner.
2. Principal and Coordinator access is limited to their permitted institution and academic scope.
3. Teacher accesses assigned classes/students and performs permitted attendance, diary/homework and academic tasks.
4. Parent accesses only linked children, including multiple children where family linking is supported.
5. Student sees only their own permitted academic information.
6. Accounts and Cashier have distinct permissions; cashier cannot access central settlement, fee-master or restricted ledger actions.
7. Fee payment/receipt and family allocation preserve separate child/institution ledgers and are safe against duplicate processing.
8. Admissions lead capture, review, assignment and conversion preserve existing records and family links.
9. Published forms preserve version history; unauthorized staff cannot access unassigned campaigns or submissions.
10. Leave and concession approvals follow the configured authority chain and create auditable decisions.
11. Sensitive files, exports, APIs and direct route requests enforce the same server-side scope as the UI.
12. Critical actions are auditable and errors do not leak private records or credentials.

Where a journey is not yet implemented, record it as a failed or deferred requirement with evidence; do not mark it complete because a dashboard or route exists.

## 6. CI and pull-request rules

- Every workstream uses an isolated branch from the agreed integration base; avoid parallel edits to shared files.
- PRs are small, scoped, draft by default, and include requirement IDs, changed surfaces, migration notes, tests and known limitations.
- No merge based solely on successful syntax/build checks. Require relevant feature tests and combined integration checks.
- Run the affected workflow on the exact current PR head. A prior commit's green result does not validate a later head.
- Use clean disposable databases for migration and seed tests. No production credentials in fixtures or logs.
- Migrations must be additive/compatible where feasible, deterministic, and tested from both clean and supported upgrade states.
- No workstream may weaken authorization, remove an acceptance test, or mark an unimplemented feature complete to meet the deadline.
- Integration is continuous; do not wait until Day 6 to combine branches.

## 7. Priority and scope policy

**P0 — release-critical:** authentication and authorization, institution/family isolation, core admissions, core academics, core fees/receipts, role-specific workflows, auditability, backups and deployment safety.

**P1 — important:** advanced reporting, configurable workflows, richer dashboards, expanded forms/entrance-test capabilities, extended HR/operations and broader app parity.

**P2 — extended:** advanced CCTV/GPS vendor integrations, sophisticated offline conflict resolution, advanced analytics and nonessential automation.

All requirements remain in the master register. P1/P2 may continue after the P0 release candidate; no requirement is silently discarded.

## 8. Daily operating cadence and dashboard

At the start and end of each working day, report:
- Workstream status: not started / active / blocked / ready for integration / accepted.
- PR links and latest head SHA.
- Exact-head CI: pass / fail / queued / not found.
- Requirements completed with test evidence, not estimates.
- Blockers, owner and next action.
- Integration conflicts and release-blocking defects.
- P0/P1/P2 counts and release-candidate confidence.

A workstream is not “complete” until its acceptance evidence is linked and its integrated behaviour passes.

## 9. Explicit non-goals and safety gates

This charter does not authorize:
- Merging to `main`, changing branch protection or disabling required checks.
- Production deployment, migrations or live database edits.
- Creating, disabling or resetting real user accounts or revoking real sessions/tokens.
- Sending real email/SMS, charging/refunding payments or connecting live vendor services.
- Treating uninspected mobile submodules or unverified vendor integrations as complete.

These actions require a separate release decision and the necessary explicit approval.

## 10. First execution actions

1. Verify the latest PR #76 exact-head workflows and resolve any active failure.
2. Extract the existing requirements into a traceable P0/P1/P2 register with stable IDs.
3. Inspect current branches, open PRs and workflow triggers to avoid duplicate or conflicting work.
4. Establish the integration base and shared contracts.
5. Launch workstreams in parallel, beginning with the least-coupled vertical slices while WS-SEC owns shared authorization changes.
6. Review actual throughput after the first implementation cycle and revise the remaining sprint forecast based on merged, tested functionality.

**Success measure:** accepted, integrated requirements with reproducible evidence—not branch count, commit count, or number of running workflows.
