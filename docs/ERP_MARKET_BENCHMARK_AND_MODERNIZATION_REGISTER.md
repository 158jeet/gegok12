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