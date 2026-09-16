# Tagore 2026-27 fee migration

The Tagore fee importer accepts the legacy workbook as an input and stores a preview before any ledger changes are made.

## Supported sheets

- `FEE STRUCTURE`: creates institution/year fee structures and fee components from class/standard rows.
- `BUS FEE 26-27`: creates transport routes and, when a student identifier is present, student transport assignments.
- `OPENING`: creates opening-balance obligations. It does **not** create historical payment receipts.
- `XII SCI FEE STRUCTURE`: imports student-specific fee obligations and any clearly identified received amounts as legacy payments.
- `FEE CONCESSION`: imports identified student concessions as approved legacy concessions.

## Matching

Student rows use a stable GegoK12 student ID first. If the source does not contain that ID, a unique exact student-name match may be used. Ambiguous and unmatched rows are marked as errors and are not applied.

Names are never used as a unique key when more than one student matches.

## Safety and repeatability

Preview rows retain the original row payload, source sheet, source row and SHA-256 source hash. Apply only processes rows marked `ready`. Existing opening balances are detected by source hash, and fee/transport structures use deterministic institution/year keys. The importer runs its apply phase inside a database transaction.

## Accounting treatment

Opening balances are represented as a debit/opening financial position with an outstanding fee obligation. They are not backfilled as payments. Legacy receipts from the XII Science sheet are separate successful payment records and allocations.

## Operational workflow

1. Open Tagore → Accounts → Fee Import.
2. Select the institution and the 2026-27 academic year.
3. Upload the exported workbook.
4. Review the preview counts and errors.
5. Resolve unmatched/ambiguous students in the source and preview again.
6. Apply matched rows only.
7. Reconcile the resulting student ledgers and accounts totals against the legacy export before production use.

The importer does not alter the old MySchoolERP system.
