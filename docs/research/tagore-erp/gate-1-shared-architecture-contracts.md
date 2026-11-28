# Tagore ERP — Gate 1 Shared Architecture Contracts

**Status:** Architecture contract proposal for integrated implementation; not a production release specification.
**Baseline:** `main` at `131e0b154554110b376f5a7d24148b5da4cf73f7`.
**Related work:** comparative audit PR #161; cashier workspace PR #160.

## 1. Non-negotiable delivery rules

1. Git is the source of truth. No server-only fixes; every accepted change is committed, reviewed and tested in Git before release.
2. Synthetic fixtures only until the complete release candidate passes acceptance and the Owner authorises live-data onboarding.
3. No production deployment, schema migration, payment activation, hardware activation or merge is implied by this document.
4. All implementation tracks integrate against one shared contract and one release-candidate SHA. No disconnected feature branches are declared complete.
5. Existing controllers, services, views, tests and open PRs are reused or extended where evidence supports them; no rewrite merely for consistency.

## 2. Canonical institution and data-scope contract

- The group is the top-level reporting and governance boundary.
- Every operational record must resolve to exactly one owning institution unless it is explicitly a group-level record.
- A user may have multiple institution memberships, each with a role and an explicit scope. Never infer access from a matching legacy `school_id` alone.
- Owner can view and administer all institutions. Central Accounts has group-wide finance visibility but does not become a second Owner.
- Cashier is limited to active institution assignments. Principal is institution-wide; Coordinator is limited to assigned grades/sections; Teacher is limited to assigned classes/subjects and their students; Parent is limited to verified linked children; Student is self-only.
- HR, Warden/Hostel, Transport, Reception/Security, College and Coaching permissions must be institution- and domain-scoped.
- Every list, detail, direct-ID lookup, mutation, API, export, download, notification recipient resolution, background job and report must enforce the same policy.
- Cross-institution access is denied by default. Group roll-ups are explicit, read-only where appropriate, and audited.

**Required security tests:** at least two institutions, two academic years, overlapping role memberships and deliberately mismatched IDs. Assert both positive access and negative access for every endpoint family.

## 3. Owner, identity and authentication contract

- Exactly one active global Owner identity is permitted for Tagore. No second Owner can be created through UI, API, import, seeder or role assignment.
- Owner can provision users, assign roles/scopes, reset credentials and manage institution settings.
- Owner recovery uses the Owner's verified recovery email. Email OTP is supported; SMS OTP is not a release dependency.
- New users receive invitation/verification and must set a unique password. Admission number, date of birth and phone number must never be permanent passwords.
- Password reset, disable, role removal and scope revocation invalidate active sessions/tokens as specified by the security design.
- All privileged changes create append-only audit events with actor, target, institution, before/after values, timestamp and request context. Secrets and OTP values are never logged.

## 4. Parent and family identity contract

- A guardian is one verified identity that may be linked to multiple children across Tagore institutions.
- A family link records relationship, guardian authority, verification source/time, status, creator and revocation metadata.
- A parent can see only active, verified child links. Revocation takes effect across web, mobile, API, cached sessions and payment journeys.
- A child may have multiple guardians; guardian permissions are explicit and not inferred from matching phone numbers.
- Family checkout is one payment intent with multiple child/institution/fee-item allocations. Every allocation remains attributed to the correct student and institution ledger.
- The checkout preview must show each child, institution, fee head, due amount, amount selected, discounts/concessions and total before confirmation.
- No parent can see internal audit notes, staff-only comments, other families or cross-child personal data beyond the explicit family summary.

## 5. Finance and ledger contract

### 5.1 Ledger invariants

- Fee obligations and their line items are the source of truth for student receivables.
- Posted payments, allocations and financial transactions are immutable. Corrections use audited reversal/compensating entries; never edit or delete a posted payment.
- Every payment must allocate exactly to one or more obligations/installments; allocation total equals payment total to the currency's smallest unit.
- Paid + outstanding must reconcile to net payable, with explicit treatment for discounts, concessions, waivers, write-offs and refunds.
- Each payment and allocation is institution-attributed. Group consolidation is a reporting layer, not a merged institution ledger.
- Receipt numbering is institution-specific, unique and transaction-safe.

### 5.2 Payment intent and concurrency

- Creating a payment intent locks/revalidates the relevant obligations and creates explicit allocation reservations with expiry and status.
- Concurrent intents cannot reserve the same outstanding amount beyond the available balance.
- Expired, failed or cancelled intents release reservations idempotently.
- Gateway callbacks are signature-verified, deduplicated by provider event ID, replay-safe and state-machine validated.
- Settlement rechecks reservation and allocation totals under database locks. A captured payment that no longer safely allocates is quarantined for review; it must not silently over-collect or issue an incorrect receipt.
- Refund, chargeback and reversal events create compensating ledger entries, reverse affected allocations and update receivables without mutating the original payment.
- Gateway settlement/reconciliation is separate from counter collection and parent reminders.

### 5.3 Role boundaries

- Cashier can view institution-scoped dues, collect approved amounts, take permitted offline modes, issue/download receipts, view own or institution collection register as authorised, send audited reminders and assign an already-approved fee structure only where the institution policy permits.
- Cashier cannot create/edit fee masters or amounts, grant concessions, waive/write off dues, reverse/refund payments, edit ledger entries, reconcile bank/gateway settlements, view secret gateway credentials or access other institutions.
- Cashier dashboard must not expose settlement identifiers or settlement amounts. A generic exception count may be shown only if it does not disclose bank reconciliation details and is explicitly approved.
- Central Accounts can review group-wide books and perform separately permissioned settlement/reconciliation, adjustments and approvals. High-risk actions require reason capture and audit.
- Teacher and Coordinator fee views are read-only and restricted to assigned class/section scopes. They see status needed for follow-up, not payment instruments or internal accounting metadata.
- Parent sees child-level dues, payment history and receipts only; no internal audit or reconciliation records.

## 6. Academic and examination contracts

- School exams, Olympiads and Vedant JEE/NEET CBT are distinct products with separate lifecycle and permissions.
- CBSE/RBSE templates, grading, competency/HPC fields and report cards are configurable by institution/board and versioned by academic year.
- Marks/results follow draft → entry → moderation → approval → publish. Published results are immutable snapshots; corrections are versioned and audited.
- Coaching CBT requires question taxonomy, blueprint, supported question types, section rules, negative marking, deterministic attempt snapshots, timers, autosave/resume, scoring audit, rank/percentile and topic analytics.
- Candidate access is limited to assigned exams and their own attempts/results. Question banks and answer keys are never exposed to candidates.
- Promotion/year rollover is previewed, validated, approved, idempotent and reversible through an audited correction workflow.

## 7. Shared platform contracts

- APIs are versioned and enforce the same identity/scope policy as web.
- All state-changing actions use non-GET methods, CSRF/authentication protections and server-side validation.
- Audit events are append-only; financial events are never overwritten.
- Notifications use a common event/outbox contract with recipient-scope validation, delivery attempts, retries, provider response and user preferences.
- Imports are preview → validate → apply, idempotent where possible, with row-level error reports and rollback on failed batches.
- Files are private by default, stored with ownership/institution metadata, scanned/validated, and authorised on every download.
- Background jobs re-resolve permissions and ownership at execution time; they must not trust stale queued scope.
- Backup includes database and uploaded files, encrypted off-site copies, retention and a restore drill into a disposable environment.
- Desktop/mobile offline changes include device identity, revocation, per-record authorization, idempotency, conflict detection and a user-visible resolution state.

## 8. External integration gates

- RealTime T304F biometric integration remains disabled until the exact device model, protocol/SDK, authentication, network requirements and vendor permission are documented and tested with synthetic punches.
- Hikvision CCTV remains disabled until NVR/model compatibility, RTSP/ONVIF security, network isolation, privacy and access-audit requirements are approved.
- GPS, WhatsApp/SMS, email delivery providers, payment gateway live mode and app-store releases each require separate credentials, cost review, privacy review and explicit activation approval.
- No marketing claim or competitor feature list is accepted as proof of a hardware/API integration.

## 9. Shared UI and workflow contract

- One design system governs Owner, Accounts, Cashier, Principal, Coordinator, Teacher, Parent, Student, HR, Hostel, Reception/Security, College and Coaching experiences.
- Navigation is role-aware; direct URLs remain permission-checked.
- Every workflow has loading, empty, success, validation-error, permission-denied and recoverable-failure states.
- Tables provide search, filters, pagination, totals, exports only where authorised, and clear institution/academic-year context.
- Forms validate server-side, preserve safe input on recoverable errors and prevent accidental duplicate submission.
- Responsive layouts must work at desktop, tablet and mobile breakpoints. Colour semantics are consistent and accessible.

## 10. Integration and acceptance protocol

1. Each workstream writes tests against these contracts before or alongside implementation.
2. Shared hot files (central routes, role seeders, core migrations, fee/payment services, common layout) have one named owner per integration window.
3. Each feature branch is rebased/rebuilt on the current integration head; stale branches are source material only.
4. Merge into one candidate only after exact-head CI and relevant security/finance regression tests pass.
5. Run one consolidated acceptance suite on the exact candidate SHA, using synthetic multi-institution fixtures and real browser workflows.
6. Group failures by root cause, fix in coherent batches, and rerun both affected suites and the full gate.
7. Release only the exact tested SHA with recorded migration state, backup/restore proof, queue/scheduler/TLS health and Owner sign-off.

## 11. Immediate reconciliation notes

- PR #160 is six commits ahead of `main` with no base divergence at the time of comparison. Its cashier workspace is valuable existing work and must be reviewed in place, not recreated.
- PR #160 introduces a dedicated cashier controller/workspace, institution collection view, CSV export, approved fee-structure assignment and app-push reminders. Its acceptance tests are a starting point, not full finance acceptance.
- The reviewed cashier controller includes an `online_to_review` count in the summary. Confirm whether this leaks settlement/reconciliation status to cashiers; default contract is that settlement details remain Central Accounts/Owner-only.
- The current online payment service still requires a complete family-wide payment-intent and reservation design, concurrency-safe settlement, and refund/reversal compensating entries. A single-child order and idempotent success check do not satisfy these requirements.
- Do not merge PR #160 solely because its branch is current. Review its exact-head CI, browser acceptance, scope-negative tests, migration safety and the settlement-status boundary first.
- Existing main evidence audit documents a much larger feature surface than the initial cashier PR. Reconcile all open PRs and the current feature register before assigning implementation owners.

## 12. Exit criteria for Gate 1

Gate 1 is complete when:
- The role × action × scope matrix is machine-readable and tested.
- The group/institution/family data model and migration strategy are agreed.
- Ledger/payment state diagrams and invariants are tested.
- API, audit, notification, file, import, backup and offline contracts are documented.
- The UI design system and shared components are agreed.
- Every workstream has bounded ownership, dependencies, acceptance tests and a current-baseline branch.
