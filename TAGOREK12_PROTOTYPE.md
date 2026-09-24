# TagoreK12 Prototype

TagoreK12 is the Tagore Group integration layer built on top of GegoK12. The prototype is intentionally additive: existing GegoK12 school, student, parent, attendance and authentication concepts remain the system of record while Tagore-specific group, scope, fee, payment, feedback and audit boundaries live in `tagore_*` tables.

## Local prototype setup

From the project root:

```bash
php artisan migrate:fresh
php artisan db:seed
php artisan db:seed --class=Database\\Seeders\\TagorePrototypeSeeder
```

The first seed command creates the normal GegoK12 demo schools/users and academic data. The Tagore seeder then links those existing GegoK12 records into the Tagore layer. Do not run the Tagore seeder first on a fresh database.

Then sign in to GegoK12 and open `/tagore/dashboard`.

The prototype API is available at `/tagore/v1/dashboard` for an authenticated Sanctum session/token.

## Design boundaries

- Do not install Fee Pro or Exam Pro to use this foundation.
- Fee/payment tables are Tagore-owned and expose adapter points for gateways and future GegoK12 add-ons.
- Exam integration should use an adapter rather than depending on private Pro tables.
- Parent-child authorization is based on explicit `tagore_parent_students` relationships.
- Institution scope is represented independently from role permissions.
- Financial events should be treated as append-only business events; corrections use adjustments/refunds rather than rewriting successful payments.

## Prototype testing order

1. Confirm `migrate:fresh` completes against MySQL.
2. Seed the base GegoK12 data.
3. Seed the Tagore integration data.
4. Sign in and verify the normal GegoK12 dashboard still works.
5. Open `/tagore/dashboard` and verify Tagore roles/scopes.
6. Test a parent account and verify child visibility.
7. Test attendance visibility against GegoK12 attendance data.
8. Test fee statement, offline payment, receipt and reconciliation.
9. Test legacy fee import in preview/review mode only.
10. Record any failures before adding new features.

The prototype should remain frozen for major feature work until this local workflow passes.

## Next prototype iteration

After local validation, connect any remaining dashboard cards to real GegoK12 data, resolve only bugs or structural gaps found during testing, and then move the same branch to a private test server. Gateway production credentials and live financial data must remain out of the prototype test phase.
