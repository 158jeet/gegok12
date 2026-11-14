# Tagore ERP role and daily-workflow functional audit

**Audit date:** 14 November 2026  
**Code baseline:** `main` plus the separately reviewed admissions CRM work in PR #62  
**Scope:** Cashier/accounts, admissions/front office, student enrolment, and academic-year/class promotion.

This audit distinguishes a route, controller, or page existing from a staff member being able to complete the real operational job end to end.

## Executive finding

The current ERP has useful underlying fee, admission-lead, student-academic, and promotion components, but the staff operating model is incomplete. There is no distinct cashier/front-office role and no dedicated cashier counter in the Tagore workspace on the audited baseline. The legacy Accountant portal is not a substitute: its route file is largely dashboard, tasks, notices, events, and profile functions. The modern Tagore Accounts page is primarily a financial overview and reconciliation screen.

The codebase therefore has components for these jobs, but the work is split across legacy and Tagore workspaces, with role gaps and missing operational screens.

## Verified findings

### 1. Cashier / daily fee collection

- The modern role catalogue contains `ACCOUNTS`, not a distinct `CASHIER` or `OFFICE` role. `ACCOUNTS` has fee view/manage, payment view/reconcile, and reporting permissions; it does not have admission or academic-promotion permissions.
- `routes/accountant.php` does not define a cashier collection route. The legacy Accountant dashboard only includes an optional unpaid-fees widget when the legacy `gfee.enabled` flag is enabled.
- `GET /tagore/accounts/fees` renders totals, family balances, recent successful payments, and online settlement reconciliation. It has no student search/selection or offline payment form.
- A backend payment action already exists at `POST /tagore/accounts/fees/student/{studentId}/payment`. It validates payment amount/mode, calls `FeeService::recordOfflinePayment`, allocates to the oldest outstanding obligations, creates a receipt number and emails the receipt. The missing link was the daily operator interface.
- Fee structure setup and bulk demand generation are present under Fee management, but those are fee-master/administration workflows, not the cashier's collection counter.

**Change in this branch:** a dedicated `/tagore/cashier` workspace is being added for OWNER, PRINCIPAL, and ACCOUNTS. It searches students with balances, displays institution and outstanding totals, records cash/cheque/bank-transfer/other payments through the existing ledger-safe endpoint, shows today's posted collection, and lists recent downloadable receipts. Coordinators and read-only roles are intentionally excluded from payment entry.

### 2. Admissions / front office

- There are **two separate admissions systems**. The modern Tagore Admissions CRM is a lead/enquiry pipeline. The legacy Admin Admissions module (`/admin/admissions`, `/admin/admissionlist`, `/admin/admission/show/{id}`, `/admin/admission/edit/{id}`) is the older application-review and approval flow.
- The legacy Admin controller can approve an application, set section/fee group/payment status, create the student account and father/mother accounts through `AdmissionUser`, and send approval notifications. Therefore, actual admission creation exists under the legacy Admin Admissions name; it is not yet integrated into the modern Tagore office workflow.
- The Tagore CRM's `AdmissionsController::STAFF_ROLES` is OWNER, PRINCIPAL, and COORDINATOR. ACCOUNTS, the legacy Accountant, and Receptionist are not included.
- CRM lead conversion requires an already-created active student account in the same institution and links the lead to that account. It does not itself run the full application approval, student/guardian account creation, academic enrollment, fee demand, document, and receipt process.
- The legacy Admin admission controller had a high-priority school-scope defect: list/show filters combined `where(...Draft)` with an ungrouped `orWhere(Pending)`, which could return pending applications outside the current school/year. Edit, update, and delete also fetched by admission ID without consistently constraining the record to the signed-in user's school. This branch fixes the list/show status grouping and adds the signed-in school constraint to edit/update/delete. Regression tests are still required before relying on the legacy screen for office operations.
- The legacy `AdmissionUser` trait creates student and parent users with a hard-coded `password` password. That is not an acceptable onboarding mechanism. Do not reuse this trait for the new Tagore workflow; replace it with secure account invitation/activation and password setup.
- The Receptionist route set is mostly visitor log, call log, postal records, events, tasks, notices and staff utilities; it does not provide the missing modern cashier/admission/promotion counter.

**Required next work:** consolidate the legacy application-approval capability with the modern lead CRM behind a least-privilege Front Office / Admissions role (not silently grant the cashier unrestricted student or academic administration). Add duplicate checks, guardian/family linking, student profile creation, academic-year/class placement, documents, fee-plan assignment/demand, and a completion checklist. Fix the legacy school-scope defect and make approval idempotent/transactional. Preserve principal/owner approval for sensitive concessions and exceptions.

### 3. Class promotion / academic-year rollover

- Promotion routes exist only in the legacy Admin route set: `/admin/promotion/list`, `/admin/promotion/create`, `/admin/promotion/export`, and `/admin/promotion/import`. There is no corresponding promotion workspace or route in `routes/tagore.php`.
- The legacy workflow exports a CSV and imports it through `PromotionImport`. It uses session-held year/class selections and mutates student academic records; it is not integrated into the modern Tagore academic workspace or its role-permission matrix.
- The import's first student-academic lookup is by roll number alone, and the code assumes a matching record exists before dereferencing it. The workflow needs explicit school, current-year, class, and student scoping, row-level validation, preview/dry-run, duplicate protection, and a clear transaction/error report before it is suitable for a reliable year-end operation.
- The modern Tagore role matrix has no explicit `promotion.manage` permission. A cashier should not receive class-promotion authority merely because the person handles front-office work.

**Required next work:** implement an academic-office promotion workspace with current/next year and class mapping, pass/fail/hold/alumni decisions, bulk preview, row-level validation, conflict detection, guardian/student communication, audit history, and reversible correction workflow. Promotion authority should be separately granted to the principal/academic office.

## Recommended role model

| Role | Daily responsibilities | Should not automatically be allowed |
|---|---|---|
| Cashier / Accounts Operator | Student balance lookup, fee collection, payment modes, receipt reprint/email, day collection report | Fee-master edits, refunds, concessions, admission approval, class promotion, user administration |
| Front Office / Admissions | Enquiry capture, follow-up, admission checklist, student/guardian onboarding, documents, class placement request | Cash posting, fee concessions/refunds, final academic promotion |
| Academic Office | Class/section allocation, promotion preview/import, academic-year rollover, results and student academic records | Cash collection, financial reconciliation |
| Principal / Owner | Approvals, oversight, delegated permissions, exception handling | Routine cashier actions need not be performed by senior management |

Role assignments must remain institution-scoped. Keep a clear separation between collecting money, changing fee masters, approving concessions/refunds, reconciling settlements, and promoting students.

## Acceptance requirements

### Cashier counter
- Accounts Operator can search by student name, mobile, or ID and see only students in assigned institutions.
- A partial payment posts successfully and reduces the correct oldest outstanding obligations; overpayment is rejected without a ledger/payment row.
- Cash, cheque, bank transfer, and other modes are recorded with reference number where supplied.
- Receipt number/PDF is available and receipt email is queued/sent where an address exists.
- Today's total and receipt count reconcile to posted successful payments.
- Cashier cannot edit fee structures, issue refunds, reconcile gateway settlements, or see internal audit history unless separately authorised.
- Owner/Principal can supervise; Coordinator, Teacher, Fee Editor, Parent, and Student cannot post payments.

### Admissions
- Front-office operator can complete an enquiry through a real student admission, not just link an already-created student ID.
- Student and guardian records are linked safely; siblings and duplicate mobile/email matches are surfaced.
- Institution, academic year, class/section, documents, fee plan and demand are connected.
- Approval boundaries and immutable activity/audit history are tested.

### Promotions
- Academic Office can preview every affected student before committing.
- Every imported row is scoped to the correct school, year and current class; missing, duplicate, invalid, or conflicting rows are reported and do not partially mutate data.
- A successful promotion creates the next-year academic enrollment exactly once and preserves history.
- Alumni, fail, hold, and section-change paths are explicitly tested.
- Principal/Owner approval and correction/audit paths are enforced.

## Release boundary

All acceptance must use disposable synthetic fixtures. Do not seed, mutate, or test against live school data. Do not deploy this work to production until the complete role/workflow acceptance suite passes and the release is explicitly approved.
