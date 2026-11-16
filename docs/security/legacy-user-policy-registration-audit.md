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

The `post_reply` Gate callback in `AuthServiceProvider` originally accepted a parameter named `$post` but dereferenced `$post_reply`. This was corrected on this branch to accept `$post_reply`, and `tests/Feature/AuthorizationGateRegressionTest.php` now verifies same-school allow and cross-school deny. Both this regression and the full exact-head workflow suite passed on commit `3a41d6649e9c4f48f2ec942e556e6b123f785021`.

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


## Follow-up: Owner-managed account token revocation

A code review of `AdministrationController::resetUserPassword` and `updateUserStatus` found that both paths previously cleared `remember_token` but did not delete the target user's Sanctum personal access tokens. Because the core `User` model uses Sanctum's `HasApiTokens` trait, this left existing API tokens usable after an Owner reset or disable action.

The follow-up now deletes the target user's Sanctum tokens inside the same database transaction before completing an Owner password reset, and when disabling an account. Re-enabling an account does not need to revoke tokens again. Synthetic feature tests assert token deletion and the corresponding audit record. This change does not claim browser-session invalidation: session-driver behavior and the Owner's own password-change/session lifecycle remain a separate audit item.

The exact-head CI pass recorded above applies to the prior commit only. The new token-revocation commit must complete all exact-head workflows before this follow-up is considered validated.


## Route/controller consumer mapping — follow-up 2026-11-16

The following route evidence was inspected directly from this branch. This is a targeted, verified inventory, not yet a complete repository-wide call-site census.

| Route surface | Route/action | Observed authorization behavior | Relationship to User policy |
|---|---|---|---|
| Legacy admin | `POST /user/updateStatus/{name}` → `Admin\\UserController@updateStatus` | Loads target by name and updates both `users.status` and `userprofiles.status`; no explicit `Gate::allows`, `authorize()` or policy call appears in the method body inspected. Route-group middleware still needs full confirmation. | No direct User-policy call found in this action. |
| Legacy admin | `GET /user/resetPassword/{id}` → `Admin\\UserController@resetPassword` | Calls `Gate::allows('member', $user)`; the `member` Gate callback compares actor and target `school_id`. | Uses a named Gate callback, not `UserPolicy`. |
| Legacy admin | `GET /user/{id}/verificationcode` → `Admin\\UserController@emailVerification` | Calls the same `member` Gate callback before dispatching verification mail. | Uses a named Gate callback, not `UserPolicy`. |
| Legacy admin | `GET /credentials/get/{name}`, `POST /credentials/add/{name}` → `UserProfileController` | Route entries identified; controller authorization still requires inspection. | Userprofile controller/model, not established as User-policy usage. |
| Tagore administration | `POST /tagore/admin/users`, `POST /tagore/admin/users/{userId}/password`, `PATCH /tagore/admin/users/{userId}/status` → `Tagore\\AdministrationController` | Group is behind `auth`; controller methods call the explicit Owner guard. | Explicit Owner guard, not UserPolicy. |
| API recovery | `POST /password/reset`, `POST /password/store`, `POST /reset/check`, `POST /reset/change/password` → `Api\\UserController` | Public recovery endpoints with throttles; challenge service governs proof and completion. | Recovery-specific controls, not UserPolicy. |
| Authenticated API account actions | `GET /updatetoken`, `POST /password/change` → `Api\\UserController` | Defined within authenticated API route group; controller uses current authenticated user. | No User-policy call observed in the inspected methods. |

### Interpretation and remaining checks

- The legacy `member` Gate is only a same-`school_id` check. It is not a substitute for Tagore's role-specific institution, class, parent-family or finance scope rules.
- The legacy `updateStatus` action is especially important: the inspected method contains no local authorization check, so its actual protection depends on the enclosing route middleware and any global/controller middleware. That middleware must be confirmed before deciding whether this is an exploitable gap.
- The Tagore administration routes are protected by `auth` at the route group and the inspected controller methods separately apply an Owner guard. This is distinct from the legacy `Admin\\UserController` surface.
- The API recovery routes are intentionally unauthenticated and must remain governed by the challenge flow, not a generic User policy.

### Bounded search limitations

The route files inspected included `admin.php`, `api.php`, `tagore.php`, `tagore_api.php`, `web.php`, `superadmin.php`, `teacher.php`, `student.php`, `accountant.php` and `receptionist.php`. The repository contains 19 route files; the remaining route files and all controller, view, resource, package and service call sites still require a reproducible full-tree scan. Connected GitHub code search returned zero matches for several authorization-call patterns, so those results are not used as proof of absence.

**Decision:** Do not change the duplicate User policy registration or permissive policy bodies yet. First complete the route middleware and full-tree call-site inventory, then add role/record-scope tests.

### Route middleware confirmation

The enclosing legacy admin route group is registered in `app/Providers/RouteServiceProvider.php` as:

`/admin` + `web`, `auth`, `schooladmin`, `privilegeconditions`.

Therefore, the inspected legacy `Admin\\UserController@updateStatus` method is not an unauthenticated public route: it inherits authentication, the legacy SchoolAdmin middleware and the academic privilege-condition middleware. It still has no per-target same-school Gate check in its own method, unlike the reset-password and email-verification actions. The middleware boundary is coarse and must not be confused with record-level authorization.

The Tagore routes use a different group: `auth` plus `TagorePerformance`, with explicit Owner checks inside the Owner-only AdministrationController methods. The API route group uses `api`; the password recovery endpoints are additionally throttled and use the recovery challenge service.

**Confirmed gap to test:** legacy SchoolAdmin may be able to update a user status outside the actor's school if the controller lookup by globally unique `name` is not constrained and no downstream middleware adds record scoping. Before changing this endpoint, add synthetic cross-school deny tests and inspect `MustBeSchoolAdmin`/privilege middleware behavior. Do not assume the role middleware itself enforces target-record ownership.


### Middleware source inspection — confirmed record-scope limitation

The `MustBeSchoolAdmin` middleware checks only `Auth::user()->isAdmin()` before allowing the legacy admin route group through. It does not inspect the target user or compare the target's `school_id` with the actor's school. The subsequent `MustBePrivilege` middleware checks whether the actor's own school has an academic year and at least one standard; it also does not scope the requested target record.

Consequently, for `Admin\\UserController@updateStatus`, the route middleware establishes an authenticated legacy SchoolAdmin-type portal boundary but does not establish target-user same-school ownership. The action looks up the target globally by `name` and updates the account and profile status without invoking the existing `member` Gate. This is a concrete cross-school authorization risk, not merely an unverified possibility.

The existing `member` Gate callback already compares actor and target `school_id` and is used by the legacy reset-password and email-verification actions. The next implementation should apply equivalent record-level authorization to `updateStatus`, with synthetic same-school allow and cross-school deny endpoint tests. Before changing the method, verify the route's exact middleware and test fixture setup in CI; do not broaden access or alter the role middleware.

## Follow-up remediation: legacy user status endpoint

The legacy `POST /admin/user/updateStatus/{name}` endpoint has now been hardened in the authentication-recovery branch. The controller resolves the target with `firstOrFail()`, then checks the existing `member` Gate before revoking tokens or changing either the user or profile status. A target outside the authenticated SchoolAdmin's school receives HTTP 403; a missing target/profile fails closed with HTTP 404. The route remains behind the existing authenticated SchoolAdmin and privilege middleware.

Regression coverage now includes:
- `member` Gate allows a same-school target and denies a different-school target.
- The legacy status-update controller rejects a cross-school target before mutation and asserts the target remains active.

Scope limitation: this closes the specifically confirmed cross-school status-update path. It does not establish complete institution/class/family scoping for other legacy user endpoints, nor does it resolve the duplicate User policy registration. The wider route/controller call-site audit remains open.
