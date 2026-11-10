# Tagore Olympiad Integration — Parallel Workstream

## Goal
Deliver Olympiad functionality inside the Tagore ERP while keeping the parent experience lightweight and preserving the ERP's stability. Reuse open-source components before building duplicate infrastructure.

## Current pilot boundary
Exameow is running as an isolated pilot container on loopback port 3435. It is not yet an approved production/student-facing service. It must remain isolated until authentication, authorization, data retention, backups, language/branding, and end-to-end tests pass. Do not expose its current host port publicly or connect it to real student data during evaluation.

## Integration stages
1. **Discovery and safety:** inventory supported exam/question/scoring APIs, authentication, export formats, persistence and retention behavior; confirm upstream expiry and licensing.
2. **ERP module:** implement role-protected Olympiad administration for events, eligibility/classes, registration windows, fee policy if applicable, exam windows, status, and result publication. Use ERP identities and institution scoping.
3. **Question workflow:** provide a teacher/admin-only question generation/import workflow; require human review and approval before publishing a question set.
4. **Student experience:** lightweight responsive web/PWA experience first; students see only eligible published Olympiads and their own attempts/results.
5. **Integration:** use a documented server-to-server adapter and stable IDs; never rely on iframe/session sharing or browser-side direct database access. Sync only minimum necessary data and log reconciliation failures.
6. **Validation:** test permissions, duplicate registrations, deadlines/time zones, exam start/resume/submit, scoring, result visibility, accessibility/mobile layouts, backup/restore, and upstream outage behavior.
7. **Release:** pilot with synthetic data, then a limited internal group; production rollout only after all mandatory quality gates pass.

## Data/security rules
- Keep the pilot on a private Docker network until explicitly approved.
- Do not send real student/parent information to external AI services without a reviewed privacy/data-processing decision.
- Never trust client-side scores; verify scoring server-side and audit result changes.
- Keep exam answer keys and unpublished questions inaccessible to students.
- Establish retention/export/deletion and backup policies before real records are loaded.
- Preserve existing ERP schema and behavior; ship Olympiad migrations additively and reversibly.

## Acceptance criteria
A release candidate must demonstrate admin event creation, student registration eligibility, teacher question approval, secure exam access, score calculation, result publication, role separation, audit trail, mobile usability, and automated regression evidence. Until those pass, the integration remains a pilot and is not represented as production-ready.

## Implementation update — 12 October 2026

The pilot now includes an end-to-end workflow for managers and students:

- Create scheduled Olympiad events scoped to an institution and target classes.
- Import single-choice question banks from Exameow-compatible XLSX/CSV, JSON files, or pasted JSON; retain analysis, subject, chapter, and difficulty metadata.
- Keep imported questions pending until an authorized manager approves them; prevent edits/approval after publication.
- Show published events only to students whose current academic record matches the event institution and class.
- Register eligible students once, deliver a timed exam without exposing correct answers, calculate scores server-side, and show private results. Late submissions are flagged for manager review.
- Convert school-local Asia/Kolkata schedule input to UTC storage and render schedule times in India local time.

**Production release blockers:** verify real student-to-class mappings and school/institution links; implement resilient answer auto-save/recovery before using this for a live timed exam; complete production security/performance checks and a backup/rollback rehearsal. Multi-select questions, proctoring, student certificates, and Exameow API integration are not included in this pilot.
