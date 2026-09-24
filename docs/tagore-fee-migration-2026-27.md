# Tagore 2026-27 fee migration

The importer treats the legacy workbook as a controlled migration source. Preview is non-destructive; application changes only the new Tagore database.

## Workbook handling

The parser recognizes:

- `STUDENTS`: legacy student master reference. GegoK12 remains the student system of record; this sheet is not blindly duplicated.
- `FEE STRUCTURE`: class-level fee heads are imported as fee components. The legacy one-time amount is retained separately as a `One Time Fee` component.
- `OPENING`: creates opening-balance obligations; it does not fabricate historical receipts.
- `BUS FEE 26-27`: creates transport routes and safe student assignments.
- `XII SCI FEE STRUCTURE`: imports student-specific fee obligations and identified legacy receipts.
- `FEE CONCESSION`: imports identified concessions for audit.
- `ALL LEDGER`: retained as a reconciliation reference; it is never treated as payment history.

## Student matching safety

Legacy numeric Reg/Adm identifiers are never treated as GegoK12 `users.id` values. A numeric legacy key must have an explicit row in `tagore_legacy_student_mappings`. When no usable identifier exists, a unique exact-name match may be used. Ambiguous or missing matches become review errors.

The mapping screen lets an authorized Owner/Principal/Accounts user select the GegoK12 student. A batch cannot be applied while errors remain.

## Accounting rules

- Opening balances are debit/opening financial positions.
- Legacy receipts remain successful legacy payment records with the original receipt retained as the legacy reference.
- Payments are never silently capped or discarded.
- Discount/concession validation prevents values above gross fee.
- Reconciliation never changes the student ledger.
- Apply is transactional and guarded against unresolved review rows.

## Operational workflow

1. Tagore → Accounts → Fee Import.
2. Select institution and 2026-27.
3. Upload the workbook.
4. Review every preview error.
5. Map unresolved legacy students explicitly.
6. Preview again until there are no errors or skipped rows.
7. Apply the validated batch.
8. Open **Reconciliation**.
9. Compare every mapped `ALL LEDGER` balance with the Tagore outstanding ledger.
10. Resolve every mismatch before production use.

The old MySchoolERP system is never modified by this importer.
