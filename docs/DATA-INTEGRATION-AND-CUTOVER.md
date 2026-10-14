# Tagore ERP data integration and rollout plan
Date: 2026-10-10

## Decision
Integrate school data now in staged, reconciled batches; do not wait for every future feature. Use the existing ERP modules that pass acceptance while feature development continues separately. Never import production records directly into a test dataset without a verified source-to-target mapping and reconciliation.

## Current discovery
- Searched the active source, project, and test-artifact folders for XLS/XLSX/CSV/ODS source files; no candidate school data files were found in those locations.
- The current database contains demonstration users and demo operational records. The school/institution display name was rebranded; this did not turn demonstration records into real school records.
- Do not delete demo rows blindly. Keep them distinguishable and reconcile duplicate/foreign-key collisions during a controlled import.

## Import order
1. Institution / campus / academic year / classes / sections / streams.
2. Students and admissions, with stable external student/admission identifiers.
3. Parents/guardians and student-parent relationships.
4. Staff, departments, roles, and class/subject assignments.
5. Fee structures, concessions, installment schedules, opening balances, and paid-history/receipts.
6. Attendance, transport, hostel, exam/results and other historical data only where source records and target rules are verified.

## Required controls
- Keep an untouched copy of each source file and a checksum.
- Profile row counts, required columns, date formats, duplicate IDs, missing parent/student links, invalid amounts, and conflicting identifiers before writing data.
- Run a dry-run mapping and produce a rejected-row report first.
- Use deterministic external-ID mappings; do not match people solely on names.
- Import in repeatable batches with an import-batch ID and idempotent upserts.
- Reconcile source/target counts, class-wise student totals, guardian links, fee opening balances, receipts, concessions and outstanding totals before accepting each batch.
- Preserve transaction/receipt history; never synthesize receipts or payment confirmations.
- Keep original fee transaction history immutable; corrections must be separately audited.
- Back up the database before each write batch and test restore before final cutover.
- Keep data import separate from feature code changes; use the real dataset to validate workflows after each batch.

## Cutover gate
A batch is accepted only when all row counts and financial totals reconcile, all rejected rows have a documented disposition, and the affected workflows pass the relevant role-based acceptance tests.

## Required source inputs
The import pipeline is prepared conceptually, but no source spreadsheets/exports were found in the searched server folders. Actual records should be imported from the school's authoritative student/admission register, guardian list, staff list, fee structure and ledger exports when they are made available to the server/import workspace. Until then, no real data is fabricated.
