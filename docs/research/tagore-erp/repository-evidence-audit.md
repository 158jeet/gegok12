# Phase 0 — Repository Evidence Audit (initial pass)

**Repository:** 158jeet/gegok12  
**Branch inspected:** main  
**Main SHA inspected:** a5806356ad317c8126845a6512b80575376a7f63  
**Audit date:** 2026-11-14  
**Coverage:** initial static inspection of routes, selected controllers/services, migrations, seeders, tests, mobile/desktop manifests and open PR metadata. This is not yet a complete route-by-route, visual or runtime acceptance audit.

## 1. Executive correction to the supplied report

The supplied report is accepted as the requirements and benchmark baseline, but several preliminary classifications need correction. The current main branch already contains substantial Tagore-specific functionality for admissions CRM, multi-institution records, fee obligations/installments, family balance views, online Razorpay orders/webhooks, school examinations, Olympiad events, visitor/gate-pass handling, departmental tasks, hostel allocation, communication, documents, transport, inventory, Android clients and a Windows desktop scaffold.

The correct status for many such areas is **partial / workflow unverified**, not absent. Conversely, a controller, migration or landing page is not proof that a complete business workflow exists.

## 2. Evidence-backed findings

### 2.1 Group and institution model — partial, verified at schema level
Evidence:
- `database/migrations/2026_09_12_090000_create_tagore_core_tables.php` creates `tagore_groups`, `tagore_institutions`, Tagore roles/permissions, user-role memberships, user scopes and parent-student links.
- `tagore_institutions.school_id` is unique; institution rows map to legacy school records.
- `database/seeders/TagorePrototypeSeeder.php` creates institution rows for up to the first three legacy schools and assigns roles to users.

Gaps / verification:
- Confirm all real Tagore entities (including Vedant coaching, hostel and PG College) are represented as intended, not simply first-three-school seed fixtures.
- Verify institution isolation for every route, API, job, export, document and report.
- Verify group-level accounts roll-up and institution-specific accounting books.
- The role seeder currently defines eight Tagore roles: OWNER, PRINCIPAL, COORDINATOR, TEACHER, PARENT, STUDENT, ACCOUNTS and FEE_EDITOR. **There is no CASHIER role in this seeder.** A dedicated cashier role and permission contract remain outstanding.

### 2.2 Role and scope enforcement — partial; high-priority audit finding
Evidence:
- `ScopeService::can()` resolves permissions from active role memberships and optionally checks `tagore_user_scopes`.
- `AdmissionsController` filters leads by the user's institution IDs.
- `SecurityController` filters visitors/gate passes by institution IDs.
- `ExaminationController` uses institution context and class-teacher checks for marks entry.
- `FeeController::authorizeStudent()` permits OWNER, PRINCIPAL, COORDINATOR, TEACHER and ACCOUNTS after checking institution membership, but the inspected helper does not enforce teacher class assignment or coordinator grade/section assignment for the fee statement.
- `FeeController::accounts()` allows OWNER, PRINCIPAL, COORDINATOR and ACCOUNTS; it scopes institutions, but does not implement the requested coordinator class-level filter.

Risk:
- Current fee visibility can be broader than the required “teacher's own class / coordinator's assigned grades only” rule. This must be fixed and negatively tested before acceptance.
- Cashier is absent from the Tagore prototype role seed and role access matrix test.
- `ScopeService` exists, but its presence does not prove every controller uses it consistently.

### 2.3 Admissions CRM — partial, verified
Evidence:
- `routes/tagore.php` registers admissions list/create/store, assignment, lead detail, activity and family-link routes.
- `AdmissionsController` implements institution-scoped leads, status filters, source/campaign filters, follow-up queues, assignment, activity history and admitted/lost status updates.
- `TagoreAdmissionsTest` covers lead creation, assignment, follow-up filtering, activity and a same-institution family link.

Gaps:
- Marking a lead `admitted` records a status and `converted_at`; the inspected workflow does not itself demonstrate full student-account/enrolment creation, fee-demand creation, document completion and once-only conversion.
- `linkFamily()` looks up the parent account using the same `school_id` as the institution and requires the student to belong to that school. This blocks the desired cross-institution family-linking flow when the same guardian's children attend different Tagore institutions.
- Duplicate applicant/guardian detection and bulk import need separate workflow proof.

### 2.4 Family account and parent dashboard — partial, verified
Evidence:
- `tagore_parent_students` supports multiple student links per parent account.
- `ParentDashboardController` loads all active linked children and produces child-level fee, attendance and published-result summaries.
- `FeeController::student()` builds a family balance summary from active links.
- Parent access to a fee statement is checked against an active parent-student link.

Gaps:
- Family overview is present, but checkout is not family-wide.
- The current online-payment route is `POST /tagore/child/{studentId}/fees/pay`; `OnlinePaymentController::initiate()` authorizes one linked student and `OnlinePaymentService::createOrder()` creates one payment order with one `student_id` and one `institution_id`.
- Therefore one checkout cannot currently represent multiple children and allocate across multiple child/institution ledgers.
- Cross-institution family linking is blocked by the same-school lookup in admissions.

### 2.5 Fee engine and reconciliation — meaningful foundation, partial
Evidence:
- Core migrations create fee structures, obligations, installments, payments, payment events, financial transactions and order-allocation records.
- `FeeService` creates itemized obligations, monthly/term installments and offline payments with explicit allocation checks, row locks, ledger updates and audit events.
- `FeeEditorService` is covered by tests for percentage adjustment and preventing a fee reduction below already-paid amounts.
- `FeeController` exposes fee management, bulk demand, imports, family balances, receipts and a distinct reconciliation route.
- Gateway reconciliation is restricted to OWNER/ACCOUNTS and compares settlement amount against payment amount, matching the user's distinction between collection follow-up and bank settlement.

Risks / gaps:
- The online checkout still handles one child at a time.
- `OnlinePaymentService::webhook()` records gateway events and settles `payment.captured`; the inspected handler does not process refund/reversal events into compensating ledger entries.
- Multiple open payment orders can be created against the same outstanding amount: order creation stores allocations but does not reserve/reduce the outstanding balance. Settlement adds the order allocation to paid amount and clamps outstanding to zero. Two separately created orders could therefore over-collect unless settlement revalidates available outstanding and applies a concurrency-safe reservation/idempotency rule.
- The current tests cover offline partial payment and overpayment rejection, but this pass did not find equivalent full gateway tests for duplicate events, simultaneous orders, timeout, refund/reversal and demand changes.
- Cashier role and cashier-specific collection reconciliation remain unimplemented in the current role seed.

### 2.6 School examination — partial, verified
Evidence:
- `routes/tagore.php` exposes create, subject schedule, marks entry, grade scale, submit, moderate, publish, withdraw and student report routes.
- `2026_10_30_090000_create_tagore_examination_engine.php` creates exam, subject, marks, mark-event, grade-scale and exam-event tables and links published results.
- `TagoreExaminationTest` verifies the engine contract, route registration, parent access to linked-child report cards and role entry points.

Gaps:
- This is a school examination engine, not a complete JEE/NEET coaching CBT product.
- Current test file is mostly contract/route/access coverage; it does not establish exhaustive end-to-end moderation, immutability, board-specific NEP holistic card compliance, load or mobile acceptance.
- Board-specific CBSE/RBSE report templates need current official-rule validation.

### 2.7 Olympiad — partial, verified
Evidence:
- Event, question, registration and attempt routes/views exist.
- `OlympiadController` supports draft event creation, target classes, question import and approval before publishing.
- `TagoreOlympiadTest` covers draft creation, teacher denial and manager question approval before publication.

Gaps:
- The inspected schema/controller is a single event/exam flow; multi-round progression, award/certificate lifecycle and advanced coaching analytics are not established.
- Earlier narrow review found constrained import/scoring and a sequential player; re-run that audit against current main and add test coverage.

### 2.8 Visitor and gate pass — partial, verified
Evidence:
- `SecurityController` implements visitor creation with OTP hash/expiry, check-in, check-out, gate-pass request, approval/rejection and security event logging.
- `2026_09_27_170000_create_tagore_security_gatepass.php` creates institution-scoped visitor, gate-pass and event tables.
- `routes/tagore.php` registers these workflows.

Gaps / risks:
- Visitor photo capture, badge/barcode printing, host directory validation and retention controls are not evidenced.
- Gate-pass creation checks that the selected user is a student but the inspected code does not verify that the student's `school_id` matches the selected institution. Add a cross-institution negative test and enforce the relationship.
- Public token verification returns a pass payload; review response minimisation, rate limiting and privacy exposure before production.

### 2.9 Operations, hostel, transport and inventory — mostly generic foundation, partial
Evidence:
- `2026_09_27_120000_create_tagore_extended_erp_modules.php` creates institution-keyed generic record tables for payroll, inventory, expenses, transport, hostel, visitors, gate passes, notifications, documents and other modules.
- `OperationsController` provides generic CRUD, institution filters and audit events.
- A separate hostel migration creates hostel rooms and allocations.
- Dedicated inventory and transport controllers/views exist.

Gaps:
- Generic CRUD tables are not equivalent to full transport GPS, vehicle/driver lifecycle, hostel warden operations, mess/room attendance, library circulation or robust inventory procurement/asset lifecycle.
- Hostel room/bed allocation constraints and student-institution consistency need workflow tests.
- GPS, CCTV and device integrations remain external-dependency gates.

### 2.10 Owner tasks and departmental performance — partial, verified
Evidence:
- `tagore_departments`, `tagore_tasks`, task events, task templates and employee reviews have migrations.
- `TaskController` implements institution-scoped task lists, assignment, employee profiles, overdue/completion metrics, department workload and performance views.
- This is not absent as the original report suggested.

Gaps:
- Verify task dependencies, blockers, evidence attachments, escalations, KPI definitions and department-level approvals. Confirm all create/update/review paths use the same institution and role policy.

### 2.11 Attendance and HR — partial / biometric absent on main
Evidence:
- Staff self-service leave, leave approval, payroll controllers and payroll migrations exist.
- The current main tree contains no T304F/Realtime-specific device registry, ingestion adapter or biometric punch model.
- PR #69 is still an open draft and is not part of main. Its description states sync is disabled pending vendor contract.

Gaps:
- Verify shifts, payroll calculations, exception handling and payroll approvals end to end.
- Do not integrate or activate T304F until the actual supported protocol/SDK/ADMS/WSDL and security documentation are supplied.

### 2.12 Android and Windows clients — partial / scaffold
Evidence:
- `mobile/parent` and `mobile/teacher` exist and Android CI is configured.
- `mobile/README.md` says the Android clients remain pinned to upstream GegoK12 implementations; Tagore config/build ownership is added, but new ERP API parity and offline sync must be extended endpoint-by-endpoint.
- `desktop/README.md` describes the Windows client as an “Offline-first staff client scaffold”; Tauri shell files exist.
- Offline sync migrations provide device/change/cursor tables, but the complete Windows sync workflow and conflict-resolution acceptance are not proven by this static pass.

### 2.13 Promotion — legacy module exists; safe workflow not established
Evidence:
- Legacy `Admin/PromotionController` supports promotion list export and importing promotion results through Excel.
- It uses legacy school/year/class context and activity logging.

Gaps:
- The inspected controller does not demonstrate the required preview/validation/approval, conflict detection, hold/fail/pass/alumni handling, idempotent next-year enrolment and immutable before/after audit. Do not label promotion absent; label legacy/partial until the full import and model workflow is audited.

### 2.14 UI, backup, college and market parity — not yet verified
- The current tree contains many Tagore views, but this static audit has not visually inspected every route for design consistency, responsiveness, empty/error/loading states or role-specific usability.
- Backup/restore and annual archive recovery have not been demonstrated by an executed restore drill.
- PG College course/semester/affiliation requirements need confirmation before OBE/NAAC/NBA implementation.
- Vendor feature claims and citations from the pasted report need source/date/plan-tier revalidation before design lock.

## 3. Test coverage observed

Present test suites include:
- `TagoreRoleAccessMatrixTest`
- `TagoreFeeWorkflowAcceptanceTest`
- `TagoreAdmissionsTest`
- `TagoreOperationsAcceptanceTest`
- `TagoreExaminationTest`
- `TagoreOlympiadTest`
- `TagoreDailyDiaryAcceptanceTest`
- `TagoreOfflineSyncTest`
- `TagoreStudentParentMigrationTest`
- `TagoreCoreModuleSmokeTest`

Important limitation: the role-matrix test enumerates eight Tagore roles and no CASHIER. The core-module smoke test verifies owner landing pages return HTTP 200; it does not prove the workflows behind each page. The fee tests provide stronger offline ledger coverage but do not by themselves prove family checkout or gateway reversal/concurrency safety.

## 4. Open PR state and CI

At the time of inspection, PRs #67, #69, #71 and #72 are open drafts and have not been merged into main. PR #69 targets another integration branch rather than main. Treat their code/docs as proposed work, not as functionality already present in main.

PR #72's three workflow runs (ERP Full Acceptance Convergence, ERP CI and TagoreK12 Prototype) were still in progress/queued when checked. No pass/fail conclusion is claimed for PR #72 yet.

## 5. Priority findings for the next audit pass

**P0 — security and money**
1. Complete route-by-route institution and class-scope review, especially fee statement and coordinator Accounts visibility.
2. Define and seed CASHIER role with operational fee collection rights but no fee-master, concession/refund/write-off, ledger-adjustment or bank-settlement rights by default.
3. Design family-wide payment order across linked children/institutions with explicit per-child allocations and independent settlement attribution.
4. Add concurrency-safe order reservations/settlement checks and refund/reversal compensating entries.
5. Validate gate-pass student-to-institution relationship.
6. Add cross-institution guardian linking and verification/revocation.

**P1 — lifecycle and product completeness**
1. Admissions lead-to-enrolment conversion and duplicate detection.
2. Promotion preview/approval and year rollover.
3. Coaching JEE/NEET QBank, blueprint, CBT player and analytics distinct from Olympiad.
4. Timetable substitution, attendance exceptions and HR/payroll end-to-end.
5. Mobile parity and Windows offline sync conflict acceptance.
6. Task dependencies/evidence/escalation and operational module depth.

**P2/P3 — specialised integrations and polish**
1. Visitor badge/photo, transport GPS, CCTV, secure calling and biometric integration only after vendor/security approval.
2. College OBE only after affiliation and regulation discovery.
3. Visual route-by-route UX acceptance, backup/restore drill, accessibility and performance.

## 6. Audit limitations and next action

This pass is based on current GitHub main source and static file inspection. It has not run the application, database migrations, PHPUnit, browser click-through, mobile emulator or visual regression. The next pass must inspect remaining controllers/services, policies, route middleware, import/export paths, notifications, timetable, attendance, report generation and UI templates, then run available synthetic tests/CI where permitted. No production DB, live student data, hardware integration, merge or deployment was touched.

## 7. Additional pass — academics, attendance, diary and communications

### Timetable and substitution
- Legacy timetable views and an authenticated API controller exist (`resources/views/admin/school/timetable/*`, `app/Http/Controllers/Api/TimetableController.php`).
- The inspected API builds a student's timetable from the student's latest academic link and the authenticated user's school. This pass did not find a Tagore-specific automatic substitute recommendation engine, teacher absence conflict solver or approval/notification workflow.
- Verify that the timetable API rejects a student ID outside the authenticated user's permitted school and handles missing student/academic-link records safely.
- Status: timetable foundation present; automated substitution and security acceptance unknown/not evidenced.

### Student and staff attendance
- Legacy student attendance create/list/export routes and controller exist; records are scoped by the authenticated legacy `school_id` and active academic year in the inspected controller.
- Staff attendance is manual in the inspected `Admin/StaffAttendanceController.php`; no T304F punch ingestion is present on main.
- **Code-quality risk:** the inspected staff attendance `store()` catches an exception and calls `dd($e->getMessage())`, which can terminate the request with a debug dump. Replace with structured logging and a controlled error response before treating this workflow as production-ready.
- No evidence in this pass of automated absent-parent SMS/email notification or class-wise alert scheduling.
- Status: student/staff manual attendance present; automated notifications and biometric ingestion partial/absent.

### Parent study / feedback diary
- `DailyDiaryController` supports parent and school roles, linked-child selection, date-range entries, parent study-hours/feedback fields and school homework/feedback fields.
- Role-specific request fields are removed before validation, and school updates preserve parent-owned fields.
- `TagoreDailyDiaryAcceptanceTest` and a Playwright E2E spec exist.
- Status: diary foundation present; verify mobile parity, teacher assignment scope, parent acknowledgement/reply UX and notifications.

### Communication campaigns
- `CommunicationController` and `CommunicationService` implement institution-scoped campaigns for email, SMS, WhatsApp and push, audience filters and per-recipient delivery rows with sent/failed states.
- Email uses Laravel Mail; SMS/WhatsApp uses Twilio when configured; push uses registered Firebase device tokens.
- The inspected service treats a previously sent recipient as idempotently complete, but this pass did not verify scheduled campaign dispatch, provider callbacks, retry policy/backoff, delivery receipts, parent acknowledgement or full privacy/consent controls.
- No secure click-to-call integration or live third-party social-feed integration was found in the inspected Tagore routes/services.
- Do not execute campaign actions during research: they can contact real recipients when credentials are configured.

### Generic operations CRUD and audit retention
- `OperationsController::destroy()` deletes the selected generic operations row and writes an audit event. The source inspected does not show soft-delete/restore for those rows. Confirm whether hard deletion is acceptable per module; finance, visitor, security, payroll and inventory evidence should generally be retained or voided rather than silently removed.
- Generic operation tables are mostly broad string/JSON columns. Treat them as scaffolding for several domains, not proof of validated end-to-end modules.

## 8. Additional pass — payroll, transport and inventory

### Payroll — partial, verified foundation
- `PayrollController` supports institution-scoped payroll runs, generate, approve, mark-paid and payslip PDF.
- `PayrollService` validates employee school membership, calculates gross/allowances/PF/ESI/TDS/other deductions, and uses draft → processed → approved → paid states with row locks.
- Gaps: the inspected service accepts salary/allowance/deduction inputs in the generation payload and does not derive payable days from approved leave/attendance or show a complete payroll policy/configuration workflow. Statutory calculation assumptions and Indian payroll compliance need specialist validation. HR role can generate but cannot approve/mark paid in the inspected controller; verify intended separation. No cashier role should gain payroll access.

### Transport — more than a placeholder, still incomplete
- `TransportController` has institution-scoped route, vehicle, driver, trip, GPS-event, live-location and boarding endpoints. Parent live-location access is checked against active child-to-route assignments.
- This corrects the original report's implication that transport is only static route allocation: a GPS event ingestion endpoint and trip tracking model exist.
- Gaps / risks: no external GPS vendor/device ingestion contract was verified; `driver()` checks that the selected user exists but does not verify same-institution employment; `boarding()` checks trip scope but the inspected method does not verify that the student belongs to the trip institution or route assignment. Add cross-institution negative tests.
- Status: transport workflow partial; GPS provider integration unknown/blocked pending provider evidence.

### Inventory, procurement and assets — partial, verified foundation
- `InventoryController` supports institution-scoped vendors, items, stock movement, purchase orders, approval, receiving, expenses, expense decisions and assets.
- `InventoryService` is used for stock movement and purchase-order receiving.
- Gaps / risks: `asset()` verifies that an assigned user exists but does not verify that the assignee belongs to the selected institution. Verify stock movement audit/idempotency, partial receiving, vendor/item lifecycle, expense approval segregation and asset issue/return history.
- Status: stronger than generic CRUD, but full procurement and fixed-asset acceptance remains open.

### Hostel and PG College
- Hostel room and allocation schema exists, but this pass did not find a dedicated Tagore hostel controller/workflow in the Tagore controller set. The generic operations table is not a substitute for occupancy conflict checks, bed assignment, warden attendance, mess management and fee-ledger integration.
- No dedicated Tagore PG College/semester/OBE controller was identified in the inspected Tagore controller set. Treat college functionality as unknown pending course/affiliation discovery, not as a completed college ERP.
