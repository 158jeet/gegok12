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

## 9. Additional pass — reports, documents, learning and CCTV

### Custom reports — one confirmed broken export source
- `ReportController` supports institution-scoped report definitions and exports for payroll runs, payroll items, inventory, expenses, assets, transport trips and communication campaigns.
- **Confirmed schema mismatch:** the report export builder unconditionally applies `where('institution_id', $report->institution_id)` to every source. The `tagore_payroll_items` migration has `payroll_run_id` but no `institution_id` column. Therefore a saved custom report using the `payroll_items` source will fail at query time. It must join `tagore_payroll_runs` and scope through the run's institution.
- The custom export path also accepts `table`, `csv` or `pdf` at definition time, but the inspected export action emits PDF only when the request query says `format=pdf`; all other formats are CSV. Verify the UI and output contract.
- Status: partial; payroll-items export has a concrete defect requiring a fix and regression test.

### Documents — private storage and versioning foundation; access review needed
- Upload stores files on the private disk under institution-specific paths and records version metadata including original name, MIME type, size, uploader and SHA-256.
- Download enforces institution membership and optional role allow-list from `access_json`.
- **Access-control gap:** the versions endpoint checks institution membership and broad staff roles but does not apply the document's `access_json.roles` restriction. It returns version rows including private storage paths and file metadata. Apply the same object-level access policy to versions and minimise returned fields.
- The inspected download path does not use `owner_type/owner_id` to verify the current user's relationship to a private student/staff document. Define and enforce ownership/guardian/assigned-class policy before using this as a student-record document vault.

### Learning, hostel and student store — partial; tenant checks needed
- Learning content, courses, enrollments, progress, surveys, hostel rooms/allocations and student-store orders have routes and schema.
- **Hostel allocation risk:** `LearningController::allocate()` verifies the room belongs to an institution the actor can access and verifies the target is a student, but does not verify that the student's school matches the room institution. The inspected Tagore routes include room creation and allocation, but no hostel checkout/transfer workflow.
- **Course enrollment risk:** `enroll()` verifies the course and student exist but does not verify that the student's institution matches the course institution. A student may attempt to enroll across institutions if the route is reachable.
- **Survey audience gap:** `surveyResponse()` checks that the survey is published but does not enforce the survey's institution/audience against the respondent.
- **Store order gap:** parent/student roles bypass the institution-membership check in `order()`; validate that the buyer is linked/enrolled to the target institution and restrict child purchasing to linked children.
- Status: partial; add cross-institution negative tests and role/audience rules.

### CCTV integration — Frigate status check, not Hikvision integration
- `PlatformController::cctvStatus()` makes a read-only request to a configured private Frigate NVR `/api/stats` endpoint and reports reachability/camera/detector counts.
- It explicitly does not verify camera streams or AI detection accuracy. This is not evidence of Hikvision integration, visitor-to-camera event linkage, recording retrieval or production activation.
- Status: read-only Frigate connectivity check only; Hikvision remains an external integration requirement and must not be activated without the actual vendor/API/security contract.

## 10. Additional pass — role catalogue, family migration and fee management boundaries

### Role catalogue is incomplete for the requested operating model
- `TagorePrototypeSeeder` and `TagoreDemoAccountsSeeder` define/seed eight roles only: OWNER, PRINCIPAL, COORDINATOR, TEACHER, PARENT, STUDENT, ACCOUNTS and FEE_EDITOR.
- The supplied target operating model also needs CASHIER, HR Manager, Transport Manager and Hostel Warden/Admin (plus other optional module roles). Some controllers already test role codes such as HR, TRANSPORT and HOSTEL, but those codes are not created by the inspected prototype role seed.
- There is no role-creation route in `AdministrationController`; it assigns only existing role records. Therefore these missing roles cannot be made available through the current owner admin UI unless separately seeded.
- `StaffController::leave()` and `decideLeave()` use OWNER/PRINCIPAL/COORDINATOR as manager roles and do not include HR, despite payroll routes allowing HR. Align the HR manager/leave-approver policy with the agreed matrix.
- The cashier gap is more than naming: `FeeController::authorizePaymentRecording()`, `FeeManagementController::MANAGE_ROLES` and `FeeWalletController` do not provide the required operational cashier collection rights. No cashier-specific daily collection/reconciliation workflow is evidenced.

### Family migration nuance
- The owner-only `StudentParentMigrationService` copies active legacy `student_parent_links` into `tagore_parent_students` using idempotent upserts. The inspected sync query does not require parent and student to share the same school, so it can preserve legacy cross-school guardian links where they already exist.
- However, the new admissions `linkFamily()` flow requires the parent and student to belong to the same school. Thus cross-institution family access may survive migration but cannot reliably be created/managed through the new admissions UI. Add an explicit group-level verified guardian workflow and tests.

### Fee-management separation
- `FeeManagementController` manages fee structures, class assignments, individual demands, bulk preview and bulk generation; its manager roles are OWNER, PRINCIPAL and ACCOUNTS.
- `FeeEditorController` limits percentage-based bulk adjustments to OWNER/FEE_EDITOR and excludes opening balances in the service.
- `FeeController` has a separate payment recording and reconciliation path. The current cashier absence means the desired operational collection role is not yet wired.
- `FeeManagementController::authorizeClassScope()` checks explicit class scopes when any exist; if no class-scope rows exist for a non-owner manager, it currently does not reject based on class scope. Current manager roles are owner/principal/accounts, so do not use this as a substitute for future coordinator class-scoping.

## 11. Additional pass — offline sync and Windows client security

### Current supported offline scope
- `TagoreSyncController` exposes login, bootstrap and push endpoints under `/api/v2/tagore/sync`.
- `OfflineSyncService` bootstraps only the authenticated user's assigned classes/students and currently accepts only attendance upserts.
- The Windows Tauri client stores a local bootstrap snapshot and attendance outbox in IndexedDB; it supports present/absent capture and later sync. It is not an offline cashier, HR, hostel or general ERP client.
- `TagoreOfflineSyncTest` verifies only that three routes are registered. It does not exercise login, class isolation, offline capture, duplicate sync, failed-change recovery or conflict resolution.

### Security and reliability risks
- The local session token and student/class snapshot are stored in IndexedDB without application-level encryption. Lost-device protection, secure credential storage, device revocation and local data retention need a design.
- Device UUID is client-supplied and `registerDevice()` upserts by UUID; define collision/reassignment and device revocation controls.
- Sync failures are persisted with status `failed`. The client deletes only `applied` changes from its outbox, so a failed change will be retried with the same client_change_id; the server returns the prior failed result and does not reprocess it. Provide a reviewed retry/requeue path and visible conflict resolution.
- The push request validates the outer array but does not apply a complete nested schema for each change. The service checks required attendance keys and class-teacher/student relationships, but the inspected code does not strictly validate allowed status values, session enum, date policy, reason IDs or payload size per change.
- Idempotency exists through unique client_change_id, but failure replay, duplicate concurrent pushes and audit/attendance correction rules need executable tests.
- API token expiry/ability restriction and lost-device revocation were not evidenced in this pass.
- Status: narrow attendance sync foundation present; safe offline production acceptance is not yet established.

## 12. Test and CI evidence

### Existing automated coverage
- `TagoreRoleAccessMatrixTest` checks permission declarations and one fee-management route across eight roles. It does not include CASHIER and does not comprehensively test cross-institution or class-scope IDOR cases.
- `TagoreCoreModuleSmokeTest` checks that the Owner can open core module landing pages and that routes are named/resolvable. HTTP 200 is not workflow acceptance.
- `TagoreOperationsAcceptanceTest` currently tests blank inventory quantity defaults, negative expense amount rejection and invalid status/JSON rejection. It does not establish full visitor/hostel/transport/inventory lifecycle completeness.
- `TagoreFeeWorkflowAcceptanceTest` provides meaningful service-level coverage for offline partial payment, ledger/receipt/audit atomicity, overpayment rejection, percentage adjustments and parent statement privacy. It does not establish family multi-child checkout or complete gateway concurrency/refund/reversal behavior.
- `TagoreOfflineSyncTest` checks route registration only; it does not execute sync.
- `TagoreExaminationTest`, `TagoreOlympiadTest`, `TagoreDailyDiaryAcceptanceTest`, `TagoreTasksTest`, migration tests and other module suites exist and need a full execution/read-through.

### Browser acceptance design
- `e2e/tests/tagore-human-style.spec.js` lists eight roles (Owner, Principal, Coordinator, Teacher, Parent, Student, Accounts, Fee Editor). It checks route responses and representative rendered pages; it does not include Cashier.
- `e2e/tests/tagore-interaction-audit.spec.js` inventories controls and screenshots concrete routes. It deliberately avoids firing destructive or financial submit actions; those require dedicated synthetic workflow tests.
- `ERP CI` includes backend PHPUnit, desktop/mobile browser E2E and production Docker smoke jobs. `ERP Full Acceptance Convergence` includes mutating administrator acceptance, full authenticated browser coverage, Docker build and deployment-script syntax checks.
- At the latest observed branch head `9836a7032834e82059d33a93d905bd8427b356b9`, ERP CI and TagoreK12 Prototype runs were queued and ERP Full Acceptance Convergence was pending. No pass/fail result is available yet.
- The current audit itself did not execute PHPUnit, migrations, Playwright, Docker or mobile builds. Do not report runtime acceptance as passed until the associated CI runs complete and their artifacts/logs are reviewed.

## 13. Additional pass — legacy fee import and existing PR reconciliation

### Legacy fee import and student mapping
- The legacy import flow has a useful safety layer: numeric legacy student identifiers are not treated as Gego user IDs; explicit mapping is required. Exact-name matching is accepted only when unique. Fee rows validate negative amounts, discount/concession over gross and received above net before apply.
- `LegacyStudentMatcher` provides ranked candidate suggestions using registration number, name, parent name, mobile and DOB; only candidates scoring at least 90 are auto-mapped. Ambiguous equal top scores are reduced before auto-apply. Add a minimum winner-vs-runner-up margin rule and manual-review path for near ties.
- **Confirmed manual-mapping defect:** `LegacyStudentMappingController::store()` uses `$batchId` inside a transaction closure but does not capture it in the closure's `use (...)` list. The mapping table upsert may succeed, but the corresponding import-row update filters on an undefined/null batch ID, leaving the row unresolved and batch status stale. Add a regression test and correct the closure capture before any legacy fee import is used.
- Import apply is wrapped in a database transaction and rejects batches with error/skipped rows through `LegacyFeeImportSafetyService`. Still verify the complete source workbook, duplicate row handling, receipt uniqueness, fee structure/class assignment and transport assignment reconciliation using synthetic fixtures.

### Existing PR reconciliation — do not merge blindly
- **PR #67** (`research/erp-market-benchmark-and-gap-register`) and **PR #71** (`research/examination-olympiad-market-audit`) are both open drafts targeting `main` and both modify the same path: `docs/ERP_MARKET_BENCHMARK_AND_MODERNIZATION_REGISTER.md`.
- PR #71 contains the full earlier register plus sections 16+ for the dedicated examination/Olympiad audit. It is materially larger than PR #67 (approximately 113.8 KB vs 84.6 KB at inspection). Treat #71 as the later/superset candidate, but compare the complete file history and review comments before deciding whether #67 should be closed as superseded. Merging both separately risks a same-file conflict/redundant history.
- **PR #69** (`integration/t304f-multi-site-device-registry-clean`) is an open draft whose base is `integration/biometric-import-spec-clean`, not `main`. It includes useful proposed changes: add CASHIER role; permit cashier collection; add coordinator class/section fee visibility scoping; make ACCOUNTS group-wide for fee/payroll views; and add a Realtime T304F device registry plus employee mappings and punch table.
- PR #69 does **not** provide a complete biometric ingestion/sync workflow: its controller registers devices and maps staff; sync remains disabled/manual-config and there is no vendor event ingestion route in the reviewed patch. Its test only proves coordinator denial without an explicit fee scope and rejection of student-to-device mapping; it does not fully prove teacher class scope, coordinator assigned-grade scope, cashier collection, group Accounts roll-up or device punch idempotency.
- Because #69 is based on an integration branch, it must be rebased/reconciled against current main and its dependency diff reviewed before any merge decision. Do not cherry-pick only the controller without its migration, seeder, routes, views and tests.
- PR #72 is the new canonical research package on its own branch and does not replace or merge application changes from #69. All four existing PRs (#67, #69, #71, #72) remain open drafts at the time of this audit; none is merged.

## 14. CI results — exact evidence

### Successful initial PR #72 candidate
The first PR #72 head (`13c53b8bd8fc6c2e112ae1b6fcc6ac29d4337df9`, documentation-only package) completed these workflows successfully:
- **ERP Full Acceptance Convergence:** success. Its job completed PHP syntax and clean disposable DB setup, route/scheduler bootstrap, backend feature/unit tests, production frontend build, disposable application health check, mutating administrator acceptance, focused Operations search/status acceptance, full authenticated desktop/mobile browser suite, public login checks, production Docker build/smoke and Windows deployment PowerShell syntax checks. The fail-closed decision step succeeded.
- **ERP CI:** backend, browser-E2E, production Docker smoke, frontend build and release-blocking acceptance gate all succeeded. Backend log reports **145 tests passed, 1,327 assertions**. Browser log reports **92 tests passed**.
- **TagoreK12 Prototype:** prototype E2E passed; Tagore suite log reports **93 tests passed, 1,022 assertions**; MySQL migration smoke and release-blocking prototype acceptance gate succeeded. The prototype-specific E2E suite reports **14 tests passed, 141 assertions**.

### Latest docs-only candidate
The audit documents were subsequently expanded. Latest recorded PR #72 head is `4f452539ebf159e05bf7d764937ea402d63f08dc`. At the latest check:
- ERP Full Acceptance Convergence: pending, no jobs started.
- ERP CI: queued (frontend, backend, browser-E2E and production Docker smoke jobs).
- TagoreK12 Prototype: queued (prototype E2E, test and MySQL migration smoke jobs).

All changes between the successful initial candidate and this latest candidate are documentation-only; no application code changed in PR #72. Nevertheless, the latest-head reruns must finish before reporting the current PR check suite as green.

### Interpretation
These CI runs are valuable evidence that the current application baseline builds and passes its existing automated suites in disposable CI environments. They do **not** prove every business requirement in the research report is implemented, nor do they cover the newly identified missing/unsafe edge cases unless a specific test exists. In particular, a green baseline suite does not negate the class-scope, family checkout, gateway, import mapping, report export, document privacy or operational cross-institution gaps recorded above. No production database or live records were changed by these CI jobs.

## 15. Open modernization PR reconciliation — current main vs proposed branches

The open PR list was checked on 14 November 2026. All listed PRs below remain open; proposed branch code is not automatically part of main. This section distinguishes what is already on main from what remains only in an open branch.

### Feature and UI PRs
- **#63 Cashier counter** (base main): adds a dedicated cashier page, outstanding-student search, offline payment recording, today's collection/receipt KPIs and recent receipts. It is **not on main** (main has no cashier route/view). The first version allows OWNER/PRINCIPAL/ACCOUNTS only; #69 later adds CASHIER. Gaps: no aged overdue/follow-up queue, reminder workflow, daily cashier close/reconciliation, fee-item allocation preview or family checkout. The UI allocates through the existing oldest-outstanding-first payment service.
- **#64 Profile/logout** (base #63): stacked on the cashier branch. It is part of the proposed #63→#64→#68→#69 integration chain; do not merge it directly to main without its parent or a deliberate rebase.
- **#68 Biometric/import specification** (base #64): documentation-only integration specification. It is not a T304F adapter.
- **#69 T304F registry and finance-role port** (base #68): adds cashier role/permissions, scoped fee behavior and device registry/mapping/punch schema. It remains setup-only; no vendor punch ingestion. It also permits CASHIER into the Accounts workspace; review/hide bank/gateway settlement IDs, settlement amount/date/status and pending settlement indicators from the cashier view. Cashier should see operational collection follow-up, not bank settlement reconciliation.
- **#65/#66** are predecessor branches superseded by the cleaner #68/#69 chain. Do not merge both predecessor and replacement PRs.
- **#62 Admissions CRM workspace** (base main): proposed enhancements include due/overdue follow-up date handling, staff assignment selector and a `convert()` action. The conversion links an already existing active student account in the same school to a lead; it does not create the student account, admission enrolment, fee demand or documents. It does not prove current-year enrolment, and it does not prevent the same student from being linked to another lead. The conversion migration/route are not on main. Review idempotency and duplicate-link controls before adopting.
- **#61 Shared UI foundation** (base main): adds `tagore-design-system.css` and links it from `resources/views/layouts/app.blade.php`. The main layout does carry the `tagore-ui` body class, so legacy pages using that layout can receive the styles. Many Tagore-specific views (including dashboard/administration and module pages) are standalone `tg-app` documents rather than children of this layout; the stylesheet link alone will not modernise those pages. A shared Tagore layout migration or explicit stylesheet loading is still needed for complete parity. This PR is not on main.
- **#52 Owner user-account management** (base main): the core functionality is already present on current main: owner-only create user, reset password, enable/disable, role assignment and `tagore_user_admin_audits` migration/routes/view. PR #52's branch differs from main and appears stale/superseded for the basic account-management capability; compare its extra changes before any merge. Current main still needs review for one-time password handling, forced password change/invitation, email verification semantics, last-owner lockout protection and cross-institution Accounts assignment.
- **#42 Family fee consolidation and shared diary** (base main): current main already has a family balance summary and `DailyDiaryController`/migration/tests, but the branch has an older implementation and is not the authority for current behavior. The current family summary is not a family-wide checkout. Do not merge the older branch over current main without comparing exact content and tests.
- **#60 Product modernisation baseline audit** (base main): an earlier audit document; superseded as a research package by PR #72 and the preserved expanded PR #71 register. Retain as history, do not merge blindly.
- **#56 Production dirty-checkout/mobile operations filters**: modifies production deployment workflow and operations UI; keep separate from functional ERP modernization and review production implications independently.

### CI / workflow PRs
- **#49 and #59** both modify `.github/workflows/live-acceptance.yml`; #59 adds representative role acceptance and documentation. **#70** also changes that same workflow to prevent temporary `work/**` validation branches from triggering the live production sweep. Review the combined workflow and preserve the read-only/no-mutation guarantee before merging any of these.
- **#33** Android emulator smoke automation, **#31** final ERP browser gate and older deployment PRs #27/#22/#21 are separate CI/deployment work. They must be reconciled with current workflow files and release policy, not mixed into feature implementation.
- **#38** dashboard search is a separate UI utility. Confirm it indexes only visible/authorised modules after role and institution scope is finalised.

### Current integration recommendation
1. Keep PR #72 as the research/requirements record.
2. Treat #63→#64→#68→#69 as a stacked integration chain; rebase and review as one dependency set, with explicit cashier settlement visibility and class-scope fixes.
3. Review #61 UI foundation and create a complete Tagore shared-layout plan before using it as a global style fix.
4. Review #62 admission conversion separately; extend it to a safe admission-to-enrolment workflow only after duplicate and year/class/fee rules are defined.
5. Do not merge stale #42/#52/#60 branches where main already contains newer equivalents; compare diffs and close/mark superseded only after owner decision.
6. Reconcile #49/#59/#70 workflow changes together, preserving no-production-mutation behavior for research branches.

## 16. Whole-repository audit sizing — beyond Tagore-specific controllers

The preserved PR #71 register recorded the following recursive-tree baseline on the same main SHA `a5806356ad317c8126845a6512b80575376a7f63`:
- 7,920 tracked files
- 19 route definition files
- 281 HTTP controllers
- 633 Blade/PHP view files
- 150 database migrations
- 123 model files
- 22 policy files
- 31 feature/unit test files
- 36 console command files
- 0 job files
- 30 listener files
- 4 notification files

The route declaration scan counted 174 route-related declaration lines in `routes/tagore.php`, 107 in `routes/api.php`, 503 in `routes/admin.php`, 255 in `routes/teacher.php`, 77 in `routes/student.php`, 50 in `routes/accountant.php`, 51 in `routes/payroll.php` and 52 in `routes/librarian.php` (these are declaration-line counts, not unique endpoints).

**Coverage boundary:** The current evidence audit has deeply inspected selected Tagore controllers/services and several legacy attendance/timetable controllers, but it has not yet reviewed all 281 controllers, 633 views, 22 policies, 30 listeners, 36 commands or all 19 route files. In particular, legacy Admin, Accountant, Teacher, Student, Parent, Librarian, Receptionist and Payroll portals remain to be mapped against Tagore's modern module surfaces. The 0-job-file count also means queued work may be implemented via listeners/commands or inline dispatch; inventory those paths rather than assuming there are no background processes.

**Next systematic audit deliverable:** generate and commit a framework `route:list --json` artifact from disposable CI, then join each route (method, URI, name, action, middleware) to controller action, view/API, policy, role, institution/class/year scope, model ownership and tests. Use this to find duplicate routes, dead links, unreferenced views/controllers, unsafe GET mutations, missing authorization, unscoped queries and exports. Then create a page/action inventory for all roles and compare the legacy portal with the modern Tagore UI so no functionality is silently lost during modernization.

## 17. Additional pass — Owner user administration and identity security

Current main already has an Owner-only user administration workspace. It is not absent. It supports user creation with a generated password, role/institution assignment, password reset, enable/disable, institution/academic-year/department management and an audit table.

Specific defects and policy gaps:
1. **Account status column bug:** `AdministrationController::index()` selects role assignment ID, user, role and institution but omits `ur.status`. The view reads `$a->status` to display Active/Disabled and to choose the next enable/disable action. It therefore sees null for every row, displays all assignments as Disabled and offers Enable even for active accounts.
2. **Existing sessions/tokens survive disable/reset:** `resetUserPassword()` and `updateUserStatus()` update the password/status and clear `remember_token`, but do not revoke Laravel Sanctum personal access tokens or invalidate other sessions. The offline sync login creates Sanctum tokens, so a disabled/reset account may retain a previously issued API token unless the API middleware independently checks user status. Revoke tokens and test token invalidation.
3. **Email verification is asserted by admin creation:** `storeUser()` writes `email_verified=1` and `email_verified_at=now()` without a verification challenge. Decide whether Owner-entered email is considered verified; otherwise issue an invitation and verify before activating parent/student access.
4. **Group Accounts role assignment is not supported by the current admin form:** `storeUser()` requires an institution; `assignRole()` requires an institution for every non-OWNER role. There is no explicit group-wide ACCOUNTS scope in this UI. PR #69 proposes Accounts global reporting behavior but does not resolve user creation/role assignment policy.
5. **Owner dual-control inconsistency:** `storeUser()` rejects creation of another OWNER role, but `assignRole()` explicitly permits assigning OWNER to an existing user with no second-owner approval. Define one consistent break-glass/dual-approval rule.
6. **Academic-year activation:** `storeAcademicYear()` permits a new row with status active without deactivating or rejecting another active year for the same school. Add a single-active-year constraint or explicit transition workflow, with an audit trail.

The role-assignment table is an audit log for account creation/password/status actions; it does not replace a security event trail for role changes, institution scope changes and failed privileged attempts.
