# TagoreK12 Prototype

TagoreK12 is the Tagore Group integration layer built on top of GegoK12. The prototype is intentionally additive: existing GegoK12 school, student, parent, attendance and authentication concepts remain the system of record while Tagore-specific group, scope, fee, payment, feedback and audit boundaries live in `tagore_*` tables.

## Prototype setup

```bash
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\TagorePrototypeSeeder
```

Then sign in to GegoK12 and open `/tagore/dashboard`.

The prototype API is available at `/tagore/v1/dashboard` for an authenticated Sanctum session/token.

## Design boundaries

- Do not install Fee Pro or Exam Pro to use this foundation.
- Fee/payment tables are Tagore-owned and expose adapter points for gateways and future GegoK12 add-ons.
- Exam integration should use an adapter rather than depending on private Pro tables.
- Parent-child authorization is based on explicit `tagore_parent_students` relationships.
- Institution scope is represented independently from role permissions.
- Financial events should be treated as append-only business events; corrections use adjustments/refunds rather than rewriting successful payments.

## Next prototype iteration

Connect the dashboard cards to real GegoK12 attendance/results data, add institution administration, implement fee obligation screens and add gateway adapters after the Tagore fee structure has been validated against the exported MySchoolERP data.
