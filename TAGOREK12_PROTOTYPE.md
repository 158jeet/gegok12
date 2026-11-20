# TagoreK12 Complete ERP

TagoreK12 is the Tagore Group ERP integration layer built on top of GegoK12. The existing GegoK12 application remains the operational school-management system of record; TagoreK12 adds the group-wide management, finance, admissions, people, scope and automation layer without replacing the upstream school workflows.

## Completed ERP scope

- Multi-institution Tagore Group hierarchy
- Role and institution-scoped authorization: Owner, Principal, Coordinator, Teacher, Parent, Student and Accounts
- Explicit parent/student relationships
- Academic years, streams, sections and departments
- Admissions CRM with lead pipeline, assignments, follow-ups, campaigns and conversion tracking
- KoboToolbox submission synchronization and admission importing
- Fee structures, assignments, demand generation, student ledgers and outstanding balances
- Offline payment recording, receipts, reconciliation and online payment adapter/webhook flow
- Legacy fee workbook import, student matching, migration and reconciliation
- Staff self-service and leave approval workflows
- Employee tasks, priorities, progress, reviews, follow-ups and recurring task automation
- Group and manager dashboards with workload, department and finance snapshots
- Feedback and audit foundations
- Parent and student dashboards
- Authenticated API/mobile integration
- Performance middleware and database indexes for the new ERP paths
- Automated structural and feature-test workflow
- Existing GegoK12 attendance, results, notices, homework, assignments, timetable, lesson plans, library and other school operations remain available through the existing application rather than being duplicated.

## First-time setup

From the project root:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=TagorePrototypeSeeder --force
npm install --no-audit --no-fund
npm run build
```

Then sign in to GegoK12 and open:

```
/tagore/dashboard
```

## Production configuration

Configure the normal GegoK12 database, mail, storage and authentication settings first. For admissions lead ingestion, optionally configure:

```
KOBO_BASE_URL=https://kf.kobotoolbox.org
KOBO_API_TOKEN=
KOBO_ASSET_UID=
KOBO_INSTITUTION_ID=
KOBO_TIMEOUT=30
```

For online payments, configure the existing gateway settings and keep production credentials outside source control.

## Operating principle

The ERP is additive. Existing GegoK12 student, parent, attendance and school workflows are reused as the source of operational truth. Tagore tables hold group-level scope, finance, admissions, people/work-management and audit data.

Financial success events should not be rewritten. Corrections use reconciliation, adjustment or refund records.

## Verification

The repository contains a dedicated Tagore CI workflow covering:

1. MySQL migration smoke test
2. Base and Tagore seed preparation
3. Composer validation and optimized autoload checks
4. Tagore route/view structural checks
5. PHP syntax checks
6. Tagore feature tests
7. Seeded end-to-end tests
8. Frontend dependency installation and production build

The repository's npm lifecycle no longer executes the former custom preinstall script. The Composer setup's `npm run build` command now resolves to the existing Laravel Mix production build.

## Architecture decision

Do not rebuild the ERP from scratch and do not duplicate GegoK12 modules that already provide the required school operations. Continue extending the Tagore layer through adapters and institution-scoped services when a genuinely new group-level capability is required.
