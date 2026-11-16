# Tagore ERP — Initial Requirements and Product-Quality Review

Date: 2026-10-29  
Candidate branch: `admin-acceptance-parallel`  
Release status: **Not approved for production**. This review records source-level evidence and known gaps; passing CI on a candidate is not proof that the deployed site is ready.

## Design benchmarks

The following official product pages were reviewed for patterns, not copied branding:

- Entab One describes role-specific management, staff, and parent/student experiences, a central operational dashboard, fee collection, reports, and mobile access: https://www.entab.in/entab-one.html
- Fedena documents role-specific dashboards and broad academic/administrative modules: https://fedena.com/feature-tour
- Fedena's fee workflow includes fee structures, receipts, defaulter reports, and financial reporting: https://fedena.com/feature-tour/school-fees-management-system
- Edutris promotes a role-specific "what needs attention" workflow with one next action per item: https://www.edutris.com/
- ClassNet emphasizes unified student records, fees, attendance, communication, results, and per-school data isolation: https://classnet.in/

Tagore's UI direction should be: one coherent ERP shell, role-appropriate navigation, actionable dashboards, readable data tables, explicit empty/error states, responsive layouts, and auditability for financial changes. Do not treat marketing claims on vendor sites as independent proof of quality.

## Initial requirements matrix

| Requirement | Current source evidence | Status / remaining proof |
|---|---|---|
| Role-specific access for owner, principal, coordinator, teacher, parent, student, accounts, and fee editor | Role matrix feature test and route-level authorization checks; fee-management route denies Coordinator and fee editor | Candidate CI and authenticated browser results must pass for exact SHA; test live accounts separately |
| Parent fee view should not expose internal transaction history | `FeeController::student` returns an empty payment collection to Parent/Student roles while still showing fee obligations and balances | Verify parent screens and every alternate payment/history endpoint in authenticated browser and API tests |
| Email fee receipts to parents | `FeeReceiptMailer` creates a PDF receipt, sends email through configured mail transport, and records delivery status | SMTP/profile configuration, queue/runtime delivery, bounce/failure handling, and a real non-production email test remain required |
| Percentage-range fee adjustment | `FeeEditorService::bulkAdjust` accepts a bounded percentage range and logs old/new values per obligation | Recent change also updates line items and rejects a decrease below amounts already paid; test must pass on the exact candidate SHA |
| Original fee history kept separately from edits | Fee-year archive service encrypts the closed-year payload, stores a SHA-256 integrity hash, and records a vault/audit event; opening balances are excluded from bulk adjustment | Verify owner-only access, archive/restore readability, retention, and reconciliation against the live ledger |
| Annual/year-end fee archive | `ArchiveClosedTagoreFeeYears` can archive closed academic years | Confirm scheduler registration and successful run; this is not a full-system disaster-recovery backup |
| Full database and uploaded-file backup, with off-machine copy and restore drill | Production deployment README explicitly says to establish daily off-machine backups; no verified full-system backup/restore acceptance evidence is recorded here | **Open release blocker** until automated backup, off-machine retention, integrity checks, and a documented restore rehearsal are proven |
| Editing controlled by separate user access | Fee Editor role and dedicated fee-editor routes exist; owner-only vault access is separately guarded | Verify all edit endpoints, bulk actions, cross-institution scopes, and audit events through negative tests |
| Original payment/financial transactions preserved | Payments are recorded with separate financial ledger entries and audit events; overpayment is rejected transactionally | Verify no ordinary role can alter/delete prior payment rows; test refunds/reversals and ledger reconciliation before release |
| Search and day-to-day navigation | Dashboard module/item search exists; recent UI update adds result/no-result feedback, Escape-to-clear, and Ctrl+/ focus shortcut | Browser tests are queued for the changed candidate; review screenshots at desktop and mobile widths |
| Responsive UI and usable forms/tables | Shared Tagore stylesheet includes responsive layouts, visible keyboard focus, table overflow handling, and mobile navigation | Automated overflow/console/asset checks plus visual review of representative role pages still required |
| Web ERP complete before Windows/Android acceptance | Web acceptance is the current priority; client checks remain separate | Do not call overall project complete until all required web gates pass; only then resume Windows/Android acceptance |
| Live production behavior matches candidate | Live acceptance workflow can run read-only browser checks, but latest inspected runs skipped the live browser job due missing live-account configuration | **Open release blocker**: configure dedicated synthetic live test accounts and run the live browser gate; do not deploy candidate merely to make the gate pass |
| AI, CCTV analytics, social publishing, and other provider integrations | Some screens deliberately show unconfigured/disabled status; internal social drafts are not external publishing | Treat as not operational until provider credentials, approvals, and end-to-end acceptance tests exist. Do not represent a placeholder as a working integration |

## Changes made during this review

1. Dashboard search now filters role-visible sidebar links as well as modules, KPIs, and list items.
2. Search provides an accessible live result count/no-results message; Escape clears the query and Ctrl+/ focuses the search.
3. Added browser assertions for search feedback and keyboard controls across all eight role fixtures.
4. Fixed fee percentage adjustments to keep obligation totals aligned with fee line items and to reject changes that would reduce a payable below amounts already collected.
5. Fixed payment allocation so payments are distributed to line items when the line-item outstanding total agrees with the parent obligation, preserving line-item balances. Legacy mismatches are not silently redistributed.
6. Added regression coverage for partial payment, overpayment rollback, line-item balances, and fee-editor reductions below collected amounts.

## Release gate

The candidate is not release-ready until the exact current SHA has all required backend, authenticated browser, production packaging, route/role, responsive/visual, and live-site gates passing; the full-system backup/restore gap is closed; and the owner has reviewed the remaining provider-integration scope. A skipped job is not a pass. No production deployment is authorized by this review.

## New requirements: configurable fieldwork forms, entrance assessments, and approval workflows

### A. Owner/Technology Portal — configurable form studio
- Provide an Owner/authorized Technology Controller portal capability to create, preview, publish, pause, close, duplicate, version, and retire reusable data-collection forms without a code deployment.
- Include prebuilt, editable themes/templates for new-admission field surveys, school/parent physical feedback, campus/infrastructure surveys, event/outreach follow-ups, and other approved collection campaigns.
- Form builder must support labelled fields, required/optional flags, help text, sections, ordering, conditional visibility, validation, dropdowns, multi-select, date/time, numeric, text, long text, rating scales, single/multiple choice, consent, photo/document attachments, and location capture where device permission is granted.
- Allow campaign-level settings: institution/campus, target audience/assigned staff, start/end dates, submission limits, offline eligibility, instructions, and reporting visibility.
- Published forms must be immutable snapshots for submissions; edits create a new version. Closing a form must prevent new submissions while preserving existing records.
- Every create/edit/publish/close/export action must be permission-checked and audit logged. Only Owner and explicitly authorized Technology Controller roles can publish globally; institution-scoped delegation must be explicit.

### B. Staff Portal and Mobile — field collection
- Staff can see only forms/campaigns assigned to them or their authorized institution/team, fill them on web or supported mobile app, save drafts, resume, validate, and submit.
- Mobile workflow must support unreliable connectivity with encrypted local draft/outbox storage, visible sync state, retry, duplicate prevention/idempotency, and conflict handling. Do not silently lose submissions.
- Capture submitter identity, assigned campaign, institution, timestamps, form version, and consent/audit metadata. Location and photos are optional and only captured with clear purpose and user permission.
- Provide submission confirmation and a staff-visible history of their own submissions; staff must not edit a submitted record unless a permitted correction workflow creates an auditable amendment.

### C. Admission fieldwork pipeline
- A new-admission fieldwork template must capture prospective student/guardian details, institution/program/class interest, source/campaign, field staff owner, follow-up date/status, consent, and notes/attachments.
- A validated submission should create or update a prospective admission lead, detect likely duplicates, and preserve source attribution. It must not silently create an enrolled student or overwrite an existing family/student record.
- Authorized admissions users can review, qualify, assign, follow up, convert to an application/admission, or reject with reason. Conversion must link the family/guardian and institution correctly and retain the original fieldwork record and audit trail.
- Reports should show campaign response, staff activity, lead stages, follow-up ageing, conversion, and institution-wise outcomes.

### D. Entrance test campaign and admission integration
- Owner/authorized Technology Controller can create entrance-test templates and question banks, configure test sections, marks, duration, pass/qualification rules, class/program applicability, availability window, and attempt policy, then publish/pause/close a test campaign.
- Teachers see only tests assigned to their institution/campaign and eligible classes/programs. They can launch an assigned test for a prospective applicant from the teacher portal/mobile interface during field admission work.
- Support candidate identity/contact capture and consent, instructions, timed delivery, objective and descriptive question types as configured, answer autosave, submission locking, and clear offline/online behavior. Do not promise secure offline timed testing unless device-clock tamper and sync behavior are addressed.
- Store responses, scoring, evaluator, result, test/version metadata, and audit trail. Objective questions may be auto-scored; descriptive answers require authorized evaluation.
- Completing the test should attach its result to the matching admission lead/application and advance the admission pipeline to the configured next stage. It must not create an enrolled student automatically or allow a teacher to alter published marks without an audited re-evaluation workflow.
- Include candidate/test reports, attendance/attempt status, results, and admission conversion analytics.

### E. Discount/concession request approval
- Teachers and other explicitly eligible staff can submit discount/concession requests against a prospective or existing student/admission, with requested amount/percentage, reason, supporting evidence, and institution/fee context.
- Requests go to the Owner for approval or rejection; no discount changes the fee ledger, payable, invoice, or receipt until Owner approval and a separate authorized fee workflow applies it.
- Owner can approve/reject/return for information, with comments and audit trail. Enforce institution/student scope, duplicate/pending-request checks, approval limits if later configured, and immutable decision history.
- Teachers can view the status of their own requests but cannot approve, apply, or modify fee concessions.

### F. Employee leave approval routing
- Leave requests route to the Principal of each institution in which the employee has an active assignment. For employees assigned to multiple institutions, create a clear approval route to each relevant Principal or a configured consolidated decision policy; do not assume one institution or silently bypass a Principal.
- Show dates, leave type, reason, attachments, balance where configured, staffing/overlap context, and request status. Principal can approve/reject/return with reason within their institution scope.
- Define cross-institution consistency before final approval: a multi-institution employee's leave is effective only when all required institution approvals are resolved according to the configured policy. Owner retains oversight/escalation, not routine substitution for Principal approval unless explicitly authorized.
- Prevent self-approval, overlapping duplicate requests, unauthorized cross-institution decisions, and silent balance changes. Record decisions and balance adjustments in audit history.

### G. Roles, privacy, reporting, and phased delivery
- Preserve the single top-level Owner model. Technology Controller permissions must be explicit and must not imply a second Owner or generic Super Admin.
- Enforce institution, campaign, assigned-staff, applicant/family, and record-level access on both UI and API; hide-only UI controls are not authorization.
- Keep applicant and feedback data private, apply retention/export controls, consent and attachment access rules, and audit access to sensitive submissions.
- Add automated allow/deny tests for Owner, Technology Controller (where assigned), Principal, Coordinator, Teacher, Accounts/Cashier, Parent, and Student; test cross-institution and unassigned-campaign denial.
- Suggested implementation phases: (1) schema, authorization, audit, form template/version engine; (2) web form builder and staff web submission; (3) admission lead pipeline and reporting; (4) entrance-test authoring, delivery, scoring and pipeline link; (5) discount approval; (6) multi-institution leave routing; (7) mobile offline drafts/sync and client parity.
- Treat each phase as incomplete until migrations, API/UI behavior, negative authorization tests, responsive browser tests, and synthetic end-to-end workflows pass on the same candidate commit. Do not deploy partial screens as completed functionality.

