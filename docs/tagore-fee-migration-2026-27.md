# Tagore 2026-27 fee migration

The Tagore fee importer accepts the legacy workbook as an input and stores a preview before any ledger changes are made.

## Supported sheets

- `FEE STRUCTURE`: creates institution/year fee structures and fee components from class/standard rows.
- `BUS FEE 26-27`: creates transport routes and, when a student identifier is present, student transport assignments.
- `OPENING`: creates opening-balance obligations. It does **not** create historical payment receipts.
- `XII SCI FEE STRUCTURE`: imports student-specific fee obligations and clearly identified received amounts as legacy payments.
- `FEE CONCESSION`: imports identified student concessions as legacy concessions.

## Student matching safety

Legacy `Reg No` and `Adm No` values are **not** treated as GegoK12 `users.id` values merely because they are numeric.

The safety layer accepts a student mapping when either:

1. an explicit row exists in `tagore_legacy_student_mappings` for the institution and legacy source key; or
2. the legacy numeric key resolves to a GegoK12 student **and** the source student name exactly matches that user's name after normalisation.

Otherwise the row is marked `error` and cannot be applied. Unique exact-name matching remains available when the legacy row has no usable identifier. Ambiguous names are never silently selected.

## Safety and repeatability

Preview rows retain the original row payload, source sheet, source row and SHA-256 source hash. The safety layer also rejects negative fee amounts, discounts/concessions greater than gross fee, negative opening balances, and received amounts greater than the net fee. The importer never silently caps or discards an overpayment.

A batch containing errors cannot be applied. Rows must be resolved and previewed again before the batch becomes eligible for application. Apply processes only `ready` rows and runs inside a database transaction.

## Accounting treatment

Opening balances are represented as a debit/opening financial position with an outstanding fee obligation. They are not backfilled as payments. Legacy receipts from the XII Science sheet are separate successful payment records and allocations, with the original receipt number retained as the legacy reference rather than replacing the new Tagore receipt sequence.

## Operational workflow

1. Open Tagore → Accounts → Fee Import.
2. Select the institution and the 2026-27 academic year.
3. Upload the exported workbook.
4. Review the preview counts and every error row.
5. Add explicit legacy-to-GegoK12 student mappings where the legacy identifiers cannot be safely resolved.
6. Preview again until the batch has no errors.
7. Apply the batch.
8. Reconcile student ledgers, receipts, opening balances and accounts totals against the legacy export before production use.

The importer does not alter the old MySchoolERP system.
