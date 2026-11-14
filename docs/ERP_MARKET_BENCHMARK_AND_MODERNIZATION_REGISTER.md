# Tagore ERP — Market Benchmark, Feature Audit & Modernization Register

**Document type:** Master discovery and modernization register  
**Repository:** 158jeet/gegok12  
**Baseline audited:** `main` branch, 14 November 2026  
**Status:** Phase 1 — discovery and benchmark; no implementation authorization implied  
**Purpose:** Compare Tagore ERP's existing capabilities and workflows with established education ERP products, record every verified gap and agreed change here, then implement the approved backlog as a coordinated programme and run full final acceptance.

---

## 0. Delivery protocol — do not skip phases

1. **Inventory:** enumerate every route, controller, page, API, job, scheduled task, import/export, role and integration in the repository.
2. **Workflow audit:** test each feature as an actual staff/student/parent task. A route, controller, dashboard tile or database table is not proof of a complete workflow.
3. **Market benchmark:** compare the relevant workflow with documented capabilities from school ERP, college ERP and coaching/testing platforms. Prefer vendor primary sources. Record date and source. Treat marketing claims as claims, not independent proof.
4. **Gap register:** record existing Tagore behaviour, evidence, competitor benchmark, gap, proposed improvement, dependencies, risk, acceptance criteria and status in this file.
5. **Scope lock:** group related changes into coherent work packages. Do not start scattered feature development while the audit is incomplete, except fixes needed to protect data/security or unblock discovery.
6. **Implementation:** implement approved work packages in dependency order. Update this register as changes are delivered; do not silently remove scope.
7. **Final acceptance:** run automated backend, frontend, API, integration, role/permission, browser, responsive, data-integrity, import/export, notification, performance and backup/restore acceptance across representative roles and institutions.
8. **Release:** no merge/deployment/live-data onboarding until explicit owner approval and all release gates pass.

**Important:** Existing functionality statements below are based on repository files and earlier code inspection, not a claim that every workflow has passed live acceptance. Unknown means inspect/test; it does not mean absent.

## 1. Benchmark methodology

### Product families selected

| Product | Segment | Why included | Public-source caveat |
|---|---|---|---|
| Fedena | School ERP / multi-school | Broad school operations, dashboards, HR, finance, import/export, online exam and multi-school | Product features may be plan/plugin dependent |
| Entab CampusCare 10X | Indian K-12 ERP | Admissions, fee, examination, holistic assessment, biometric/HR/payroll, parent/teacher/principal apps | Vendor-published capabilities; confirm in product demo |
| MyClassboard | Indian school ERP | Finance, admission CRM, student lifecycle, HR/payroll, transport, communication, alerts and branch operations | Core/add-on distinctions matter |
| iCloudEMS | College/university ERP | Student lifecycle, finance, HR, exams, digital evaluation, OBE, accreditation and multi-campus | Higher-ed reference; do not copy university-only features into schools |
| JEENEET.org | JEE/NEET test platform | Exam console, question bank, paper builder, timed tests, autosave, analytics and teacher review | Coaching/exam specialist, not a complete school ERP |
| DigiERP | College/campus ERP | Admissions, semester/exam, accounts, library, hostel, placement, accreditation and multi-campus | Vendor-published scope; verify detailed workflows |

### Evaluation rules

- Benchmark workflow depth, not the number of advertised modules.
- Compare user journey, exception handling, approvals, reporting, mobile usability, data model, integrations, security and auditability.
- Separate **Must Have**, **Should Have**, **Differentiator**, **Not Applicable**, and **Needs Owner Decision**.
- Do not clone competitor interfaces or copy proprietary content, question banks, papers or code.
- Do not add AI, biometric/proctoring, payment or messaging integrations without security, privacy, cost and operational review.
- Competitor feature not publicly documented is marked **Not documented**, never **Not supported**.

## 2. Tagore codebase inventory — first pass

### Confirmed modern Tagore controllers

- AcademicStructureController
- AdministrationController
- AdmissionsController
- CommunicationController
- DailyDiaryController
- DashboardController
- DocumentController
- ExaminationController
- FeeController
- FeeEditorController
- FeeImportController
- FeeManagementController
- FeeWalletController
- InventoryController
- LearningController
- LegacyFeeReconciliationController
- LegacyStudentMappingController
- OlympiadController
- OlympiadRegistrationController
- OnlinePaymentController
- OperationsController
- ParentDashboardController
- ParentOlympiadController
- PayrollController
- PerformanceController
- PlatformController
- ReportController
- SecurityController
- StaffController
- StudentParentMigrationController
- TaskController
- TransportController

### Confirmed modern Tagore page families

Dashboard; academic structure; administration; admissions CRM; departments; communication; diary; documents; examinations and marks/results; fees (accounts, editor, import, management, reconciliation, student, receipt, wallet); inventory; learning; Olympiad (manager, student, parent, result); operations; parent dashboard; payments; payroll/payslips; performance; platform/CCTV; reports; security; staff/leave/self-service; migration; tasks; transport.

### Legacy role route families

Admin, Accountant, Teacher, Student, Parent, Librarian, Receptionist, Payroll, Inventory/Stock, Settings, Superadmin and APIs. These must be audited for duplicated or conflicting workflows with modern Tagore routes.

### Existing test families identified

Authentication; academic structure; admissions; core-module smoke; daily diary; examinations; fee archive/import/workflow; legacy fee migration; dashboards; offline sync; Olympiad/registration/parent Olympiad; operations; performance; prototype E2E and feature tests; role access matrix; student/parent migration; tasks; lifecycle and UI blade contracts.

**Inventory limitation:** The first pass enumerates the principal Tagore code surfaces. The full route-by-route, screen-by-screen, action-by-action and API/job inventory is still required before the register can be marked complete.

## 3. Cross-product benchmark themes

### 3.1 School ERP benchmark

- Fedena publicly documents student information/admission, HR, dashboards, attendance, timetable, gradebook/examination, finance, reports, hostel, library, transport, import/export, reminders, audit, gate management, online examination and multi-school options.
- Entab documents integrated admissions/enrolment, instalment fee plans, online payment, receipts/dues/reports, holistic assessment, attendance alerts, transport tracking, HR/payroll, CPD and role-specific parent/teacher/principal mobile apps.
- MyClassboard documents admissions CRM, student information, finance, concerns, inventory, certificates, hostel, dynamic forms, visitor, transport, HR, payroll, NEP/HPC, WhatsApp/LMS, add-on alerts and branch-level dashboards. Its knowledge base documents operational fee tasks such as defaulter follow-up, due changes, concession, cheque bounce, double payments, sibling payments and receipt lookup.

### 3.2 College ERP benchmark

- iCloudEMS documents multi-campus student lifecycle, finance/fee, HR/payroll, examinations, digital evaluation, OBE/LMS, accreditation, analytics, library, hostel, transport and communication.
- Digital evaluation benchmark includes barcode-based anonymous script allocation, on-screen marking, moderation, recheck/challenge and timestamped result publication.
- College-specific requirements to assess for Tagore PG College: annual/semester structures, subject/course registration, internal and university exam records, backlog/supplementary/revaluation, credits where applicable, transcripts, affiliation returns, faculty workload, placements, alumni and NAAC/AISHE/IQAC evidence. Confirm which apply to the college before implementation.

### 3.3 JEE/NEET and Olympiad benchmark

- JEENEET.org documents exam-console patterns, timer, question palette, save/next, mark-for-review, rough sheet, readiness check, autosave/resume/auto-submit, shuffled papers, question-bank review, paper builder, scheduling, instant controlled results, leaderboards and teacher reports.
- Assessment benchmark should include secure timed delivery, reliable answer persistence, recovery after connectivity loss, configurable exam patterns, section/question rules, negative marking, question/option randomization, reviewed question bank, detailed solutions, rank/percentile, topic analytics and longitudinal student progress.

## 4. Master feature-by-feature modernization register

Status key: **AUDIT REQUIRED** = inspect and test existing workflow; **GAP TO VERIFY** = likely improvement candidate, but confirm code behaviour first; **AGREED** = user-approved requirement; **NOT IN SCOPE** = explicitly not to build without further decision.

### A. Platform, UX and role-based workspaces

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| UX-01 | Shared design system | Shared UI foundation work exists; pages have previously shown inconsistent styling | Consistent navigation, responsive tables/forms, clear states and role dashboards | Audit every page at desktop/tablet/mobile; unify components, spacing, typography, filters, tables, empty/error/loading states | P0 | AUDIT REQUIRED |
| UX-02 | Role home dashboards | Modern dashboard and legacy role portals coexist | Role-specific actionable dashboards and task queues | Verify every role's first screen, KPI correctness, shortcuts and unresolved-work queues | P0 | AUDIT REQUIRED |
| UX-03 | Profile and logout | PR #64 addresses non-clickable profile/logout across roles | All roles can edit own profile, change password, sign out securely | Verify all roles, menu accessibility, session invalidation and mobile behaviour | P0 | AUDIT REQUIRED |
| UX-04 | Global search | Search exists in individual workflows | Fast global search with role-scoped results | Assess student/staff/receipt/admission/document search and duplicate results | P1 | GAP TO VERIFY |
| UX-05 | Notifications centre | Communication and reminder surfaces exist | Central inbox, delivery/read state, preferences, retry and templates | Audit SMS/email/push/WhatsApp channel architecture, delivery logs and opt-outs | P1 | AUDIT REQUIRED |
| UX-06 | Task and approval inbox | Task module exists | Assigned tasks, due dates, escalation, approval queues | Unify tasks, pending approvals, reminders and audit links | P1 | GAP TO VERIFY |
| UX-07 | Accessibility and usability | Not yet fully measured | Keyboard support, labels, contrast, mobile touch targets | Add accessibility and responsive acceptance across all roles | P1 | GAP TO VERIFY |

### B. Organisation, institution, department and access control

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| ORG-01 | Multi-institution hierarchy | Tagore institutions and role scoping exist | Group → institution/campus → department → class/section/location | Verify canonical hierarchy and institution filters across all modules | P0 | AUDIT REQUIRED |
| ORG-02 | Central Accounts | Role exists; group-wide access requirement clarified | Group-wide cross-institution finance and employee/payroll view and authorised updates | Enforce global scope server-side without requiring local memberships; audit all writes | P0 | AGREED |
| ORG-03 | Institution Cashier | Dedicated cashier workspace added in PR #63; CASHIER role proposed | Local fee counter, paid/pending visibility, reminders, day collection and reports | Verify all activities and strict institution isolation | P0 | AGREED |
| ORG-04 | Class teacher fee visibility | New user requirement | Read-only fee status for assigned class and section | Add server-enforced academic assignment scope and approved reminder workflow | P0 | AGREED |
| ORG-05 | Coordinator grade-band fee visibility | New user requirement | All students/sections for assigned classes (e.g. classes 2–5) | Explicit academic-year/institution/class assignment; never infer from title | P0 | AGREED |
| ORG-06 | Role and permission matrix | Modern role catalogue and legacy roles coexist | Least privilege, permission-level controls and delegation | Build complete role × action × institution × department × data-scope matrix; test API and UI | P0 | AUDIT REQUIRED |
| ORG-07 | Delegation and temporary access | Not confirmed | Time-bound delegation and approval | Assess acting principal/coordinator, leave cover, expiry and audit | P2 | GAP TO VERIFY |
| ORG-08 | Audit trail | Security/audit surfaces exist | Immutable before/after actor/time/reason history | Verify all sensitive changes and exportable audit records | P0 | AUDIT REQUIRED |

### C. Admissions, student lifecycle and front office

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| ADM-01 | Enquiry CRM | Modern Admissions CRM exists | Configurable lead stages, source, follow-up, counsellor ownership and conversion funnel | Compare full lead lifecycle, reminders, conversion rates and duplicate detection | P1 | AUDIT REQUIRED |
| ADM-02 | Application and registration | Legacy Admin Admissions also exists | Online/offline application, documents, forms and status tracking | Reconcile duplicate legacy and modern admission workflows | P0 | AUDIT REQUIRED |
| ADM-03 | Admission to student onboarding | CRM conversion has depended on an existing student record; legacy approval can create accounts | Guided application → approval → student/guardian → class/year → documents → fee plan | Implement one integrated onboarding journey; safe invite/activation, no default passwords | P0 | GAP TO VERIFY |
| ADM-04 | Family/sibling linking | Student-parent migration and links exist | One guardian login, multiple children, verified relationship and contacts | Test sibling linking, duplicate guardian, split custody and parent access | P0 | AUDIT REQUIRED |
| ADM-05 | Student 360 profile | Student profile and student data surfaces exist | Unified identity, academic, attendance, fee, communication, document and history | Audit data completeness and profile timeline | P1 | GAP TO VERIFY |
| ADM-06 | Student transfer/withdrawal/TC | Certificates and lifecycle surfaces exist | Controlled transfer, withdrawal, TC, dues clearance and history | Verify approvals, balances, documents and audit | P1 | AUDIT REQUIRED |
| ADM-07 | Promotion/year rollover | Legacy CSV promotion import exists; modern workspace not confirmed | Preview, validation, next-year enrolment, fail/hold/alumni and history | Replace fragile import with safe preview/commit and conflict report | P0 | GAP TO VERIFY |
| ADM-08 | Visitor/front office | Operations/receptionist routes and visitor logs exist | Visitor pre-registration, ID, host, badge, check-in/out, alerts | Audit full visitor and gate workflow | P1 | AUDIT REQUIRED |
| ADM-09 | Appointment/call/postal | Legacy receptionist route family | Enquiry appointments, call logs, inward/outward mail and task follow-up | Verify one front-office daily workspace | P2 | AUDIT REQUIRED |

### D. Fees, cashier, finance and accounting

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| FEE-01 | Fee structure setup | Fee management/editor/import exists | Class/course fee plans, heads, instalments, effective dates and revisions | Audit structure creation, cloning, versioning and year rollover | P0 | AUDIT REQUIRED |
| FEE-02 | Fee assignment/demand generation | Fee management services exist | Bulk assignment, student-specific items, transport/hostel/misc fees | Verify preview, exclusions, pro-rata, mid-year joining/leaving and reversals | P0 | AUDIT REQUIRED |
| FEE-03 | Cashier counter | PR #63 adds dedicated cashier workspace | Fast student lookup, partial payment, receipt and daily summary | Full end-to-end payment/receipt/reprint/email acceptance | P0 | AUDIT REQUIRED |
| FEE-04 | Paid/pending/overdue reconciliation | Fee reports and reconciliation surfaces exist | Class/student/head-wise paid vs pending and collection-vs-demand | Cashier must access operational collection reconciliation at institution level | P0 | AGREED |
| FEE-05 | Fee reminders and collection follow-up | Reminder/communication systems exist | Scheduled and manual guardian reminders, follow-up state and delivery audit | Cashier can notify assigned-institution guardians; teacher/coordinator scoped follow-up | P0 | AGREED |
| FEE-06 | Teacher/coordinator collection support | No complete workflow confirmed | Teacher sees assigned section; coordinator sees all sections in assigned grades | Read-only minimum necessary balance view; no ledger/payment permissions | P0 | AGREED |
| FEE-07 | Concessions/scholarships | Fee editor and management exist | Rule-based sibling/staff/merit/need concession with approval | Verify workflow, caps, effective dates, approval and history | P1 | AUDIT REQUIRED |
| FEE-08 | Refunds/write-offs/adjustments | Must inspect exact implementation | Dual approval, reason, reversal ledger and audit | Separate from cashier collection; explicit permission | P0 | AUDIT REQUIRED |
| FEE-09 | Online payments and gateway reconciliation | OnlinePaymentController and legacy reconciliation exist | Webhook idempotency, settlement reconciliation, double-payment and refund flow | Test all payment states, callback replay, mismatch and failure recovery | P0 | AUDIT REQUIRED |
| FEE-10 | Cheque/bank transfer | Offline payment service supports modes | Cheque pending/cleared/bounced, transfer references and proof | Verify cheque lifecycle and collection reconciliation | P1 | GAP TO VERIFY |
| FEE-11 | Family/multiple-child payments | Parent/family fee features exist | Consolidated family dues/payment allocation | Test sibling allocation, partial payment and wrong-child edge cases | P0 | AUDIT REQUIRED |
| FEE-12 | Receipt/challan/invoice | Receipt and PDF/email views exist | Custom formats, bulk printing, unique sequence and resend | Audit PDF, email queue, duplicate, cancelled receipt and numbering | P1 | AUDIT REQUIRED |
| FEE-13 | Expenses/daybook/accounting | Accounts overview exists; full ledger depth unclear | Income/expense, cashbook, daybook, cost centres, approval and exports | Determine whether true accounting ledger exists or needs integration | P1 | GAP TO VERIFY |
| FEE-14 | Central Accounts reporting | Cross-institution requirement | Group consolidated and institution/department drill-down | Unified Accounts portal, filters, period comparison and export | P0 | AGREED |

### E. HR, staff, attendance and payroll

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| HR-01 | Employee master/360 | StaffController and employee models exist | Personal, employment, institution, department, role, documents and history | Audit full staff lifecycle and bulk import | P0 | AUDIT REQUIRED |
| HR-02 | Multi-institution employee visibility | Central Accounts requirement | Local employment ownership; central consolidated visibility | Employee remains institution/department mapped; central Accounts sees all employees | P0 | AGREED |
| HR-03 | T304F biometric device registry | PR #66 starts multi-device registry | Multiple devices, institutions, locations, serial/firmware, mapping, sync health | Complete vendor-compatible connector after device data and ADMS/WSDL confirmation | P0 | AGREED |
| HR-04 | Attendance ingestion/reconciliation | Attendance and device groundwork exist | Raw punches, idempotency, retries, exceptions and correction approval | Build sync queue, raw event store, mapping exceptions and audit | P0 | GAP TO VERIFY |
| HR-05 | Employee attendance/leave | Staff leave and payroll surfaces exist | Shift/roster, weekly off, holidays, leave balance, comp-off, late/half-day | Audit policy engine and biometric-to-attendance calculation | P0 | AUDIT REQUIRED |
| HR-06 | Payroll | PayrollController and payslip pages exist | Salary components, attendance, leave, advances, deductions, arrears, statutory rules | Audit payroll calculation, per-institution runs and group roll-up | P0 | AUDIT REQUIRED |
| HR-07 | Payslip/self-service | Payslip and staff self pages exist | Secure profile, payslip, tax docs and leave self-service | Test all employee role accounts and mobile usability | P1 | AUDIT REQUIRED |
| HR-08 | Recruitment/onboarding/performance | Performance and staff surfaces exist | Recruitment, offer, joining, probation, appraisal and exit | Inventory current support and prioritise gaps | P2 | GAP TO VERIFY |
| HR-09 | Payroll approval/payment | Must inspect | Draft → review → approve → payment export → close/reopen with audit | Add maker-checker and controlled period lock if missing | P0 | GAP TO VERIFY |

### F. Academics, timetable, teaching and student engagement

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| ACA-01 | Academic year/class/section/subject | AcademicStructureController exists | Effective-dated structure, promotion, transfer and subject mapping | Test all institution/board types and historical years | P0 | AUDIT REQUIRED |
| ACA-02 | Timetable and substitutions | Legacy timetable features/models exist; modern workflow unclear | Conflict-free timetable, teacher/room availability, substitution and notifications | Audit and decide modern workspace integration | P1 | GAP TO VERIFY |
| ACA-03 | Teacher daily workspace | Diary, tasks, learning and staff pages exist | Today view: timetable, attendance, lessons, homework, marks and fee follow-up | Build role-specific teacher task flow | P1 | GAP TO VERIFY |
| ACA-04 | Attendance (student) | Legacy attendance routes/models exist | Daily/period attendance, late/leave, parent alerts, correction audit | Verify modern/legacy integration and bulk marking UX | P0 | AUDIT REQUIRED |
| ACA-05 | Lesson plans/curriculum | Learning module exists; legacy lesson plan models | Annual plan, chapter coverage, teaching diary and completion analytics | Audit CBSE/RBSE/college differences | P1 | AUDIT REQUIRED |
| ACA-06 | Homework/assignments | Daily diary and legacy homework features exist | Create, assign, submit, grade, feedback, overdue tracking | End-to-end parent/student/teacher acceptance | P0 | AUDIT REQUIRED |
| ACA-07 | Parent/teacher communication | Communication and diary exist | Threaded communication, moderation, attachments and response tracking | Audit delivery/read receipts and access boundaries | P1 | AUDIT REQUIRED |
| ACA-08 | Student learning progress | Performance and learning modules exist | Subject/topic mastery, interventions and longitudinal growth | Connect tests, attendance and teacher observations | P1 | GAP TO VERIFY |

### G. Examination, Olympiad and entrance preparation

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| EX-01 | School examination | ExaminationController and marks/results views exist | Exam calendar, schedules, marks, moderation, grading and report cards | Audit CBSE/RBSE/college workflows and result approval | P0 | AUDIT REQUIRED |
| EX-02 | Olympiad event/registration | OlympiadController, registration, parent/student pages exist | Eligibility, registration, fees if applicable, schedules and result publishing | Audit full manager/student/parent journey | P0 | AUDIT REQUIRED |
| EX-03 | Question bank | Current Olympiad importer supports single-choice, max 200 per batch (previous code inspection) | Reusable, tagged, versioned bank with review | Add multiple question types, validation, versioning and bulk import | P0 | GAP TO VERIFY |
| EX-04 | Paper blueprint/builder | Not confirmed as complete | Section pattern, subject/topic/difficulty mix, marks and negative marking | Add controlled blueprint and randomised assembly | P0 | GAP TO VERIFY |
| EX-05 | Online exam player | Timed attempts and answer saving exist | Official-style palette, save/next, mark review, timer, rough sheet, accessibility | Audit mobile/desktop, server timer and attempt recovery | P0 | AUDIT REQUIRED |
| EX-06 | Reliability/anti-cheat | Server-side scoring exists; broader controls need audit | Autosave, reconnect, resume, idempotency, question snapshots and event log | Harden against refresh, network loss, duplicate submits and client clock manipulation | P0 | GAP TO VERIFY |
| EX-07 | Results/analytics | Student results and performance surfaces exist | Score, accuracy, speed, topic gaps, rank/percentile and cohort comparison | Add teacher/class/coordinator/management analytics with privacy | P0 | GAP TO VERIFY |
| EX-08 | Re-evaluation/objections | Not confirmed | Controlled answer challenge, moderation and result versioning | Add audit-ready review workflow | P1 | GAP TO VERIFY |
| EX-09 | JEE/NEET test series | Tagore Vedant Academy use case; test series/exam code exists | Full/part/chapter mocks, PYQ licensing, scheduled series, analysis and revision plan | Dedicated coaching test-series workspace | P0 | AGREED |
| EX-10 | Digital paper evaluation | Higher-ed benchmark | Anonymous barcode scripts, on-screen marking, moderation and challenge | Evaluate relevance to PG College; owner decision before build | P2 | GAP TO VERIFY |

### H. Parent and student experience / mobile

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| APP-01 | Parent portal | Parent dashboard, diary, Olympiad and fees surfaces exist | One place for multi-child, dues, attendance, results, homework and notices | Audit full parent journey and data isolation | P0 | AUDIT REQUIRED |
| APP-02 | Student portal | Student Olympiad and legacy student portals exist | Timetable, attendance, assignments, study resources, tests, results and profile | Reconcile modern and legacy experience | P1 | AUDIT REQUIRED |
| APP-03 | Android parent/teacher apps | Desired product requirement; current implementation needs audit | Secure mobile apps/PWA with push and offline-aware workflows | Decide native vs PWA after API and offline requirements audit | P1 | GAP TO VERIFY |
| APP-04 | Offline capability | Offline sync test exists | Safe queued writes, conflict resolution and sync status | Identify offline-critical workflows (attendance, fee receipts, tests) | P1 | AUDIT REQUIRED |
| APP-05 | Multi-child parent account | Parent linking exists | Sibling switch with no cross-family leakage | Test every linked child and permission boundary | P0 | AUDIT REQUIRED |
| APP-06 | Parent notification preferences | Not confirmed | Channel, language, quiet hours and consent preferences | Add preference centre and delivery history | P2 | GAP TO VERIFY |

### I. Transport, hostel, library, inventory and facilities

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| OPS-01 | Transport | TransportController and view exist | Routes, stops, vehicles, drivers, allocation, fees, attendance and live GPS | Audit complete daily workflow and parent visibility | P1 | AUDIT REQUIRED |
| OPS-02 | Hostel | Legacy hostel features exist; modern module uncertain | Room/bed allocation, attendance, mess, visitors, leave and hostel fees | Inventory legacy routes and decide modern migration | P1 | GAP TO VERIFY |
| OPS-03 | Library | Legacy library routes/models exist | Catalogue, barcode, issue/return, fine, reservation and stock | Audit and modernise if needed | P1 | AUDIT REQUIRED |
| OPS-04 | Inventory/procurement | InventoryController/view exists | Requisitions, approvals, PO, GRN, issue/return, vendor and stock ledger | Audit complete lifecycle and multi-location stores | P1 | AUDIT REQUIRED |
| OPS-05 | Assets/maintenance | Platform/security/facilities models need audit | Asset register, warranty, maintenance, depreciation and work orders | Identify if separate module needed | P2 | GAP TO VERIFY |
| OPS-06 | Gate pass/visitor/CCTV | Security, operations, CCTV and legacy gate features exist | Visitor approval, student pickup, staff gate pass, CCTV health and audit | Integrate Hikvision only through supported secure API; test event flow | P1 | AUDIT REQUIRED |
| OPS-07 | Safety/incident/infirmary | Not confirmed complete | Incident, first aid/clinic, emergency contacts, escalation and privacy | Owner decision and legal review before health-data scope | P2 | GAP TO VERIFY |

### J. Documents, certificates, reports, compliance and integrations

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| DOC-01 | Document management | DocumentController/view exists | Student/staff/institution documents, version, expiry and access controls | Audit upload, preview, expiry alerts and retention | P1 | AUDIT REQUIRED |
| DOC-02 | Certificates/TC | Legacy certificate features exist | Templates, approval, serial numbering, QR verification and issue register | Modernise document generation and verification | P1 | GAP TO VERIFY |
| REP-01 | Report centre | ReportController and report views exist | Role-scoped dynamic reports, saved filters, drill-down and exports | Build full report catalogue and data dictionary | P0 | AUDIT REQUIRED |
| REP-02 | Management analytics | Dashboard/performance modules exist | Group/institution/class comparisons, trends, alerts and drill-down | Audit KPI correctness and freshness | P1 | AUDIT REQUIRED |
| CMP-01 | Board/compliance reports | CBSE/RBSE/college contexts | Board-ready registers, UDISE+/inspection, university/NAAC/AISHE where relevant | Map required outputs institution by institution | P1 | GAP TO VERIFY |
| INT-01 | Bulk import framework | Fee import and migration tools exist | Template download, sample file, preview, row errors, duplicate detection, rollback and audit | Common reusable import centre for students, staff, fee structures, question bank, attendance and other approved records | P0 | AGREED |
| INT-02 | Bulk export | Data export surfaces exist | Filtered export with permission checks and export audit | Standard CSV/XLSX/PDF export, large-file jobs | P1 | AUDIT REQUIRED |
| INT-03 | Messaging integrations | SMS/email/push and communication exist | Provider abstraction, retries, templates and delivery callbacks | Audit provider cost, retry, failure and opt-out | P1 | AUDIT REQUIRED |
| INT-04 | Biometric T304F integration | Device registry branch exists | Multi-device, multi-location, secure ADMS/API sync | Verify actual vendor contract before enabling; no guessed endpoints | P0 | AGREED |
| INT-05 | Payment integrations | Online payment controller exists | Idempotent callbacks, settlement and reconciliation | Audit provider coverage and failure paths | P0 | AUDIT REQUIRED |
| INT-06 | API/webhooks | Tagore API routes exist | Versioned API, auth, throttling, webhook signatures and docs | Inventory every API and external consumer | P1 | AUDIT REQUIRED |

### K. Data governance, security, performance and operations

| ID | Feature/workflow | Tagore baseline / evidence | Benchmark expectation | Proposed audit/change | Priority | Status |
|---|---|---|---|---|---|---|
| SEC-01 | Institution data isolation | Scoping exists in multiple controllers; historic gaps found | Every read/write/export is tenant-scoped | Automated cross-institution negative tests for every module | P0 | AUDIT REQUIRED |
| SEC-02 | Privacy/consent | Security surfaces exist | Purpose limitation, minimal access, consent and retention | Review student/child data and DPDP obligations with legal advice | P0 | GAP TO VERIFY |
| SEC-03 | Authentication/session | Authentication tests exist | Secure password reset, MFA for privileged users, session expiry and audit | Audit role login, logout, account recovery and privileged controls | P0 | AUDIT REQUIRED |
| SEC-04 | Backup/restore | Backup-first deployment process exists | Scheduled encrypted backups, retention, restore drill and RPO/RTO | Test restore of DB/files and document evidence | P0 | AUDIT REQUIRED |
| SEC-05 | Audit/logging/monitoring | Security/operations surfaces exist | Central logs, queue health, failed jobs, uptime and alerts | Build operator dashboard and incident workflow | P1 | AUDIT REQUIRED |
| SEC-06 | Performance/scalability | Browser and Docker acceptance exists | Concurrent fee counter, attendance, imports and exams | Load test peak fee day and simultaneous mock exams | P0 | GAP TO VERIFY |
| SEC-07 | Data quality | Import/migration tools exist | Duplicate detection, validation, merge and data-quality reports | Add quality dashboard and safe correction process | P1 | GAP TO VERIFY |
| SEC-08 | Deployment/release | Backup-first pipeline exists | Reproducible release, health checks, rollback and public TLS verification | Verify full production path only after acceptance and approval | P0 | AUDIT REQUIRED |

## 5. Agreed non-negotiable business rules

1. Central Accounts is group-wide across all institutions and departments; it can view and make authorised updates across institutions.
2. Institution Cashier is local to assigned institution(s); it can collect fees, see paid/pending/overdue dues, perform operational collection reconciliation, issue receipts, send approved fee reminders and report/escalate pendencies.
3. Class Teacher sees only fee status for their assigned class and section; Coordinator sees all sections/students across explicitly assigned classes (e.g. grades 2–5). These roles are read-only for financial ledgers and cannot post payments.
4. Fee reminder delivery, sender, template and follow-up status must be audited; respect communication preferences and rate limits.
5. Employee employment assignment remains institution/department-specific. Central Accounts sees the consolidated employee, attendance and payroll picture. T304F employee mapping is per device and institution.
6. No attendance deductions are made directly from raw biometric punches; reconcile and approve exceptions first.
7. No live school/student database is required for development. Use synthetic fixtures and disposable CI databases until ERP completion and explicit approval.
8. No default/shared passwords for newly admitted students or guardians. Use secure activation/invitation.
9. No merge or production deployment without explicit user approval.
10. No feature is marked complete merely because its page or route exists.

## 6. Cross-cutting bulk import standard

Every eligible import must provide:
- Downloadable blank template and a populated sample file adjacent to the upload control.
- Template version, field definitions, required/optional fields, accepted formats and example values.
- Upload → parse → preview → row-level validation → duplicate/conflict report → explicit confirmation → transactional commit.
- Idempotency or duplicate prevention; safe update mode vs create mode; dry-run mode.
- Institution/year scoping and server-side permission checks.
- Row-level downloadable error report; successful/failed counts.
- Import batch ID, actor, timestamp, source filename, checksum and audit trail.
- Rollback or compensating correction path where safe; never silently partially import.
- Large imports processed asynchronously with progress and retry.
- Never include real student/staff data in sample templates.

Initial import targets: student records, staff records, fee structures, fee assignments/demands, question bank, attendance/device mappings, academic structure, transport allocation, inventory and approved historical migrations.

## 7. Cross-cutting acceptance standard

For every workflow:
- Positive and negative tests for every applicable role.
- Institution and academic-year isolation, including cross-institution denial.
- Complete create/read/update/delete or lifecycle transition where applicable.
- Validation, empty, duplicate, partial, error, retry and concurrency states.
- Browser acceptance at desktop, tablet and mobile breakpoints.
- Keyboard/accessibility and usable feedback.
- Email/SMS/push/WhatsApp delivery or provider-mocked acceptance.
- Import/export correctness and audit trail.
- Data integrity and financial reconciliation.
- Performance tests proportional to expected peak load.
- Synthetic-data reset/cleanup and no production credentials.
- Evidence captured in CI and linked to the register.

### Final role acceptance roster

- Group Owner
- Central Accounts (no institution membership required)
- Institution Principal
- Institution Cashier
- Coordinator with explicit grade-band assignment
- Class Teacher with explicit class/section assignment
- HR
- Teacher
- Parent with multiple linked children
- Student
- Accountant/legacy role where still supported
- Receptionist/front office
- Librarian/transport/inventory roles where modules are retained

## 8. Work packages — implementation only after audit and scope lock

| Package | Scope | Dependencies | Exit gate |
|---|---|---|---|
| WP-00 | Full route/screen/API/job/role inventory and workflow audit | None | Every route and screen classified; gaps and duplicates recorded |
| WP-01 | Design system and role workspace consistency | WP-00 | All pages responsive and consistent; no dead controls |
| WP-02 | Identity, roles, group/institution/class scoping | WP-00 | Role × scope matrix and cross-tenant tests pass |
| WP-03 | Admissions, student lifecycle and promotion | WP-02 | Enquiry-to-enrolment and year rollover pass |
| WP-04 | Fee engine, cashier, collection follow-up and Accounts | WP-02 | End-to-end payment and dues reporting reconcile |
| WP-05 | HR, T304F, attendance and payroll | WP-02, vendor contract for live sync | Multi-device sync/reconciliation/payroll tests pass |
| WP-06 | Academic operations, teacher/parent/student portals | WP-02 | Daily teaching/learning workflows pass |
| WP-07 | Assessment/Olympiad/JEE-NEET | WP-02, WP-06 | Secure exam delivery, scoring and analytics pass |
| WP-08 | Transport, hostel, library, inventory, gate/security | WP-02 | Each retained module passes complete workflow |
| WP-09 | Reports, compliance, integrations and import centre | WP-02 and relevant modules | Templates, imports, exports and reports reconcile |
| WP-10 | Security, performance, backup/restore and release readiness | All work packages | Full acceptance and explicit release approval |

## 9. Open owner decisions (do not assume)

- Which exact Tagore PG College university/affiliation workflows and accreditation reports are required.
- Whether parent fee reminders should be sent directly by teacher/coordinator or be submitted as a request for cashier dispatch; default recommendation is approved templates with audit and institution policy.
- Which communication providers/channels are approved and budgeted (SMS, WhatsApp Business, email, push).
- Whether online exams need any proctoring; default is no invasive webcam/biometric proctoring without explicit approval and privacy review.
- Which mobile workflows must function offline and whether a PWA is sufficient.
- Whether statutory payroll calculations (PF/ESI/TDS/gratuity) are handled in ERP or via an accounting/payroll integration; confirm applicability and professional review.
- T304F serial numbers, firmware, network topology, ADMS/cloud/API entitlement and vendor WSDL/API details will be supplied later; registry and simulator can be prepared meanwhile.

## 10. Public benchmark sources

Vendor primary sources, reviewed 14 November 2026:

1. Fedena feature tour — https://fedena.com/feature-tour
2. Fedena pricing/modules — https://fedena.com/pricing-and-plans
3. Fedena employee/teacher login and biometric attendance — https://fedena.com/feature-tour/employee-teacher-login
4. Entab CampusCare 10X — https://www.entab.in/school-erp-software.html
5. Entab parent/teacher/principal mobile apps — https://www.entab.in/mobile-apps.html
6. Entab payroll and attendance integration — https://www.entab.in/entab-blog-payroll.html
7. MyClassboard school management modules — https://www.myclassboard.com/school-management-software/
8. MyClassboard finance and fee management knowledge base — https://kb.myclassboard.com/finance/fee-management/
9. MyClassboard automated alerts — https://cdndatastatic.myclassboard.com/product_updates/Feature-Enhancement-of-Automated-Alerts-Module.pdf
10. iCloudEMS platform and connected modules — https://icloudems.com/products/
11. iCloudEMS digital evaluation — https://icloudems.com/solutions/digital-evaluation/
12. iCloudEMS accreditation — https://icloudems.com/solutions/accreditation-compliance/
13. JEENEET.org exam and institute features — https://jeeneet.org/features and https://jeeneet.org/for-institutes
14. DigiERP campus ERP — https://digierp.in/

Secondary discovery sources (use to identify vendors/topics, not as sole proof):
- https://appadvisor.in/education-software
- https://appadvisor.in/education-software/college-erp-software
- https://www.aveoninfotech.com/blog/what-is-campus-erp

---

## 11. Audit completion tracker

| Audit area | State | Evidence/next action |
|---|---|---|
| Modern Tagore controllers and view families | PARTIAL | Listed in section 2; inspect each route, controller action, form, button and query |
| Legacy role portals | NOT STARTED | Compare legacy Admin/Accountant/Teacher/Student/Parent/Librarian/Receptionist/Payroll with modern modules |
| Role × permission × scope matrix | NOT STARTED | Enumerate every middleware, policy and controller guard |
| API endpoints/webhooks | NOT STARTED | Inventory routes/tagore_api.php and external callbacks |
| Jobs/queues/schedulers | NOT STARTED | Inventory jobs, listeners, notifications, commands and cron schedules |
| Database entities/migrations | NOT STARTED | Map tables and ownership keys to each module |
| UI screen/action acceptance | NOT STARTED | Browser-driven workflow inventory, including non-clickable/dead controls |
| Competitor workflow matrix | INITIAL | Primary sources collected; map feature-by-feature and verify details |
| Gap and change register | INITIAL | This file is the canonical register; update with exact code evidence |
| Final acceptance | NOT STARTED | Only after all approved implementation packages are complete |

**Canonical rule:** All new findings and agreed changes must be added to this file (or a linked module-specific register) in Git before implementation begins. Do not let the backlog exist only in chat.


## 12. Repository baseline inventory — verified 14 November 2026

A recursive Git tree inspection of `main` (commit `a5806356ad317c8126845a6512b80575376a7f63`) establishes the following measurable baseline. These are file counts, not claims that every file is active or production-ready.

| Area | Count on main | Audit implication |
|---|---:|---|
| Tracked files | 7920 | Full repository surface is materially larger than the modern Tagore module alone |
| Route definition files | 19 | All role and API route groups must be inventoried, not only `tagore.php` |
| HTTP controller files | 281 | Controller count alone does not establish route coverage or workflow completeness |
| Blade/PHP view files | 633 | Large legacy UI surface; duplicates, stale pages and shared layouts require classification |
| Database migrations | 150 | Schema audit must include legacy and Tagore tables, ownership keys and migration order |
| Model files | 123 | Map relationships, casts, scopes, tenant keys and lifecycle state |
| Policy files | 22 | Policy coverage must be compared with route middleware and controller guards |
| Feature/unit test files | 31 | Existing test count is not a substitute for role/workflow/action coverage |
| Console command files | 36 | Scheduler and command side effects require audit |
| Job files | 0 | Queue processing, retries, idempotency and failure handling require audit |
| Listener files | 30 | Event-driven behavior and duplicate notification risks require audit |
| Notification files | 4 | Channel, preference, retry and delivery audit required |

### 12.1 Route surface counts

The following counts are occurrences of route-related declarations detected in each file (including middleware/prefix/name declarations in the raw count). They are useful for sizing the audit, not exact unique endpoint counts. Commented-out routes and grouped declarations mean a framework-generated route manifest remains the authoritative next step.

| Route file | Declaration lines detected |
|---|---:|
| `routes/tagore.php` | 174 |
| `routes/tagore_api.php` | 2 |
| `routes/web.php` | 32 |
| `routes/api.php` | 107 |
| `routes/admin.php` | 503 |
| `routes/accountant.php` | 50 |
| `routes/teacher.php` | 255 |
| `routes/student.php` | 77 |
| `routes/payroll.php` | 51 |
| `routes/librarian.php` | 52 |
| `routes/receptionist.php` | 118 |
| `routes/setting.php` | 22 |
| `routes/teacherapi.php` | 134 |
| **Total across listed files** | **1577** |

Notable findings:
- The modern `routes/tagore.php` alone contains 174 route-related declaration lines; `routes/tagore_api.php` currently exposes only a small authenticated dashboard API route group. Mobile/API parity must therefore be assessed against the legacy `api.php` and `teacherapi.php` surfaces.
- The legacy route surface is substantial: Admin (503 declaration lines), Teacher (255), Receptionist (118), Student (77), Accountant (50), Librarian (52), Payroll (51) and Settings (22). These must be mapped to the modern Tagore workspaces before any legacy portal is retired or duplicated.
- The repository contains 633 view files versus 31 test files. This ratio is not a quality score; it highlights the need for route-to-view-to-action traceability and targeted browser acceptance.
- The current tree includes both modern Tagore and legacy role controllers/views. Treating the modern dashboard as the whole ERP would omit important operational workflows.

### 12.2 Branch topology and integration risk — verified 14 November 2026

Open modernization work is not a flat set of independent branches. The current PR bases form this dependency chain:

```text
main
├── #63 cashier counter
│   └── #64 profile/logout controls
│       └── #65 biometric + reusable import specification
│           └── #66 T304F multi-location registry
├── #62 admissions CRM workspace
├── #61 shared UI foundation
└── #67 market benchmark + modernization register
```

This is the PR base topology, not proof that all changes are conflict-free or ready to merge. In particular:
- PR #65 currently reports a dirty/non-mergeable state against its parent branch; inspect and resolve its branch conflicts before treating it as an integration candidate.
- PR #66 is based on #65 and must be revalidated after #65 is reconciled.
- PR #64 is based on #63; test and review the combined cashier/profile path rather than treating either PR's green CI as standalone integration proof.
- Independent PRs #61 and #62 may overlap shared layouts, navigation or role routes; compare changed files and acceptance coverage before sequencing them.
- Older open PRs (#60, #59, #56, #52, #49, #42, #38, #33, #31 and deployment fixes) also need triage against the canonical register. Do not merge stale work merely to reduce the open-PR count.

### 12.3 Audit execution order from this baseline

1. Generate the framework route manifest in CI (including method, URI, name, action and middleware) and store a machine-readable artifact.
2. Join every route to controller action, view/API response, middleware/policy, role and institution/class/year scope.
3. Identify unreferenced controllers/views, duplicate route names, dead links, GET routes with write side effects, missing authorization and broad/unscoped model lookups.
4. Inventory jobs, listeners, notifications, scheduled commands and external callbacks; record retry/idempotency and audit behavior.
5. Map migrations/models to institution, academic year, class/section, employee, guardian/family and financial ownership.
6. Build one representative synthetic acceptance journey per distinct permission profile, then add negative cross-institution and cross-class tests.
7. Update the feature register with exact file/line evidence and status: implemented + verified, partial, missing, duplicate, unsafe, or owner decision.

**Scope note:** This snapshot is a verified repository-tree baseline only. It does not yet claim controller-level security review, complete endpoint counts, UI click coverage, or independent competitor feature verification.

### 12.4 Integration repair and first code-review findings — 14 November 2026

The stacked biometric/profile branch chain was repaired without merging or discarding the original branches:

- PR #64 profile test had malformed doubled namespace separators in tests/Feature/TagoreProfileSelfServiceTest.php. Corrected the PHP namespace/imports and the escaped Blade-heading assertion on the PR #64 branch (commit 6e2f1708).
- PR #65 had diverged from its current base because its test-file change was based on an earlier profile-test commit. Its merge state was dirty even after correcting the malformed namespaces on the old branch.
- Created clean replacement PR #68, based on the corrected PR #64 branch, containing the biometric/import specification.
- Ported the nine-file T304F/finance change set to clean replacement PR #69, based on PR #68. The replacement branch is cleanly based on its parent; the original PRs #65 and #66 remain open and must be treated as superseded until their disposition is decided.
- The first port exposed malformed doubled namespace separators in the three new biometric route controller references in routes/tagore.php. Corrected those route references on PR #69.
- The biometric registry migration initially used cascading foreign-key deletion from institutions/devices into device mappings and raw punch records. Changed institution/device references to restrict deletion so retiring a device or institution cannot cascade-delete biometric evidence. Employee deletion from punch rows remains nullable to preserve the raw event.

**Open security/functional review findings (not yet resolved):**

1. BiometricDeviceController::mapEmployee currently verifies users.school_id against the institution's school_id, but does not independently prove that the selected user is an active employee (rather than another user type) or has the required employment assignment. Inspect the authoritative employee/HR relationship before accepting this mapping workflow.
2. The fee controller's current authorizeStudent checks staff institution membership but does not demonstrate class/section assignment checks for TEACHER and COORDINATOR. This does not yet satisfy the agreed class-teacher and coordinator fee visibility boundaries.
3. The fee Accounts workspace authorises COORDINATOR, but the visible query scope is institution-level. Coordinators must instead receive only read-only balances for their explicitly assigned classes/sections; they must not inherit institution-wide finance summaries.
4. Cashier must remain institution-scoped; Central Accounts remains group-wide. The role check changes must be validated against every fee read and write path, not just the dashboard/cashier entry points.
5. The biometric registry is setup-only: sync defaults off, no live device adapter or scheduler is implemented, and no real device calls have been made. Vendor model/firmware/API contract confirmation remains a commissioning prerequisite.

### 12.5 Current clean integration sequence

main
└── #63 cashier
    └── #64 profile/logout (namespace/test correction committed)
        └── #68 biometric + import specification (clean replacement for #65)
            └── #69 T304F registry and finance-role port (clean replacement for #66)

PR #68 and #69 are draft and unmerged. The old #65/#66 branches are not the clean integration path. Do not merge any part of the chain until CI and the above scope/security findings are reviewed. No deployment or live-data mutation.
### 12.6 CI compatibility and validation findings — 14 November 2026

- The first MySQL 8.4 migration smoke for the T304F registry failed because Laravel's generated unique-index name for (device_id, device_user_id) exceeded MySQL's 64-character identifier limit. Replaced it with the explicit short name tagore_bio_map_device_user_unique (commit 38bec3bc).
- The initial v1 prototype validation is therefore a confirmed failure, not a pass. Its failure is isolated to migration index naming at migration line 34; v2 validation branches were created from the corrected commit.
- CI branch routing is now understood: ERP CI and Full ERP Acceptance run on work/** pushes; Tagore Prototype runs on tagore/** pushes; the PR workflows are configured for pull requests targeting main. The clean stacked PRs #68/#69 do not receive those PR checks automatically because their bases are feature branches.
- A work/** push also triggered the configured read-only live-site sweep against the deployed HTTPS host. The run completed successfully: all eight dedicated role accounts were present and 18 desktop/mobile role acceptance tests passed. The suite logged in and browsed routes; it did not submit business forms or mutate fee/student/staff records.
- To avoid repeating that side effect for temporary CI branches, PR #70 removes work/** from the live-acceptance push trigger while preserving main pushes and manual dispatch. A work/** validation branch now carries that trigger guard; the tagore/** branch does not invoke the live sweep.
- v2 validation is running against commit 38bec3bc (plus the workflow trigger guard on the work branch). Do not mark PR #69 accepted until ERP CI, full acceptance and Tagore prototype/migration smoke all complete successfully.

Current PR sequence remains #63 → #64 → #68 → #69. PR #70 is a separate workflow-safety change. PR #65/#66 are stale predecessor branches, not the clean integration candidates.

## 13. Owner-approved scope additions — 14 November 2026

The following are permanent backlog requirements, not informal suggestions. They must be benchmarked, audited against existing code, assigned traceability IDs, and accepted before implementation. Reuse/consolidate existing workflows instead of creating parallel modules.

### 13.1 Parent identity and family portal

| ID | Requirement | Target behaviour and acceptance criteria | Priority |
|---|---|---|---|
| FAM-01 | Canonical Parent ID | One distinct Parent ID and one parent account/profile per guardian. One parent application can register or link multiple children across Tagore institutions. Duplicate guardian records must be detected and reviewed, not silently merged. | P0 |
| FAM-02 | Unified parent portal | Parent signs in once and switches between linked children. Each child’s permitted profile, attendance, results, fees, diary, notices and documents are accessible from the parent profile. | P0 |
| FAM-03 | Student identity vs login | Keep each child’s distinct academic/student identity and history, but do not require a duplicate parent/student application or separate student login. Student login can be enabled by age/institution policy without creating a second student record. | P0 |
| FAM-04 | Relationship security | Every API, page, export and file download enforces verified parent-child links server-side. Support multiple guardians, multiple children, revoked links, custody restrictions, sibling institution differences and audit history. | P0 |
| FAM-05 | Safe migration | Inventory existing parent/student accounts, map duplicates for human review, preserve safe credentials, use invitation/activation rather than default passwords, and provide reconciliation/rollback. No live migration before approval. | P0 |

### 13.2 Student 360° profile

| ID | Requirement | Target behaviour and acceptance criteria | Priority |
|---|---|---|---|
| STU-360-01 | Unified student profile | A single student 360° view and chronological journey covers identity, class/year history, attendance, results, report cards, diary/homework, activities, documents and approved communications. | P0 |
| STU-360-02 | Achievements and participation | Record academic, sports, cultural, leadership, community service, club, competition, Olympiad and other participation/achievements with date, level, organiser, outcome and approving staff. Distinguish participation from awards. | P1 |
| STU-360-03 | Certificates and portfolio | Approved certificates, commendations, badges and evidence can be viewed/downloaded by authorised parent/student and staff. Include file validation, malware scanning, retention and access audit. | P1 |
| STU-360-04 | Results and progress | Display approved examination results, subject-wise trends, term/year summaries, Olympiad and JEE/NEET results where relevant. Published results are versioned; corrections retain reason and history. | P0 |
| STU-360-05 | Attendance and engagement | Show daily/period attendance, punctuality and approved summaries; link to diary and interventions. | P0 |
| STU-360-06 | Visibility/privacy | Parent/student see only their own linked child. Staff have role-scoped access. Health, safeguarding, discipline and sensitive wellbeing notes require separate permissions and access logs. | P0 |
| STU-360-07 | Portfolio export | Searchable timeline and controlled portfolio/report export; exports inherit all relationship and document permissions and log downloads. | P2 |

### 13.3 Timetable and teacher substitution

| ID | Requirement | Target behaviour and acceptance criteria | Priority |
|---|---|---|---|
| TTB-01 | Principal/coordinator timetable | View by institution, class, section, subject, room and teacher with clashes, vacant periods and workload. | P0 |
| TTB-02 | Automatic substitution | Approved leave/absence triggers proposed substitutions. Candidate ranking considers subject fit, availability, timetable clashes, workload limits, campus/shift and fair distribution. Never silently assign a conflicting/unavailable teacher. | P0 |
| TTB-03 | Manual substitution | Principal/coordinator can select or replace a substitute. Overrides show conflicts and require a reason where policy is overridden. | P0 |
| TTB-04 | Publication and audit | Preserve original timetable and substitution history; notify substitute and affected class/staff, and update permitted parent/student views. | P1 |
| TTB-05 | Coverage dashboard | Show unfilled periods, substitution load and recurring coverage gaps, scoped to institution/assigned classes. | P1 |

### 13.4 NEP-aligned report cards / HPC

| ID | Requirement | Target behaviour and acceptance criteria | Priority |
|---|---|---|---|
| HPC-01 | Modern report-card engine | Configurable holistic progress card (HPC-style), not marks-only. Validate applicable current CBSE/RBSE and official NEP/framework requirements before claiming compliance. | P0 |
| HPC-02 | Competency/outcome assessment | Subject competencies, learning outcomes, formative/summative evidence, rubrics, descriptive feedback and configurable grade bands. | P0 |
| HPC-03 | Holistic development | Include co-scholastic, arts, physical education, skills, projects, student self/peer reflection and other applicable domains. Sensitive wellbeing information remains restricted. | P1 |
| HPC-04 | Review and publication | Teacher entry → subject/class review → coordinator/principal moderation → approval → parent/student publication. Published snapshots are locked; corrections are versioned with reason/audit. | P0 |
| HPC-05 | Board/stage templates | Separate configurable templates by board, stage, class and academic year; academic leadership signs off mapping to current official guidance. | P0 |
| HPC-06 | Progress analytics | Longitudinal competency trends and learning-gap interventions; do not treat descriptive competency measures as directly comparable numeric exam scores. | P1 |

### 13.5 Parent social feed and one-tap contacts

| ID | Requirement | Target behaviour and acceptance criteria | Priority |
|---|---|---|---|
| PAR-ENG-01 | Official social/news feed | Parent portal displays institution-approved Tagore social-media updates and school announcements. Prefer supported official APIs/embeds or a controlled first-party feed; never scrape or store social passwords. | P1 |
| PAR-ENG-02 | Feed governance | Configure official accounts by institution, audience targeting, moderation, pin/archive, publication window and resilient cached/first-party fallback. Third-party failure must not break the portal. | P1 |
| PAR-ENG-03 | One-tap department calling | Parent can tap to call Principal, Fees, Coordinator, Transport In-charge and other configured departments without manually dialling. Mobile uses a telephone action; desktop offers an appropriate contact fallback. | P1 |
| PAR-ENG-04 | Contact directory security | Route contacts by the child’s institution/campus, verify numbers, maintain office hours/fallbacks, restrict who can edit and do not expose staff personal numbers by default. | P1 |

### 13.6 Department-wise work management

| ID | Requirement | Target behaviour and acceptance criteria | Priority |
|---|---|---|---|
| WORK-01 | Owner department workspace | Owner portal groups work by department and institution, with department leads and group-wide roll-up/drill-down. Avoid a flat undifferentiated task list. | P0 |
| WORK-02 | Department detail | Show objectives, projects, tasks, accountable owner, assigned staff, deadlines, blockers, dependencies, evidence and configured KPIs/budget. Reuse the existing task store where possible. | P0 |
| WORK-03 | Role-specific views | Department leads see their department; institution heads see authorised institutions; owner sees group-wide roll-up; staff see assigned work. Enforce scope server-side on lists, detail, exports and mutations. | P0 |
| WORK-04 | Escalation | Highlight overdue, blocked and unassigned work with configurable reminders, SLAs, escalation chain and immutable status history. | P1 |
| WORK-05 | Performance reporting | Compare department progress and drill into evidence without confusing activity counts with outcomes. | P2 |

### 13.7 Audit, benchmark and acceptance gates

1. Audit current parent/student models, guardian relationships, account creation, all parent/student routes/APIs, student profile pages, report-card engine, timetable, leave approvals, substitution, communication/social integrations and task/work models before implementation.
2. Build a field-by-field Student 360° source map: source module, author/approver, timestamp, academic year, visibility, retention and correction history.
3. Create synthetic family fixtures: one guardian with multiple children, multiple guardians for one child, siblings in different institutions, revoked guardian access, duplicate candidates and custody restrictions.
4. Test substitution with leave approval, subject fit, unavailable teachers, timetable clashes, workload caps, manual override, notifications and audit.
5. Map HPC templates to current official board/framework sources and record source/version/date; do not assert compliance without academic sign-off.
6. Confirm social platform API terms, permissions, costs and resilience before selecting a provider. Feed is supplementary; school notices must remain available independently.
7. Test one-tap calls for institution routing, mobile/desktop fallback, verified contact updates and prevention of private-number exposure.
8. Test owner/department work views for cross-institution leakage, drill-down, assignments, overdue escalation and audit.
9. Use synthetic data only. No live family/student migration or external social/device integration before explicit owner approval and release gates.

### 13.8 New audit tracker

| Area | Evidence required before implementation is ready |
|---|---|
| Parent/family | Existing models, duplicate patterns, relationship constraints, route/API inventory, safe migration and rollback |
| Student 360° | Field/source map, visibility matrix, timeline coverage, document security and parent/student journey |
| Timetable/substitution | Timetable schema, workload rules, leave trigger, conflict engine, manual override and notification tests |
| HPC/report cards | Current official board mapping, rubric model, moderation/publication/versioning and sample acceptance reports |
| Social feed | Supported API/embedding options, terms/cost, authentication, moderation, audience and fallback |
| One-tap contacts | Institution/department directory, ownership, mobile/desktop behaviour and privacy tests |
| Department work | Department/institution hierarchy, task model, scope matrix, owner roll-up and escalation tests |

These requirements extend the existing admissions, parent/student, academic operations, assessment, communications, platform UX and work-management work packages. Preserve the IDs above as traceability keys in implementation PRs and final acceptance.


### 13.9 Code-audit findings and corrections — 14 November 2026

The following were found by inspecting the clean T304F/finance branch, not inferred from the feature descriptions:

- **Fee access:** the fee controller previously authorised TEACHER/COORDINATOR by institution membership alone. A coordinator could reach the institution-wide Accounts summary, recent payments and family balances. Corrected on the clean integration branch: coordinators now require explicit `tagore_user_scopes` entries with `scope_type=standard_link` and the `fee.view` permission; their Accounts aggregates, recent payments and family balances are filtered to students in those assigned standard links for active academic years. Individual student fee/receipt access is checked against the same active-year class scope. Class teachers are restricted to their own `standards_link.class_teacher_id` assignments. Missing scope fails closed.
- **Biometric employee mapping:** the previous mapping accepted any non-deleted user in the same school, including potentially student/parent accounts. Corrected on the clean integration branch: mapping now requires a recognised legacy staff user group or an active Tagore staff role, and the same-school check remains.
- **Unverified vendor endpoint:** the registry previously prefilled an assumed Realtime API endpoint. Removed the hardcoded endpoint and changed new devices to `manual_config` with a null endpoint. Sync remains disabled until the owner supplies and verifies the actual model/firmware/API contract and connection details.
- Added feature tests for coordinator denial without explicit class fee scope and rejection of biometric mapping to a student account. These tests are awaiting CI execution.
- **Important limitation:** these corrections do not yet constitute a complete permission audit. Verify all cashier/central Accounts read/write paths, scope assignment lifecycle, active academic-year edge cases and employee-role coverage before marking finance/biometric acceptance complete.


## 14. Family-wide fee centre — owner-approved requirement

### FAM-FEE-01 — One parent, one consolidated fee dashboard and checkout

**Decision:** Parents must not have to switch the active child to review or pay fees. From the parent profile, show a consolidated family fee centre for every verified linked child and every eligible institution/account.

| ID | Requirement | Target behaviour / acceptance | Priority |
|---|---|---|---|
| FAM-FEE-01 | Family-wide fee overview | One screen lists every linked child's name, student ID, institution, class/year, fee heads/instalments, total payable, paid, outstanding, due dates, overdue status and recent payments/receipts. | P0 |
| FAM-FEE-02 | Cross-child selection | Parent can select dues for one or more children from the same family dashboard without switching child profiles. | P0 |
| FAM-FEE-03 | Editable payment amount | Parent can enter a partial amount per selected child/fee item, including different amounts for different children, up to each eligible outstanding amount. The payable liability itself cannot be edited. | P0 |
| FAM-FEE-04 | One checkout | Selected allocations are submitted in one family checkout/payment intent, where gateway and institution configuration permit. The parent sees a final child-wise allocation preview before confirming. | P0 |
| FAM-FEE-05 | Correct ledger allocation | Successful payment creates the correct child-wise fee ledger allocations under each student ID and institution. Never post the full family payment to one sibling. Produce child-wise receipts plus a family-level transaction summary. | P0 |
| FAM-FEE-06 | Partial, advance and exclusions | Support partial payments; define explicit treatment for excess/advance credit and fees that cannot be paid online. Do not silently apply excess to another child or unrelated fee head. | P1 |
| FAM-FEE-07 | Atomicity/idempotency | One payment intent has a unique idempotency key and immutable allocation snapshot. Gateway retries/webhooks cannot duplicate or misallocate money. On failure, no fee ledger is marked paid. Handle partial gateway/settlement outcomes through a controlled reconciliation queue, not silent partial posting. | P0 |
| FAM-FEE-08 | Institution/payment boundaries | Where siblings attend different institutions, aggregate only if the institutions are configured under the same authorised payment/merchant settlement arrangement. Otherwise present one family dashboard with clearly separated checkout groups and explain why. | P0 |
| FAM-FEE-09 | Permissions and history | Parent sees only verified linked children; all child-wise history and receipts are accessible from the family profile. Staff ledgers remain student/institution-scoped. | P0 |

**UX target:** Parent dashboard → Family Fees → all children shown together → choose fee items/partial amounts → review allocation by child and institution → one eligible checkout → success screen with family summary and separate child-wise receipts. No forced child-switching.

**Accounting model:** Preserve each student's fee obligation and ledger as the source of truth. Introduce/confirm a family payment intent/header plus child-wise allocation rows. The gateway transaction is not itself the fee ledger. Every allocation must reference a specific student, institution, fee obligation/instalment and amount. Reconcile allocated total exactly to captured amount. A family payment may span multiple ledgers but must never collapse student balances into a shared family balance.

**Safety/edge cases:** simultaneous payment attempts from two parent devices; two guardians paying same dues; partial allocation; one sibling already paid; fee demand changes during checkout; sibling in another institution; gateway timeout after debit; duplicate webhook; refund/reversal; academic-year rollover; custody/access revocation while checkout is open. Freeze a time-bound allocation snapshot, revalidate outstanding amounts at final capture/posting and route conflicts to reconciliation without double collection.

### 14.1 Public-market comparison for this workflow

- Entab publicly documents a single parent login with sibling details accessible from that account, and states that siblings do not need separate logins. Its parent app also exposes fee due/paid details and online payment. Public documentation reviewed does **not** establish that a parent can allocate one combined checkout across multiple siblings with independently editable partial amounts. Sources: https://www.entab.in/services.html and https://www.entab.in/mobile-apps.html.
- MyClassboard documents sibling accounts in one parent app, but its own knowledge base contains a sibling-payment issue instructing parents to pay one sibling, log out, then log in again for the second sibling to avoid the payment being credited to one sibling. This is evidence of a documented operational limitation in that support scenario, not proof that every current MCB deployment behaves identically. Sources: https://kb.myclassboard.com/how-to-add-sibling-account-in-mcb-parent-app/ and https://kb.myclassboard.com/fee-management/ (see “Siblings Payment Issue – All payments credited to a single sibling”).
- Fedena documents parent/student online fee payment, fee due/paid details and student-wise fee profiles. Public material reviewed does not confirm a single multi-sibling payment allocation workflow. Sources: https://fedena.com/feature-tour/school-fees-management-system and https://support.fedena.com/support/solutions/articles/211497-how-can-i-view-fee-payment-details-in-a-student-s-profile-.
- Therefore the target is not merely sibling visibility. It is **family-level consolidated payment with a correct, immutable allocation ledger**. Treat this as a Tagore differentiator candidate, subject to a live product demo/contract validation of competitors rather than a definitive claim that no competitor offers it.

## 15. Expanded public-vendor benchmark matrix — evidence reviewed 14 November 2026

**Evidence convention:** “Documented” means the vendor's public pages/support material reviewed describes the capability. “Not verified” means the reviewed public sources did not establish the exact workflow; it must not be interpreted as the vendor lacking the feature. Marketing statements are not independent product testing. Plan/add-on availability and configuration need vendor confirmation.

| Capability | Fedena | Entab CampusCare 10X | MyClassboard | iCloudEMS | DigiERP | JEENEET.org |
|---|---|---|---|---|---|---|
| Core student record / lifecycle | Documented SIS, profile, attendance, achievements, activities and alumni | Documented student information and integrated parent/student app | SIS core module; achievements add-on documented | Documented lifecycle enquiry→admission→fees→academics→placement→alumni, 360° profile | Documented one student record across admissions, fees, academics, hostel, placement/alumni | Exam-specific student profile and progress; not a full institutional SIS |
| Parent portal / multiple children | Parent login and child academic/fee views documented; family-wide single checkout not verified | Sibling details under one login explicitly documented | Sibling account add flow documented via matching parent mobile; payment workflow caveat below | University lifecycle/communication documented; K-12 multi-child parent portal not verified | Parent portal documented; sibling UX detail not verified | Parent account links to child with student approval; exam/progress-focused |
| Family consolidated payment | Not verified in public sources reviewed | Not verified in public sources reviewed | Public KB documents sibling payment misallocation scenario and instructs separate sibling logins for payment in that support case | Family multi-child payment not relevant/verified for higher-ed product | Fee payments documented against student record; family-wide checkout not verified | Not a school family fee ERP |
| Fees / collections | Fee structures, online payments, receipts, advance fees, defaulters, reminders and finance reports | Online payments, dues, receipts, instalments, defaulter analytics, multi-branch dashboards | Fee counter, multiple payment modes, due/payment alerts, reports, reconciliation and sibling-payment support article | Instalment plans, fee collection, reconciliation and automated reminders | Online/counter/cheque/transfer, scholarships, instalments, receipts and reminders | Exam subscription/purchase payments, not institutional fee management |
| Admissions | Student admission, enquiry/registration modules | Registration/application forms and multi-level approvals documented | Admissions core; CRM add-on documented | Connected admissions funnel and conversion analytics | Online application, entrance scoring, seat allotment and enrolment | Institute exam enrolment/scheduling, not general admissions |
| Attendance / safety | Attendance module documented | Attendance alerts, leave requests, bus tracking | Attendance, transport and visitor modules listed | Student attendance and lifecycle analytics | Attendance against enrolment, transport module | Exam-session attendance/check-in and integrity signals |
| Timetable / substitution | Timetable and automatic timetable generator; one-click substitute for absent teacher documented | Timetable listed in parent app; automated substitution not verified in reviewed sources | Timetable present in principal workflow; automated substitution not verified | Academic/timetable domain present; teacher substitution detail not verified | Timetable module documented; substitution detail not verified | Exam schedules, not school timetable |
| Assessment / report cards | Gradebook, examination, custom student remarks and reports | Customisable report cards, competency/rubric assessment and HPC claims documented | NEP/HPC module listed; board/gradebook capabilities | OBE, CO/PO/PSO mapping, digital evaluation, moderation and accreditation | Exam grading, moderation and transcript workflows | Strong JEE/NEET exam console, question bank, report cards, student analytics and parent reports |
| Student achievements / portfolio | Student profile explicitly includes achievements/activities | Student zone progress/performance/participation; exact certificate portfolio detail needs demo | Achievements add-on and student wall add-on listed | 360° lifecycle includes academics, attendance, mentor and placement; school-style activity portfolio not verified | Student profile/documents/certificates; achievement timeline detail not verified | Exam performance, mistakes, study plans and report cards; not general co-curricular portfolio |
| HR / payroll | HR, employee login, leave, payroll ecosystem | HR/payroll, staff attendance and CPD | HR and HR payroll add-on | HR/payroll integrated | HR/payroll, leave and attendance | Not a full HR/payroll system |
| Transport / hostel / library | Documented modules | Transport tracking, library; broader campus features | Transport, hostel, library core | Library, hostel, transport | Library, hostel, transport | Not applicable |
| Digital evaluation / higher-ed | Online examination; not same as full anonymous script evaluation | Exam/assessment and report card workflows | Gradebook/exam; anonymous on-screen script evaluation not verified | Strong documented barcode, anonymous allocation, on-screen marking, moderation, recheck and publish | Exam/grading/transcripts; full anonymous script evaluation not verified | Online exam console, autosave, question bank, exam analytics and integrity review |
| Communications / parent engagement | Messaging, news, events, SMS/email integrations | Parent app, circulars, homework, alerts, teacher messaging and events | WhatsApp LMS, automated alerts, social network links mentioned in knowledge base | Circulars and grievance tracking | Parent/staff portals, WhatsApp integration listed | Notices, messages and parent progress updates |
| Multi-campus / group view | Multi-school ERP option | Centralised dashboards with branch-level permissions documented | Branch/zonal/org permissions and branch finance reports | Multi-campus cross-campus dashboards | Multi-campus unified dashboard | Multiple institute/batch support within exam product |
| Work/tasks / owner department management | Task plugin and role dashboards | Principal/management dashboards; detailed department task hierarchy not verified | Department/role work and dashboards; exact owner task portfolio not verified | Role dashboards and institutional analytics; task management hierarchy not verified | Role portals and campus admin modules; department task portfolio not verified | Institute/teacher/student workflows; not group operations ERP |
| Import/migration / audit | Custom import/export, audit module | Migration/onboarding, role permissions and audit claims | CSV workflows, finance audit reports and parent preferences | Managed migration, RBAC and auditable evaluation | Data migration and audit/field permissions claims | Question/paper upload, content review and exam attempt records |

### 15.1 What Tagore should learn — shortlist, not blind cloning

1. **Parent/family:** Entab’s no-extra-sibling-login model is a baseline expectation. Tagore should go further with the FAM-FEE family-wide payment centre and 360° linked-child experience.
2. **Timetable:** Fedena explicitly documents one-click substitute assignment and an automatic timetable generator. Tagore's agreed leave-aware automatic recommendations plus manual override, workload/clash checks and audit is the target to beat.
3. **Student record:** Fedena documents achievements and activities in student information; iCloudEMS documents a 360° student lifecycle; JEENEET demonstrates a useful student performance/reporting model. Tagore's 360° should join academic, co-scholastic and institutional history with verified evidence and privacy controls.
4. **NEP/HPC:** Entab claims competency/rubric assessment and auto-generated HPC; MyClassboard lists NEP/HPC. Tagore must validate official CBSE/RBSE requirements independently and build configurable board/stage templates.
5. **JEE/NEET:** JEENEET is a strong specialist benchmark for realistic exam delivery, autosave, recovery, question review, paper building, student analytics, parent reports and teacher study plans.
6. **PG College:** iCloudEMS is the strongest reference in this set for OBE, digital evaluation, anonymous script allocation, moderation, rechecking and accreditation evidence. DigiERP adds a practical connected college record, fee, exam, hostel/library and migration benchmark.
7. **Finance:** Fedena and Entab show mature online fee, dues, receipts, reminders and reporting patterns; MCB documents detailed fee counter, reconciliation and operational exceptions. Tagore should benchmark transaction reliability, sibling allocation and cash-office usability—not just a payment button.
8. **Operations:** Fedena's task plugin, visitor/gate and audit features, MCB's visitor/inventory/hostel/transport and DigiERP's connected campus operations are relevant reference points for Tagore's work management and non-academic modules.

### 15.2 Benchmark completion status — explicit

**External public-source review:** Expanded across all six selected products and the main school, higher-education, fee, timetable, student-record and JEE/NEET domains as of 14 November 2026. This is a documented desk-research benchmark, not hands-on product testing or proof of every plan's entitlements.

**Tagore-versus-market parity audit: NOT COMPLETE.** The repository inventory is still first-pass. The following evidence is still missing before the overall comparison can honestly be marked complete:
- Route/action/API/job/permission manifest across all legacy and modern route files, including generated framework route list.
- Screen-by-screen desktop/mobile click-through and workflow completion evidence for every role.
- Feature-level implementation proof from controller/service/model/view/tests, not merely presence of controller/page/table.
- Verified role × action × institution × department × class/section × academic-year access matrix.
- Existing payment gateway/ledger flow inspection for multi-student allocation and webhook/idempotency guarantees.
- Full import/export, notification delivery, scheduler/listener, backup/restore, performance and error-path acceptance.
- Vendor demo validation for ambiguous capabilities, add-on/plan entitlements, integration costs and exact workflow behaviour.

Until those tasks are evidenced, status remains **BENCHMARK RESEARCH EXPANDED — PARITY AUDIT OPEN**. Complete this audit and update the matrix before declaring the modernization scope locked or proceeding with broad feature implementation.


## 16. Examination Portal and Olympiad Portal — dedicated market audit and product requirements

**Owner direction:** Examination and Olympiad are separate visible workstreams. For Tagore Vedant Academy's JEE/NEET coaching wing, the examination experience should take WiziAI as a major product benchmark, while combining stronger exam-authoring, delivery reliability and faculty analytics patterns from specialist assessment platforms. The school/college examination workflows must remain distinct from coaching test-series workflows.

### 16.1 Existing Tagore implementation — repository evidence, not acceptance

Baseline inspected on `main` at `a5806356ad317c8126845a6512b80575376a7f63` on 14 November 2026:

- `app/Http/Controllers/Tagore/ExaminationController.php` and `resources/views/tagore/examinations/*` exist. The controller currently creates institution/academic-year/class-section examinations, adds subject schedules, provides marks entry, and has submit/moderation/publish/withdraw and student-results workflow routes covered by `tests/Feature/TagoreExaminationTest.php`.
- `database/migrations/2026_10_30_090000_create_tagore_examination_engine.php` establishes a Tagore examination data model. It is not evidence by itself of a full coaching test engine.
- `app/Http/Controllers/Tagore/OlympiadController.php`, `OlympiadRegistrationController.php`, `ParentOlympiadController.php`, and `resources/views/tagore/olympiad/*` exist. Current visible surfaces include manager, teacher, student and parent views; event creation, class targeting, registration, question import/review/approval, publishing, student attempts and results.
- `tests/Feature/TagoreOlympiadTest.php`, `TagoreParentOlympiadTest.php` and `OlympiadRegistrationTest.php` cover selected flows, including draft creation, manager approval before publishing and teacher denial for event creation/publishing.
- The legacy API exam routes in `routes/api.php` are commented out. Modern Tagore routes are in `routes/tagore.php`; generated route-list and full role click-through still need to be captured.
- The current exam controller's school-exam marks workflow is not equivalent to a coaching-grade CBT platform. The current Olympiad workflow is not proof of a full Olympiad question-bank, paper-blueprint, adaptive practice, analytics and multi-round competition system.

**Initial conclusion:** both portals are present in code, but they are not yet evidenced as complete, modern, end-to-end assessment products. Their absence from the visible modernization workstream was a register/visibility failure. This section makes them explicit and independently trackable.

### 16.2 Market benchmark — coaching / JEE / NEET examination

| Product | Publicly documented strengths relevant to Tagore | What Tagore should adopt or verify |
|---|---|---|
| **WiZiAI** | JEE/NEET-focused smart mock tests; custom topic/difficulty quizzes; subject/topic performance analysis; strengths and weakness identification; time-management insights; personalized recommendations; adaptive mock tests; historical progress; peer comparison/leaderboards; AI study planning; voice/text AI tutor and stepwise doubt solving; PYQ practice. | Benchmark the student experience, report-card depth, learning loop and personalized improvement. Treat advertised question counts, accuracy, outcomes and performance metrics as vendor claims until independently verified. Do not assume its public website proves institute-side batch administration, question approval, paper security, centre operations or all exam-delivery edge cases. |
| **Examezy** | Coaching test-series cycle: weekly tests, full-length mocks, scholarship tests; paper import and AI-assisted paper generation; JEE-style sections, numeric answers, negative marking and question palette; autosave; tab/camera checks; instant rank/percentile; time-per-section and question insights; AI class diagnostics; online and printed/shuffled paper paths; WhatsApp result alerts. | Strong reference for faculty workflow, mixed online/offline test operations, scholarship lead capture, same-day results and class-level diagnostic teaching. |
| **JEENEET.org** | Exam-console patterns: timer, question palette, save/next, mark-for-review, rough sheet, readiness checks, autosave/resume/auto-submit, paper randomisation, question review, paper building, scheduled tests, reports, analytics and teacher study plans. | Benchmark candidate exam ergonomics, robust attempt lifecycle and faculty/student/parent report flows. Validate exact current capabilities in a demo. |
| **Eklavvya** | Question-bank-driven exam creation; subject/topic/difficulty selection; sections and per-section settings; fixed/flexible scheduling; negative marking; multiple question types and languages; question/option shuffle; candidate assignment; proctoring options; activity logs; question/topic/batch reports and APIs. | Use as the assessment-engine and exam-operations benchmark: authoring, scheduling, access roles, audit trail, optional integrity controls and export/reporting. Proctoring must be configurable and privacy-reviewed, not always-on by default. |

**Sources reviewed (14 November 2026):**
- WiZiAI platform: https://www.wiziai.com/ and https://www.wiziai.com/about
- WiZiAI Google Play feature description: https://play.google.com/store/apps/details?id=com.wiziai.student
- Examezy coaching product: https://examezy.com/for/coaching-institutes/
- Examezy feature catalogue: https://examezy.com/features/
- JEENEET.org: https://jeeneet.org/
- Eklavvya exam management: https://www.eklavvya.com/online-exam-management/ and https://www.eklavvya.com/exam-management/
- Eklavvya exam creation help: https://help.eklavvya.com/a/how-to-create-manage-exams-/

**Evidence caution:** This is public-source desk research, not hands-on product testing. Feature claims, question counts, AI accuracy, outcome statistics, pricing, proctoring and integrations require vendor demo/contract verification. Tagore must not copy proprietary questions, papers, code, interface assets or licensed PYQ content.

### 16.3 Coaching examination product — agreed target capabilities

The coaching examination portal is a first-class product for JEE Main, JEE Advanced, NEET, foundation and internal scholarship/diagnostic tests. It must support offline centre exams and online tests through a shared exam definition and result model.

| ID | Capability | Target behaviour / acceptance | Priority |
|---|---|---|---|
| EXM-COACH-01 | Coaching workspaces | Separate dashboards for Owner/Academic Head, Exam Admin, Subject Faculty, Batch Mentor, Student and (where permitted) Parent; batch/institute/academic-year scope. | P0 |
| EXM-COACH-02 | Test-series calendar | Weekly tests, chapter tests, part tests, cumulative tests, full mocks, PYQ practice, scholarship/diagnostic tests; assign to one or more batches/centres and publish schedule/reminders. | P0 |
| EXM-COACH-03 | Question bank | Subject → chapter → topic/subtopic → concept; exam, paper, year/source, difficulty, marks, negative marks, estimated time, language, question type, solution/explanation, tags, status and version. | P0 |
| EXM-COACH-04 | Rich question types | Single/multiple correct, integer/numerical range, decimal/tolerance, matrix/matching, assertion-reason, paragraph/comprehension-linked, passage/case, image/diagram, subjective response and file upload where appropriate. Marking rules must be explicit per type. | P0 |
| EXM-COACH-05 | Question quality workflow | Draft → subject expert review → approved → retired; duplicate detection, source/license metadata, error reporting, correction/version history and no silent edits after assignment. | P0 |
| EXM-COACH-06 | Paper blueprint builder | Configure exam pattern by JEE Main/Advanced, NEET, foundation or custom template; sections, question counts, marks, negative marking, optional questions, time, difficulty distribution and chapter weightage; preview total marks/time before publish. | P0 |
| EXM-COACH-07 | Paper assembly | Select approved questions manually or by blueprint; enforce syllabus coverage, difficulty mix and duplicate controls; freeze a versioned question/answer snapshot per test. | P0 |
| EXM-COACH-08 | Exam player | Responsive CBT screen with section tabs, question palette, answered/not answered/review states, save/next, clear response, mark for review, section navigation rules, server-authoritative timer, optional calculator and rough-work area. | P0 |
| EXM-COACH-09 | Reliable attempt lifecycle | Start token, one active attempt policy, autosave, idempotent answer writes, reconnect/resume, browser refresh recovery, server-side deadline, auto-submit, duplicate request protection and attempt event log. No answer loss on intermittent network. | P0 |
| EXM-COACH-10 | Exam security | Randomized question/option order where permitted, per-student paper variants, time windows, centre attendance/check-in, device/session policy, suspicious-event logging and optional proctoring. Do not claim cheating prevention from tab-switch counts alone. | P0 |
| EXM-COACH-11 | Offline centre workflow | Print controlled paper variants/QR or booklet codes; invigilator attendance; OMR/manual marks import with validation; online and offline candidates join the same test result pool without duplicate attempts. | P1 |
| EXM-COACH-12 | Auto evaluation | Server-side marking for objective/numeric types, configurable partial credit and negative marking; manual rubric/evaluator queue for subjective responses; double-check high-risk answer-key changes. | P0 |
| EXM-COACH-13 | Result release | Immediate/private result, scheduled release or faculty moderation; freeze result version, re-evaluation/challenge window, correction audit and publish notifications. | P0 |
| EXM-COACH-14 | Rank and percentile | Batch, centre, course, institution and eligible all-Tagore ranks; tie-break rules; percentile calculation cohort and denominator explicitly stated; handle absentees, late attempts and multiple sessions consistently. | P0 |
| EXM-COACH-15 | Student analytics | Total score, subject/section score, accuracy, attempted/unattempted, negative marks, time/question, speed vs accuracy, chapter/topic/concept strength, difficulty performance, careless vs knowledge-gap classification (clearly labelled heuristic), trend and improvement. | P0 |
| EXM-COACH-16 | Faculty analytics | Question-wise option distribution, most-missed questions, distractor analysis, batch heatmap, topic mastery, class average/median, outliers, attendance and improvement since previous tests. | P0 |
| EXM-COACH-17 | Personalized action plan | Recommend next topics, practice sets and revision based on verified question tags and repeated performance; teacher can edit/approve plan. Explain why each recommendation was made. | P1 |
| EXM-COACH-18 | AI study mentor | Ground explanations in approved solutions, syllabus and licensed content; step-by-step text/voice doubt support; cite source/question and flag uncertainty; protect student data; teacher escalation; usage/cost controls. Never invent answer keys. | P1 |
| EXM-COACH-19 | Adaptive practice | Optional practice mode adjusts difficulty based on confidence and mastery; must not alter fixed competitive mock tests or official rank cohorts. | P1 |
| EXM-COACH-20 | PYQ library | Year/exam/subject/chapter/topic filters, verified answer/explanation, provenance and rights metadata; import only licensed/publicly permitted material. | P1 |
| EXM-COACH-21 | Parent/student reporting | Student sees own attempt/review/analysis; parent sees only linked child's released results and progress; teacher sees assigned batch; no cross-batch/private student leakage. | P0 |
| EXM-COACH-22 | Export and integration | PDF report card, CSV/XLSX result export, question/response export with permission, API/webhook integration with ERP student/batch roster; all exports logged. | P1 |
| EXM-COACH-23 | Performance and scale | Load-test concurrent starts/submissions, high-volume autosaves, rank generation and result publication; queue heavy analytics; cache immutable paper snapshots; monitor latency/error rates. | P0 |
| EXM-COACH-24 | Mobile experience | Mobile-friendly practice and reports; for high-stakes full mocks show compatibility guidance and recommend supported desktop/browser where needed. | P1 |

### 16.4 School / college examination portal — distinct from coaching

| ID | Capability | Target behaviour / acceptance | Priority |
|---|---|---|---|
| EXM-SCH-01 | School exam lifecycle | Unit tests, half-yearly, annual, pre-board, board/internal, practical/oral and custom exams; class/section/subject/date schedule. | P0 |
| EXM-SCH-02 | Marks and attendance | Bulk marks entry, absent/exempt/medical/withheld states, validation against maximum marks and class roster, draft/save/resume. | P0 |
| EXM-SCH-03 | Assessment types | Written, practical, project, oral, internal assessment and configurable weightage; separate rubric where applicable. | P1 |
| EXM-SCH-04 | Moderation and publication | Teacher submission → coordinator/principal moderation → lock/publish; correction/reopen reason and immutable version history. | P0 |
| EXM-SCH-05 | Report cards | Board/stage-specific grading, subject totals, grades, attendance, remarks, competencies/HPC references and downloadable report cards. | P0 |
| EXM-SCH-06 | College semester exams | PG College course/semester/subject registration, internal/university marks, practicals, backlog/supplementary/revaluation and transcript only after owner validates applicable university rules. | P1 |
| EXM-SCH-07 | Digital evaluation | Assess PG College fit for barcode anonymisation, evaluator allocation, rubric marking, moderation, recheck and result audit; implement only after process/legal/board requirements confirmed. | P2 |
| EXM-SCH-08 | Academic integration | Link results to Student 360°, promotion, attendance, parent portal and longitudinal progress without duplicating marks records. | P0 |

### 16.5 Olympiad Portal — independent competition and registration product

| ID | Capability | Target behaviour / acceptance | Priority |
|---|---|---|---|
| OLY-01 | Olympiad catalogue | Multiple competitions by subject, grade, institution, language, round, eligibility, dates, fee/scholarship and organiser; reusable annual editions. | P0 |
| OLY-02 | Registration | In-school and external registration, parent/guardian consent, verified student identity, duplicate prevention, registration number, roster and status tracking. | P0 |
| OLY-03 | Eligibility and rounds | Grade/age/subject eligibility; school, district, state and national rounds; qualification rules and progression history. | P1 |
| OLY-04 | Olympiad question bank | Separate subject/topic/difficulty/grade/language bank; objective, numeric, reasoning, diagram and subjective question types; solutions and review/version controls. | P0 |
| OLY-05 | Competition paper builder | Blueprint, section, marks, duration, negative marking, randomization, paper variants and locked answer key. | P0 |
| OLY-06 | Candidate delivery | Student portal, exam schedule, instructions, readiness check, timer, question palette, autosave/resume, auto-submit and attempt audit. | P0 |
| OLY-07 | Offline administration | Centre/school roster, invigilator, attendance, paper/OMR/manual score capture, verification and controlled upload. | P1 |
| OLY-08 | Results and awards | Raw score, rank within defined cohort, qualification, certificates, medals/awards, downloadable result and public result only with consent/privacy controls. | P0 |
| OLY-09 | School/institution dashboard | Registrations, attendance, completion, class/grade/subject performance, qualifying students and exports. | P1 |
| OLY-10 | Parent experience | Linked-child registration status, schedule, instructions, result and certificate; no exposure to other students. | P1 |
| OLY-11 | Integrity and disputes | Duplicate registration/attempt checks, answer-key correction, result challenge, moderation, audit and reissue controls. | P0 |
| OLY-12 | External partner/API | Optional external school/organiser roster import, result export and certificate verification endpoint after owner-approved contract/security review. | P2 |

### 16.6 Shared assessment engine — architecture decision

Build one reusable assessment core with separate product experiences and permission boundaries:

1. **Shared core:** question bank, taxonomy/tags, question versioning, exam blueprint, exam/paper version, scheduling, candidate assignment, attempt/session, answer persistence, marking rules, evaluation, result snapshot, analytics events, notifications and audit.
2. **Separate product layers:** School/College Examination, Coaching Test Series and Olympiad Competition each own their templates, eligibility, workflow, reporting, roles and navigation. Do not force a school annual exam into a JEE mock-test interface or an Olympiad into a classroom marks-entry screen.
3. **Institution and cohort scoping:** every exam, question set, attempt, result and report must carry institution, academic year, programme/batch/class and permitted cohort scope. Explicitly define cross-institution leaderboards; default to institution/batch only.
4. **Content rights:** store source, author, licence/permission, provenance and revision for every imported question. AI may draft content but a qualified subject expert must approve before use in a scored test.
5. **Integrity:** immutable published paper snapshot; server-side time and scoring; append-only attempt events; idempotent submission; transactional answer/result persistence; controlled answer-key correction and result recalculation.
6. **Privacy:** proctoring is optional by exam risk level, disclosed before attempt, data-minimised, retention-limited and permission-controlled. Avoid facial recognition or continuous audio/video by default.
7. **AI boundary:** AI insights are advisory, explainable and grounded in reviewed data; never silently set official marks, alter rank, publish results or replace teacher judgement.

### 16.7 Delivery order and acceptance gates

| Phase | Work package | Exit gate |
|---|---|---|
| A | Full code audit: all exam/Olympiad routes, controller methods, migrations, models/tables, views, JS, imports, API, tests, role matrix and route:list | Evidence map identifies implemented, partial, missing, duplicate and unsafe workflows; no assumptions based on file presence |
| B | Shared assessment core design and schema gap review | Versioned ERD/state diagrams, API contracts, transaction/idempotency rules and role/action matrix reviewed |
| C | Question bank and authoring/review | All required question types, validation, bulk import, duplicate/content review, versioning and access tests pass |
| D | Exam blueprint, scheduling and candidate assignment | JEE/NEET templates, section/marking rules, batches/centres, overlapping schedules and preview tests pass |
| E | Candidate exam player and attempt reliability | Timer, palette, autosave, network interruption, refresh/resume, deadline, duplicate submit and concurrency tests pass |
| F | Evaluation, rank, analytics and reports | Independently verified scoring fixtures, tie/percentile rules, topic analytics, exports and parent/student/faculty privacy tests pass |
| G | Olympiad workflow | Registration, eligibility, rounds, offline centre, results, awards, certificates and disputes pass |
| H | AI study loop and adaptive practice | Grounded explanations, teacher-approved recommendations, content-rights checks, privacy/cost guardrails and offline fallback pass |
| I | Final role and responsive acceptance | Owner/admin, exam admin, subject expert, teacher/mentor, student, parent and invigilator flows tested on desktop/mobile with synthetic data |

### 16.8 Initial code-audit questions — do not mark complete yet

- Does the current Olympiad JSON importer support only one correct MCQ option, and what are the actual row/batch limits? Inspect importer validation and tests before recording as a confirmed limitation.
- Does the current exam engine support question banks, CBT attempts, autosave, timer recovery, section rules, numeric/multiple-correct answers, negative marking and rank/percentile, or is it currently school marks-entry only?
- Are teacher and coordinator exam permissions restricted to assigned classes/batches, and are result endpoints equally scoped?
- Are answer keys and question versions immutable after an exam is scheduled/published?
- Can two concurrent requests, duplicate webhooks or browser retries create duplicate attempts/marks?
- Is the student/parent Olympiad attempt lifecycle fully server-timed, recoverable and idempotent?
- Are external Olympiad registrants separated from existing Tagore student accounts safely?
- Are results/rank lists cohort-scoped, tie-break rules explicit and privacy-safe?
- Are routes linked in every relevant role navigation/dashboard? The owner reports the work is not visible; add explicit role navigation and dashboard entry points only after route/permission verification.
- Are offline print/OMR and online test results able to reconcile to one candidate/test without duplicate scoring?

### 16.9 Status

**Market desk research for coaching exams:** expanded across WiZiAI, Examezy, JEENEET.org and Eklavvya.  
**School/college exam and Olympiad market comparison:** initial specialist comparison documented; CBSE/RBSE/PG College rule mapping and vendor demonstrations remain.  
**Tagore implementation parity audit:** OPEN. The current repository contains school exam and Olympiad code, but no evidence yet that it meets the full coaching-grade CBT, question bank, analytics and adaptive learning target.  
**Implementation:** not started by this register update. Next action is the code-audit work package (Phase A), then implement in coherent PRs with automated tests. No live student data, production deployment or merge is authorised by this work.


### 16.10 Phase A — first-pass code audit findings (14 November 2026)

Inspected the current `main` branch route/controller/migration/view/test files. This is a static repository audit; no browser click-through or execution against a running test database is claimed.

**Route inventory verified in `routes/tagore.php`:**
- School examinations: `tagore.examinations.index/store`, subject schedule create, marks entry/save, grade scale save, submit, moderate, publish, withdraw and student results.
- Olympiad manager: index/store, question template, show, import, approve and publish.
- Olympiad student: register, exam, save answers, submit and result.
- Parent-supervised Olympiad: linked-child index, register, launch, tokenised exam, save answers, submit and result.
- Public Olympiad registration routes are in `routes/web.php`, behind a feature flag and throttles.
- No coaching test-series, coaching batch exam scheduling, coaching question-bank, paper-blueprint, test analytics, rank/percentile or AI study-plan route family was found in the exam-specific route set inspected.

**School Examination: confirmed current scope**
- The migration creates `tagore_exams`, `tagore_exam_subjects`, `tagore_exam_marks`, `tagore_exam_mark_events`, `tagore_exam_grade_scales`, `tagore_exam_events` and uses `tagore_results`.
- Controller supports institution/year/class-section exam setup, subject schedule, teacher/manager marks entry, grade scales, submit → moderate → publish → withdraw lifecycle and student/parent report card.
- Teacher marks entry is checked against `class_teacher_links` for subject/class/year; parent report card is restricted to active linked children.
- The current exam data model is school-exam marks-centric. The inspected schema/controller does not evidence a reusable coaching question bank, paper snapshot, candidate attempt/session, per-question response, section timer, negative-marking engine, numerical answer evaluator, rank/percentile cohort or topic-level coaching analytics.
- Existing tests are useful contract/role smoke tests, but do not establish CBT delivery or concurrent attempt reliability.

**Olympiad: confirmed current scope and specific limitations**
- Manager creates institution-scoped events and targets classes; teacher/manager can import questions; imported questions require manager approval before event publication.
- Import accepts JSON and XLSX/XLS/CSV, but the spreadsheet importer explicitly rejects types other than Single Choice. Each imported item requires one answer key matching one option key; the batch is explicitly limited to 1–200 questions.
- The attempt player renders one-choice radio options. Answer saves accept scalar option keys and at most 200 answers. The scoring loop awards positive marks for the single correct option; the inspected scoring code does not implement configurable negative marking, partial credit or numerical/multiple-correct evaluation.
- Exam timer is calculated from event end and registration start + duration. Browser autosave and refresh/resume are present; parent-supervised attempts use a separate token-based route family. Tests cover selected autosave/resume and linked-child protection.
- The current student player renders a sequential question list and does not show the richer exam palette/section controls/rough sheet/review navigation required by the coaching target.
- The inspected Olympiad flow has no evidenced blueprint builder, reusable taxonomy-rich question bank across events, multi-round progression, cohort percentile/leaderboard, detailed topic analytics, teacher batch diagnostics or certificate/award workflow.
- Public registration is feature-flagged and rate-limited; verify the actual registration-to-ERP identity matching and duplicate handling in a dedicated acceptance pass.

**Navigation visibility remains an open defect to verify:** routes and pages exist, but owner reports they are not visible in normal portal navigation. Next audit step is to trace every role dashboard/sidebar/menu and prove that authorised users can reach the correct Examination, Coaching Tests and Olympiad workspaces. Do not equate route existence with discoverability or acceptance.

**Initial gap classification:**
- **Confirmed missing from inspected exam-specific implementation:** coaching test series, coaching batch assignment, question bank/paper blueprint, CBT candidate attempt engine, advanced question types/marking schemes, rank/percentile, topic-level analytics, adaptive practice, AI mentor and coaching study plans.
- **Confirmed Olympiad limitations:** single-choice import/attempt model, 200-question batch/answer cap, no negative marking/multi-answer/numeric scoring in inspected path, limited sequential player UI.
- **Partially present and must be tested further:** event registration, class eligibility, approval gate, timed attempt, autosave/resume, parent-child access, result scoring and school marks moderation.
- **Not yet verified:** menu discoverability by role, concurrency and race conditions, server-time tamper resistance under all paths, import row-level error reporting/rollback, question/answer snapshot immutability, result/rank privacy, external registrant deduplication, accessibility/mobile acceptance and production-scale performance.

**Next Phase A tasks:** inspect dashboard/sidebar route links for every role; inspect all Olympiad schema/indexes and registration identity matching; build route × role × action × scope matrix; run focused synthetic feature tests; record concrete evidence and defects before implementation PRs. The coaching engine implementation must not be grafted into the school marks-entry controller or built as a superficial extension of the existing Olympiad single-choice flow.
