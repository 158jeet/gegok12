# Tagore ERP — Modernisation Roadmap

**Status:** Proposed; dependent on repository evidence audit and owner review.  
**Execution guardrail:** Git-first; synthetic data only; no live DB, hardware activation, production migration or deployment until explicit approval and backup/release gates.

## Delivery method
1. Convert each feature-gaps.csv row into a traceable feature record.
2. Inspect routes, controllers, middleware/policies, models, migrations, views, APIs, jobs, notifications, exports and tests.
3. Assign evidence-backed status: verified-present, partial, not-found, unknown or blocked-by-external-dependency.
4. Write acceptance tests before implementation using synthetic institutions, students, guardians, staff and transactions.
5. Implement coherent Git changes; run CI and human-style role acceptance.
6. Merge only after review/approval. Deploy only from a tested release with backup and restore verification.

## Phase 0 — Evidence audit and design lock
- Reconcile owner report with current main, PR #67, #69, #71 and related issues/PRs.
- Build route/controller/API/job/export inventory for all modules.
- Verify institution scoping and permission enforcement on every read/write path.
- Verify current UI page-by-page for every role, not just dashboard/menu presence.
- Verify mobile app source, API parity, build pipelines and supported devices.
- Revalidate competitor claims against dated primary vendor docs/demos; record plan tier and uncertainty.
- Produce evidence-complete feature register, dependency map, architecture decisions and prioritised backlog.

**Exit gate:** no major feature marked complete/absent without evidence; owner reviews priorities and confirms scope.

## Phase 1 — Security and platform foundations (P0)
1. Institution hierarchy and tenant isolation.
2. Role × action × data-scope policies and tests for web, API, jobs, exports and files.
3. Parent/guardian identity, verified family linking, revocation and multi-child access.
4. Audit event framework, immutable histories and communication delivery logs.
5. Backup/restore process, annual archive and recovery drill.
6. Consistent design system and complete page styling for existing role journeys.

**Acceptance:** cross-institute negative tests; direct-ID/API/export denial; parent isolation; audit completeness; backup restore; representative role walkthrough.

## Phase 2 — Finance and family fee journeys (P0)
1. Separate institute ledgers and group consolidation rules.
2. Cashier collection dashboard: paid, pending, overdue, follow-up, daily reconciliation and alerts.
3. Central Accounts consolidated finance and HR views.
4. Teacher/Coordinator minimum read-only class-level fee status.
5. Fee plans, installments, percentage revisions, prorated admission demands and controlled concessions.
6. Parent multi-child checkout with per-child/per-institute allocation preview.
7. Gateway adapter contract with idempotent webhooks, timeout, reversal/refund and reconciliation states.
8. Email receipts and notification retries/logs.

**Acceptance:** partial payments; two-child family checkout; no cross-child silent credit; duplicate webhook; timeout/reversal/refund; demand changes during checkout; no parent access to internal audit.

## Phase 3 — Admissions, academics and staff operations (P1)
- Admissions CRM, application, document workflow, imports, deduplication and safe onboarding.
- Promotion preview, approval, conflict checks and longitudinal student history.
- Timetable conflict checks and substitute recommendations.
- Attendance notifications, homework and teacher-parent diary.
- HR shifts, leave, payroll and staff records.
- T304F adapter only after vendor protocol/security documentation; first test with simulator/synthetic punches.
- Owner department task/project/KPI module with evidence and escalation.

**Acceptance:** applicant converts once; promotion idempotent; substitute excludes unavailable staff; payroll ties to approved attendance; raw punches immutable; task audit/escalation pass.

## Phase 4 — Assessment products (P0 coaching; P1 school exam uplift)
Keep three experiences separate:
- School examinations: board-specific grading/report cards, moderation and publishing.
- Olympiads: event/registration/rounds/scoring/awards.
- Vedant JEE/NEET coaching: question bank, blueprint builder, realistic CBT player, section timers, negative marking, autosave/resume, rank/percentile and topic analytics.

AI tutor/study recommendations and proctoring are later increments after core accuracy, privacy, security and cost review.

**Acceptance:** question import validation; supported question types; timing/resume; scoring edge cases; rank ties/percentiles; 100+ synthetic candidates; result privacy; immutable published snapshots.

## Phase 5 — Parent/teacher apps and communication (P1/P2)
- Mobile parity for approved parent and teacher workflows.
- Parent multi-child dashboard, fees, attendance, homework, diary, report cards, notices and events.
- Push/email/SMS delivery, retry, acknowledgement and audit.
- Moderated first-party news feed; social integration only where permitted/stable.
- Secure click-to-call after provider, privacy, consent and cost review.

**Acceptance:** same role/child scope on mobile and web; push failures observable; revocation works; no private staff-number leakage.

## Phase 6 — Campus operations and college (P2)
- Visitor/gate pass register and reception workflows.
- Transport route/vehicle/driver/student assignments; GPS only after vendor/API review.
- Hostel rooms, hosteller attendance, mess/hostel fees, incidents and inventory.
- Library circulation and inventory lifecycle.
- PG College semester/examination/evaluation and OBE only after university affiliation/course requirements verified.
- CCTV remains disabled until approved vendor/security documentation and explicit activation approval.

## Phase 7 — Final acceptance and release readiness
- Synthetic fixtures for every institution type and representative role.
- Full desktop/mobile end-to-end acceptance across workflows.
- Security review: tenant isolation, IDOR, privilege escalation, CSRF, webhook authenticity, file access, rate limiting and audit tampering.
- Finance reconciliation/idempotency tests.
- Performance baselines for attendance, fees and exams.
- Accessibility, responsive layout, exports, notification delivery and error recovery.
- Backup/restore drill; release notes; rollback plan; signed acceptance checklist.

**Release gate:** all P0 and agreed P1 tests pass; no unresolved critical security/data-integrity defects; owner explicitly approves release. Only then plan production backup, migration and deployment.

## Priority principles
- **P0:** tenant isolation, RBAC, family identity, audit, ledger integrity, cashier/central accounts boundaries, family checkout, backup/restore, complete core UI, coaching CBT foundation.
- **P1:** admissions lifecycle, promotion, timetable/substitution, attendance/HR, homework/diary, school exam/report card uplift, mobile parity, owner task/KPI.
- **P2:** visitor, transport GPS, hostel, library/inventory enhancements, college workflows after regulatory discovery.
- **P3:** AI tutor, remote proctoring, social integrations, CCTV/advanced calling integrations.

Priorities may change after evidence audit, dependency assessment and owner review. Do not parallelise work that depends on unsettled identity, tenant, ledger or permission foundations.
