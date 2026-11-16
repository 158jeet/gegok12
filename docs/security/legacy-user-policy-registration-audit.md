# Legacy User Policy Registration Audit

**Status:** Evidence captured; authorization changes intentionally deferred pending complete consumer mapping.
**Branch:** `security/authentication-recovery-hardening`
**Scope:** Laravel Gate policy registration for `App\\Models\\User` and adjacent legacy authorization callbacks.

## Confirmed registration defect

`app/Providers/AuthServiceProvider.php` declares three entries for the same associative-array key, `App\\Models\\User`:

1. `AdminPolicy`
2. `ParentUserPolicy`
3. `UserPolicy`

PHP arrays cannot retain duplicate keys. The later declarations overwrite earlier declarations before Laravel registers the policy map. In the current file, the effective mapping is therefore `UserPolicy`; the AdminPolicy and ParentUserPolicy entries are not independently registered for the User model.

## Confirmed permissive policy methods

The three policy classes currently contain unconditional `true` returns for the following methods:

| Policy | Methods |
|---|---|
| `AdminPolicy` | `viewAny`, `view`, `create`, `update` |
| `ParentUserPolicy` | `viewAny`, `view`, `create`, `update` |
| `UserPolicy` | `viewAny`, `view`, `create`, `update` |

Delete/restore/forceDelete methods in these files currently return no explicit authorization result. These classes do not encode Tagore institution, role, class, family or record ownership boundaries.

## Adjacent Gate callback defect

The `post_reply` Gate callback in `AuthServiceProvider` accepts a parameter named `$post` but dereferences `$post_reply`. The latter is undefined in that closure. This needs a focused regression test and correction.

## What is not yet established

- Which legacy controllers, routes, resources or packages call `authorize()`, `Gate::authorize()`, `can()`, `@can`, or policy helpers for the User model.
- Whether the three policy classes were intended for different model types, or are obsolete copies.
- Whether any callers rely on the currently effective permissive `UserPolicy` behavior.
- Whether authorization is also enforced by route middleware, controller guards, query scoping or service-level checks for those same endpoints.

Repository-wide text search through the connected code-search index did not return reliable call-site results. A zero-result search is not evidence that there are no consumers.

## Safe implementation sequence

1. Build a reproducible inventory of User-policy and Gate call sites from the checked-out branch, including Blade directives and package/resource authorization.
2. Map each call site to its route, controller action, model instance and current route middleware.
3. Identify intended policy ownership. If Admin and Parent policies target different concepts, map them to the correct distinct model classes rather than repeatedly mapping the User model.
4. Define explicit role and record-scope rules consistent with the Tagore role matrix in PR #72.
5. Add allow/deny tests for Owner, Principal, Accounts, Cashier, Coordinator, Teacher, Parent and Student where relevant, including cross-institution and unrelated-record cases.
6. Change policy registration and policy bodies only after the call-site matrix and tests are in place.
7. Run the full synthetic CI and route acceptance suites on the exact resulting commit.

## Current Owner boundary

The separate Tagore administration controller now protects its own Owner-only operations and prevents creating or assigning a second `OWNER` role through those endpoints. This does **not** prove that every legacy user-management, import, API, package or direct database path is protected. No broad legacy policy behavior has been changed in this audit step.

## Safety boundary

Audit and tests use synthetic CI fixtures only. No production accounts, sessions, database records, email/SMS channels, deployments or live endpoints are changed by this document.
