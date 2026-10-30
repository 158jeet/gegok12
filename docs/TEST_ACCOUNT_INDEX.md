# Tagore ERP Acceptance Test Account Index

This index is for **automated disposable test environments only**. These are synthetic fixture identities, not real staff or family accounts.

## Seeded identities

The `Database\\Seeders\\TagoreDemoAccountsSeeder` creates these identities when the test database is seeded. The seeder returns immediately in `production` and must never be run to create production accounts.

| Role | Login email | Purpose |
|---|---|---|
| Owner | `owner@tagore-demo.local` | Owner dashboard and administration acceptance |
| Principal | `principal@tagore-demo.local` | Principal role and route/permission checks |
| Coordinator | `coordinator@tagore-demo.local` | Coordinator role and route/permission checks |
| Teacher | `teacher@tagore-demo.local` | Teacher role, diary and task acceptance |
| Parent | `parent@tagore-demo.local` | Parent role and parent/student diary workflows |
| Student | `student@tagore-demo.local` | Student role and access boundary checks |
| Accounts | `accounts@tagore-demo.local` | Accounts role and access boundary checks |
| Fee editor | `fees@tagore-demo.local` | Fee-editor role and access boundary checks |

**Disposable test password:** `Tagore@2026!`

The password is a fixture default, not a production credential. Do not reuse it for production or staging. Existing fixture users are preserved by the seeder rather than having their password overwritten, so if a local test database already contains one of these users with an older password, reset only that disposable database or explicitly reset that fixture there.

## Synthetic relationships and setup

- The parent and student fixture are linked by a synthetic guardian relationship.
- Administrator acceptance uses a synthetic unlinked school: `acceptance-unlinked-school@tagore-demo.local` / slug `tagore-acceptance-unlinked-school`.
- The browser harness signs in through `/__e2e/session/{role}`, registered only when `APP_ENV=testing` and `TAGORE_E2E_ENABLED=true`.
- The harness route is not a login mechanism for production and must never be enabled in production.

## Reuse

These identities are automatically seeded for the disposable SQLite browser-test database in CI. For a future local test run, seed the application in a non-production environment and use the emails above. The current live-site acceptance workflow intentionally uses separate dedicated staging accounts and will not silently use these local fixture identities.

## Production boundary

No production accounts are created by this index or by the test seeder. Production accounts must be created later through the agreed production account-creation process, with unique credentials, least-privilege roles, and an account owner responsible for credential storage and rotation.
