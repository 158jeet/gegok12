# Accounts, HR, and Assessment Modernization

## 1. Finance access model (confirmed business rule)

The ERP must distinguish a central **Accounts** function from an institution-level **Cashier**.

### Central Accounts (global)
- A user with the ACCOUNTS role has group-wide scope across every active institution and department, regardless of institution-specific role assignments.
- The Accounts portal consolidates fees, collections, receipts, refunds/adjustments where separately authorised, reconciliation, payroll runs, employee records, attendance summaries, and finance reports across all institutions.
- Central Accounts can view and update finance/payroll records across institutions. Every mutation records actor, institution, department, timestamp, before/after values, and reason where applicable.
- Employee records remain assigned to their employing institution and department; global visibility does not transfer or duplicate employment ownership.
- Global access must be enforced server-side in every query and mutation, not merely by showing all institutions in the UI.

### Institution Cashier (local)
- The CASHIER role is assigned to one or more explicit institutions through tagore_user_roles.institution_id.
- Cashier can search students, collect offline fees, record supported payment modes/reference, issue/reprint receipts, and view that institution's daily collection totals and reports.
- Cashier cannot access another institution, global payroll, fee master edits, concessions/refunds, gateway reconciliation, or staff HR records unless a separate permission is explicitly granted.
- Principal/Owner retain institution oversight; central Accounts retains group-wide finance authority.

### Employee and biometric mapping
- Each employee has one employing institution and department as their authoritative HR assignment.
- Device-to-employee mappings are specific to a device and its institution; a user ID on one machine must not automatically map to the same numeric ID on another machine.
- Central Accounts and authorised central HR can view employee/attendance/payroll information across all institutions. Institution HR/Principal see only their assigned institution.
- Payroll is generated per institution and period, then rolled up in central Accounts. Attendance corrections require an auditable reason and approval; raw punches never directly trigger deductions.
- Device registry remains multi-device and multi-location. New devices default to sync disabled until serial, firmware, API entitlement, credentials and WSDL contract are verified.

## 2. Olympiad and entrance-test modernization

### Existing implementation observed in the repository
- Tagore has a separate Olympiad event workflow with institution and target-class scope, registration windows, timed student attempts, answer saving, server-side scoring, and private student results.
- Questions can be imported from XLSX/XLS/CSV/JSON; imported questions require manager approval before publication.
- The current importer accepts single-choice questions only and imports up to 200 questions per batch.
- A separate school Examination Engine supports academic-year/class/section examinations, subject schedules, marks entry, moderation, grading scales and result publication.
- docs/OLYMPIAD-ERP-INTEGRATION.md describes Exameow as an isolated pilot, not an approved production/student-facing service.

### Target product direction
Unify the experience under an **Assessment & Testing** workspace while retaining distinct workflows for:
1. School Olympiads and internal competitions.
2. JEE/NEET and entrance-exam practice tests.
3. Chapter/topic tests, weekly tests, mock exams and full-length simulations.
4. School examinations and report-card assessments.

### Assessment authoring and question bank
- Central reusable question bank with institution/group ownership, subject, class, exam, chapter/topic, learning outcome, difficulty, language, marks, negative marks, source, tags and version.
- Support single/multiple choice, numerical answer, integer/range, assertion-reason, passage/case-based and subjective questions according to assessment type.
- Bulk question import templates with sample download, validation preview, row errors, duplicate detection, answer-key validation and explicit commit.
- Human review workflow: draft → peer review → approved → published; version questions so edits cannot silently alter a live or completed attempt.
- Blueprint builder: subject/topic weightage, difficulty mix, question count, total marks, duration, section rules, optional/compulsory questions and negative marking.

### Test delivery
- Responsive web/PWA exam player with section navigation, question palette, answered/review-marked/unanswered states, autosave status, server-authoritative timer, reconnect/resume and final submission confirmation.
- Autosave each answer with idempotency and conflict protection; retain an attempt event log. On network loss, queue safe local changes and reconcile on reconnect without extending the server deadline.
- Randomize question and option order where configured; create per-attempt question snapshots so the answer key cannot change mid-exam.
- Accommodate accommodations/extra time only through explicit authorised configuration and audit.
- Do not add invasive webcam/biometric proctoring by default. Any proctoring must be separately approved with privacy, consent, retention and legal review.

### Results and learning analytics
- Server-side scoring, negative marking, partial credit where question type permits, manual moderation for subjective items and controlled re-evaluation.
- Student view: score, accuracy, attempted/unattempted, time spent, topic-wise strengths/gaps and rank/percentile only when cohort rules make it meaningful.
- Teacher view: class/section performance, question difficulty/discrimination, topic mastery, misconception clusters and students needing intervention.
- Management view: institution/cohort comparisons with filters and export, while respecting student-data access.
- Parent view: own linked child's reports and recommended practice only.
- Publish result only after review; maintain result version, recheck/objection workflow and audit trail.

### Entrance-exam preparation
- JEE Main/Advanced and NEET configurable exam patterns and subject/section structures, with dated pattern versions rather than hard-coded assumptions.
- Full-length mocks, part tests, chapter tests, daily practice questions, previous-year-paper ingestion only where licensing permits, and scheduled test series.
- Detailed post-test solutions/analysis, time-management insights, accuracy vs speed, topic-wise gap list, recommended revision/practice sets and longitudinal progress.
- Teacher-curated assignments and remediation groups; do not present AI-generated answer keys or solutions as authoritative without review.

### Acceptance gates
- Test role matrix for central Accounts, institution Owner/Principal, assessment coordinator, teacher, student and parent.
- Verify cross-institution isolation for local roles and group-wide visibility for central Accounts.
- Verify question answer keys are never sent to student clients before submission.
- Verify timer cannot be extended by changing client clock, refreshing or disconnecting.
- Verify autosave/reconnect, duplicate submissions, concurrent requests, late submissions, re-evaluation and result publication.
- Verify imports do not create partial/duplicate questions and template download matches parser.
- Verify mobile usability, accessibility, load/performance under concurrent attempts, backups and restore.
- Use synthetic fixtures until explicitly approved for live student data.

## 3. Delivery sequence
1. Lock and test the central Accounts vs institution Cashier permission model.
2. Complete the reusable bulk-import framework and first Student, Staff, Fee Structure and Question Bank adapters.
3. Finish the T304F multi-device registry, polling adapter, raw punch reconciliation and institution-specific employee mappings.
4. Build HR attendance exception handling and approved attendance-to-payroll integration.
5. Modernize the Assessment & Testing workspace in slices: question bank/import → test blueprint → exam player/recovery → scoring/results → analytics/entrance test series.
6. Keep Exameow isolated until its APIs, license, security, privacy, backup and failover have passed review; use it only if it adds value without duplicating the ERP engine.

No production data, device credentials, biometric templates, or live student exam records are used in CI.
