# Tagore ERP — Feature Audit & Market Benchmark

**Status:** Owner-supplied completed research baseline, committed for review  
**Date:** 2026-11-14  
**Scope:** Tagore Public School (CBSE); Tagore Bal Niketan (RBSE Hindi/English 6–12); Tagore Smart School (RBSE Hindi/English K–5); Tagore Vedant Academy (pre-foundation, MNS, JEE/NEET coaching); Tagore Hostel; Tagore PG College; group Owner, Central Accounts and HR.

## 1. Executive summary

Tagore ERP is based on the Gego-K12 Laravel application. The target is a secure multi-institution operating platform for schools, coaching, college, hostel and central offices—not merely a school ERP with additional menus.

The most important foundations are: institution hierarchy and data isolation; role/action/data-scope permissions; verified family identity and multi-child access; reliable fee and ledger allocation; student/staff lifecycle records; distinct school-exam, Olympiad and coaching assessment workflows; complete mobile and desktop journeys; audit, backup and restore; and consistent modern UI.

The supplied report classifies many areas as partial, absent or unknown. These are preliminary classifications. They are not verified current-code findings unless supported by repository paths and executable acceptance evidence.

## 2. Institution model

Institutions in scope:
- Tagore Public School — CBSE K–12.
- Tagore Bal Niketan Senior Secondary School — RBSE Hindi and English, classes 6–12.
- Tagore Smart School — RBSE Hindi and English, K–5.
- Tagore Vedant Academy — pre-foundation 6–10, MNS 4–5, JEE/NEET 11–12 and target batches.
- Tagore Hostel.
- Tagore PG College.
- Group Owner, Central Accounts, HR and authorised shared services.

Required hierarchy: group → institution → campus/department/wing → academic year → classes/sections/batches. Access must be explicitly scoped to institutions and, where relevant, classes, sections, departments or employee populations. Group roll-ups must preserve separate institution-level books and attribution.

## 3. Market benchmark set

The owner-supplied report benchmarks Fedena, Entab CampusCare 10X, MyClassBoard, iCloudEMS, DigiERP, WiZiAI, Examezy, JEENEET.org, Eklavvya, Kinvo and specialist attendance/visitor products.

| Product | Benchmark focus |
|---|---|
| Fedena | Multi-school, audit, reminders, finance, gate management, timetable |
| Entab CampusCare 10X | School administration, fees, attendance, exams, parent communication |
| MyClassBoard | Parent/student experience, sibling workflows, holistic cards, certificates, diary |
| iCloudEMS | College ERP, finance, payroll, digital evaluation, OBE |
| DigiERP | Student lifecycle, admissions, fee plans, payment and reminders |
| WiZiAI | JEE/NEET preparation, analytics, study plans, AI tutoring |
| Examezy | Question bank, paper creation, CBT, proctoring, rank and analytics |
| JEENEET.org | JEE/NEET test series and comparative performance |
| Eklavvya | Configurable online assessment, timing, question types, proctoring |
| Kinvo / secure calling | Parent-to-staff click-to-call without exposing private numbers |

The pasted report contains vendor citations and claims. Original inline citation identifiers are not portable Git citations. Revalidate primary source URLs, date, plan tier, availability and actual product behaviour before procurement or architecture decisions. Vendor marketing copy is not proof of implementation quality.

## 4. Functional audit baseline

### Institution structure and access control
**Preliminary status:** Partial / repository-wide verification required.  
**Required:** multi-institution hierarchy, central dashboards, institute-isolated records, role × action × data-scope matrix, and enforcement across routes, controllers, APIs, jobs, queries, exports and file downloads.  
**Acceptance:** Institute A Principal cannot read, mutate, export or infer Institute B data, including through direct IDs or APIs. Owner/Central Accounts can see authorised roll-ups while underlying books remain attributable.

### Admissions and onboarding
**Preliminary status:** Partial / not fully verified.  
**Required:** enquiry CRM, online application, registration, document checklist, admission review, duplicate detection, validated bulk import, family linking, fee-plan assignment and safe conversion to student/enrolment.  
**Acceptance:** applicant converts once; duplicates are detected; family relationship is verified; approvals are auditable; no orphan accounts or duplicate fee demands.

### Family identity and student profile
**Preliminary status:** Partial.  
**Required:** one verified parent/guardian identity can link multiple children across Tagore institutions. Parent sees only linked children. Student 360 profile consolidates permitted academics, attendance, fees, report cards, homework/diary, achievements and certificates.  
**Acceptance:** all verified children visible, unrelated children inaccessible, revoked relationships lose access, transfer/alumni history preserved.

### Finance and fees
**Preliminary status:** Fee heads/plans/receipts reported; completeness requires audit.  
**Required:** fee heads, annual percentage revisions, installments, approved concessions/waivers, prorated admission demand, receipts, reminders, ageing, collection summaries, exports and separate institute ledgers with group roll-up.

**Cashier:** institute-specific operational collection, paid/pending/overdue follow-up, collection reconciliation, receipts, reminders and daily reports. This is not bank/gateway settlement reconciliation. Cashier must not default to global payroll, fee-master changes, concessions/refunds/write-offs, ledger adjustments or bank settlement.

**Central Accounts:** authorised group-wide finance and HR reporting/updates.

**Teacher/Coordinator:** minimum necessary read-only pending-fee status for own/assigned classes; no payment posting or ledger mutation.

**Parent checkout:** select one or more linked children and fee items, enter partial amounts, preview allocations, then use one family checkout where merchant/settlement configuration permits. Every allocation posts to the correct child and institution ledger. Never silently credit sibling overpayment.

**Acceptance:** partial payment, gateway timeout, duplicate webhook, reversal/refund, two guardians/devices and mid-checkout demand change preserve idempotency and ledger integrity.

### Attendance, HR and payroll
**Preliminary status:** Staff profiles and attendance/payroll foundations reported; device integration disabled pending vendor contract.  
**Required:** employee profiles, institution assignment, shifts, leave, holidays, attendance exceptions, payroll and audit history. T304F requires a device registry (serial, key, location, institution), same-institute employee mapping, immutable/idempotent raw punches and reviewed corrections with reason, approver and audit. Integrate only after RealTime supplies supported API/SDK/ADMS/WSDL and security/network documentation. Do not guess endpoints or activate speculative push services.  
**Acceptance:** duplicate punch idempotent; invalid/unmapped employee quarantined; raw punch never overwritten; sync health and lag visible.

### Academics, timetable, homework and diary
**Preliminary status:** Timetable, attendance and teacher homework reported; depth requires verification.  
**Required:** timetable by institution/class/teacher/room; leave-aware substitute recommendations; clash/workload checks; manual override; student attendance; parent absence notifications; homework/assignment; teacher-parent diary and feedback/acknowledgement; offline draft/sync where supported.  
**Acceptance:** unavailable/overloaded teachers excluded from suggestions; override reason recorded; parent sees only relevant class/student communication.

### School examinations, Olympiads and coaching CBT
These are separate products with different operating rules.

- **School examinations:** calendar, subject schedules, marks entry, grade scales, moderation, approval, board-specific report cards, publication/withdrawal and immutable result history.
- **Olympiads:** event setup, eligibility, registration, rounds, question types, negative/partial scoring where configured, awards/certificates and supervised access.
- **Vedant JEE/NEET coaching:** rich question bank taxonomy, paper blueprint, section-level timing, negative marking, realistic CBT player, question palette, review/flag/navigation, autosave/resume, scoring, rank/percentile and topic analytics. AI study recommendations and doubt tutor are later capabilities.

The earlier narrow examination audit reportedly found school exam controllers/routes and migrations plus an Olympiad event/attempt workflow. It also reported limits in imported question types/scoring, a sequential player without full coaching-style navigation, and missing coaching-grade question-bank, blueprint, percentile and analytics capabilities. These are audit leads to recheck against current main, not immutable facts.

**Acceptance:** import validation; negative marking; numerical/multiple-correct where configured; section timers; resume; concurrency; immutable result snapshots; rank/percentile privacy; 100+ synthetic candidates.

### Parent/teacher mobile and communication
**Preliminary status:** Base apps and communication modules reported; end-to-end parity unverified.  
**Required:** parent app with multi-child dashboard, fees, attendance, homework, diary, results, notices, events and secure contact directory; teacher app with assigned classes, attendance, homework, diary, timetable and substitute information; push/email/SMS delivery logs and acknowledgement. News/social feed uses approved APIs or a moderated first-party feed with caching/fallback. Secure calling may use an approved provider; private staff numbers are not exposed by default.  
**Acceptance:** web/mobile scopes match; delivery/retry/acknowledgement observable; revoked users lose access.

### Owner tasks and KPIs
**Preliminary status:** Custom module absent / not evidenced in supplied audit.  
**Required:** departments, managers, task owners, due dates, dependencies, blockers, evidence, status, KPIs, escalation and performance reports. Start with transparent deterministic metrics; AI summaries only after data quality and access controls.  
**Acceptance:** assignment, updates, evidence, overdue escalation and department KPI roll-up auditable.

### Gate, visitor, transport, hostel, library and inventory
**Preliminary status:** Mixed partial/unknown; inspect each module rather than infer from menu labels.  
**Required:** visitor register with identity/photo, purpose, host, in/out, pass/QR/print and audit; CCTV research only until vendor credentials/contracts approved. Transport routes, vehicles, drivers, student assignment, attendance and optional GPS. Hostel room/bed allocation, hosteller attendance, mess/hostel fees, incidents and inventory. Library catalog/issue/return/fines; inventory assets, issue/return and stock audit.  
**Acceptance:** complete check-in/out trail; campus-scoped access; external integrations disabled until contracts verified.

### College and compliance
**Preliminary status:** Requires affiliation and course-level discovery.  
**Required:** verify university regulations, course/semester structure, examinations, digital evaluation/moderation and applicable OBE CO-PO-PSO or NAAC/NBA evidence. Do not implement generic compliance without confirming actual affiliation and obligations.

### UI, reporting, backup and offline operation
**Required:** consistent modern UI on every route/page; role dashboards; responsive desktop/mobile; accessible forms/tables; empty/loading/error states; exports; communication/audit logs; tested backup/restore; offline Windows client only for defined workflows with encrypted local storage, conflict resolution and safe sync.  
**Visual direction:** dark charcoal dashboard with green/red accents and polished inner pages, not dashboard-only styling.  
**Acceptance:** role-based browser walkthroughs, mobile viewport, exports, notification failures, backup restore, accessibility and performance baselines.

## 5. Role and access principles

See role_matrix.csv. This is a policy baseline, not a substitute for code authorization. Enforce server-side for every route, API, query, export, queued job and file download. Hiding a menu item is not authorization.

Finance boundaries:
- Central Accounts: authorised group-wide finance/HR visibility and updates.
- Cashier: assigned-institute operational collections.
- Teachers/Coordinators: minimum read-only fee indicators for own/assigned classes.
- Parents: verified children only; no internal transaction audit trail.
- Bank/gateway settlement: separate finance function.

## 6. Evidence and confidence rules

Every feature record must include unique ID, outcome, institution/role scope, evidence-backed status, repository paths (route/controller/model/migration/policy/job/view/API/test), dated competitor source URL, gap, priority, acceptance test, dependencies and release gate.

Statuses: verified-present, partial, not-found, unknown, blocked-by-external-dependency. A missing menu does not prove a feature is absent. A route, view or table alone does not prove a feature is complete. Completion requires full workflow and authorization evidence.

## 7. Delivery order

See roadmap.md. Tenant isolation, permissions, identity, audit and data integrity precede high-risk transactions. Then finance/family payments, admissions, HR/attendance, academics, assessment, mobile communication and operations. Git-first, synthetic tests, review and backup-first release.

## 8. Safeguards

- No live student/family data during research and acceptance; use synthetic data.
- No production migration, live-data import, deployment or hardware activation in this research phase.
- No guessed biometric, CCTV, GPS, SMS, calling or payment-gateway endpoints.
- No silent ledger allocation across siblings/institutions.
- No parent access to internal transaction/audit records.
- No merging research/implementation PRs without review and approval.
- No module marked complete until route-, role- and workflow-level acceptance passes.

## 9. Source register and prior artifacts

Revalidate primary sources before design lock:
- Fedena: https://fedena.com/
- Entab: https://www.entab.in/
- MyClassBoard: https://www.myclassboard.com/
- iCloudEMS: https://www.icloudems.com/
- DigiERP: https://digierp.com/
- Examezy: https://examezy.com/
- Eklavvya: https://www.eklavvya.com/
- JEENEET: https://jeeneet.org/
- WiZiAI canonical URL and Kinvo product identity/URL require verification.

Prior Tagore artifacts to reconcile, not blindly merge: PR #67 (initial market benchmark), PR #71 (exam/Olympiad audit), PR #69 (T304F and finance-role scope), and related PRs #68/#70.

## 10. Decision gate

This records the owner-provided completed research input. It does not certify exhaustive route-level auditing, current competitor claims or implementation readiness. Next technical step: systematic repository evidence pass and completion of the feature register. Then present a prioritised dependency-aware implementation plan for review. Coding and deployment remain separate gates.
