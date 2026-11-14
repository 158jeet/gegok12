# Runtime route manifest — first-pass audit

**Evidence date:** 2026-11-14  
**Source:** GitHub Actions run `ERP Full Acceptance Convergence #598` (run ID `43591954144`), job `Full ERP acceptance (fail closed)` (job ID `132440628904`).  
**Candidate context:** PR #72 research branch; CI disposable SQLite database; `APP_ENV=testing`; E2E feature flag enabled.  
**Artifact:** `erp-full-acceptance-854e6c...` (includes `route-list.json`, `route-list.txt`, browser reports and server log). The exact artifact remains attached to the workflow run; it is not a production snapshot.

## Verified output

- Laravel `php artisan route:list --json` returned **1,444 registered route entries**.
- The workflow parsed the JSON with `JSON_THROW_ON_ERROR` and verified a non-empty array.
- HTTP method counts from the manifest: GET 952, POST 459, DELETE 18, PATCH 16 (GET routes also advertise HEAD; method totals are not a route-entry count).
- Route prefixes: `admin` 471; `teacher` 225; `api` 200; `tagore` 173; `accountant` 99; `receptionist` 96; `student` 75; `library` 52; remaining routes are authentication, public forms, package assets, testing and platform endpoints.
- **221 named / 1,223 unnamed** routes.
- **27 Closure** actions.
- No duplicate exact `method + URI` pairs were found in this manifest.
- API prefix: 200 routes; 161 explicitly include `auth:sanctum`. Other API routes include login, password reset and public lookup/info endpoints; each needs action-level review rather than assuming all are intentionally public.
- The manifest contains 69 routes whose expanded middleware list does not include an `Authenticate` class. This is a triage count, not a count of public vulnerabilities: it includes expected public/authentication routes, test-only routes, package routes and routes protected by other middleware.

## High-priority audit candidates

### 1. State-changing actions registered as GET

The manifest contains **50 GET/HEAD routes whose action is named `destroy` or `delete`**, plus **5 GET/HEAD approval-related actions** and **3 GET/HEAD reset/token-update candidates**. Examples include:

- Legacy web deletes: `admin/academic/delete/{id}`, `admin/document/delete/{id}`, `admin/notice/delete/{id}`, `teacher/homework/delete/{id}`, `student/assignment/delete/{id}`, `library/books/delete/{id}`, `receptionist/visitorlog/delete/{id}`.
- API deletes: `api/v2/leave/delete/{id}` and `api/v2/task/delete/{id}` are GET routes even though they are inside the Sanctum-authenticated v2 group.
- Other state candidates: `admin/event/approve/{id}`, `teacher/leave/approve/{id}`, `teacher/studentLeave/approve/{id}`, `admin/user/resetPassword/{id}`, and `api/v2/updatetoken`.

**Required next action:** inspect each controller method and all calling Blade/JS/mobile clients; move mutations to POST/PATCH/DELETE with CSRF/authentication, preserve compatibility only through safe redirects or explicit deprecation, and add tests proving GET/HEAD never mutates. Do not bulk-rewrite routes without checking callers and mobile API contracts.

### 2. Public and package routes require environment verification

The testing manifest includes `__e2e/*`, `_debugbar/*`, `_ignition/*`, and `tinker` routes. The E2E endpoints are explicitly conditionally registered only when `app()->environment('testing')` and `TAGORE_E2E_ENABLED` are true; they are expected in this CI manifest and should not be counted as production exposure.

The checked-in `config/debugbar.php` sets `enabled` from `DEBUGBAR_ENABLED` (default null) and enables collectors including database queries, session, mail, cache and models. Route registration alone does not prove these routes are enabled or reachable in production. Verify production dependency installation, `APP_DEBUG`, `DEBUGBAR_ENABLED`, Ignition and WebTinker package providers/routes, and test the deployed-equivalent configuration with a disposable environment. Explicitly fail the release gate if debug/tinker/ignition endpoints are reachable in production mode.

### 3. Route naming and auditability

1,223 routes are unnamed. This makes stable route references, route-level authorization mapping and automated UI-to-route audits harder. Prioritise naming modern Tagore routes and high-risk legacy actions; do not rename public API routes without client compatibility review.

## Important scope limitations

- This manifest was generated from the CI testing environment, seeded synthetic data and E2E flag. It is **not** a production route manifest and does not prove production exposure.
- A route's middleware list is not proof of record-level tenant, class, academic-year, guardian or employee ownership enforcement.
- The manifest's `path` field is null for most entries; source-file attribution still requires route-source inspection.
- No controller bodies, model scopes, policies, export/download paths or runtime negative authorization cases were fully audited by generating this manifest.
- The CI run passed: backend **145 tests / 1,327 assertions**, full authenticated browser suite **92 passed**, focused Operations checks **5 passed** and public login smoke **2 passed**. These are the run's reported results, not proof of full feature parity or tenant isolation.

## Next route audit work

1. Inventory all 50 GET delete/destroy routes and 5 approval candidates; locate every caller; test GET/HEAD non-mutation; produce a safe migration batch.
2. Inspect the 23 API routes without explicit Sanctum authentication individually; classify public login/lookup/info vs routes that need auth or ownership checks.
3. Inspect impersonation routes and impersonation stop flow; verify audit events, role boundaries, target institution and session revocation.
4. Audit modern Tagore and legacy parent/student endpoints for cross-child, cross-institution and IDOR controls.
5. Map all 1,444 route entries to controller action, request validation, model ownership, view/API client, policy/Gate and negative tests; prioritise finance, identity, documents, exports and impersonation.
6. Generate a production-equivalent route manifest in disposable CI with debug/tinker packages disabled as expected for production; compare against the testing manifest.

## Public API and password-recovery action review

The next source pass inspected the unauthenticated API route handlers, their request validators and OTP traits. This is source inspection, not live exploit testing.

### Public endpoints classified as expected candidates

- `api/parent/login` and `api/teacher/login`: public authentication entry points; require stricter per-account/IP/device rate limits and generic failure responses.
- `api/schools/list`, `api/apk/parent`, `api/apk/teacher`, country/state/city, blood-group, degree, certificate, marital-status and designation lookups: plausible public bootstrap/reference data. Confirm responses contain no private fields and add cache/rate controls as appropriate.
- `api/password/reset`, `api/password/store`, `api/reset/check`, `api/reset/change/password`: public recovery endpoints and therefore high-risk identity boundaries.
- `api/school/info`: public school bootstrap, but the current implementation selects `School::first()` rather than a school selected by slug/tenant. In a multi-institution platform this can return the wrong institution's identity and settings.

### Confirmed source-level defects / risks in password recovery

1. `AuthenticationProcess::createAuthentication()` generates the six-digit OTP with `rand()` and stores the token as a plain value in the Authentication table. Use a cryptographically secure generator and store a verifier/hash; never expose OTP material in logs or responses.
2. `OTPRequest` accesses `$otp[0]` in its custom validators without checking that a pending OTP exists. A user with no reset record can trigger an exception path instead of a controlled validation failure.
3. `OTPRequest` compares the OTP to `$_SERVER['REMOTE_ADDR']`-bound data. IP binding is brittle for mobile users and must use trusted proxy-aware request context if retained; it is not a substitute for rate limiting/device verification.
4. OTP expiry is checked while `storePassword` verifies the code. That endpoint then changes the Authentication row from status 0 to status 1. The subsequent `resetChangePassword` validator/controller reads status-1 reset rows but does **not** recheck `expires_on` and does not consume/invalidate the successful token after changing the password. A previously verified OTP can therefore remain reusable beyond its intended five-minute expiry and may be replayed for further password changes. Treat as a P0 identity defect; prove with a disposable synthetic-account regression test before fixing.
5. `ResetPasswordRequest` has custom validators and messages that distinguish unregistered/inactive accounts, enabling account-state enumeration. Its status validator dereferences `userprofile`; null/missing related profile handling is not defensive.
6. `api/reset/check` returns `is_reset` for a caller-supplied mobile number and user-group ID without authentication. It is a direct account-state oracle unless the product intentionally exposes and mitigates it.
7. `api/parent/login` uses the general API middleware throttle (`60,1`) in the inspected route setup; no stricter per-account/device failed-login throttle was found in the inspected route/request code.
8. `UserController::resetPassword()` revokes the user's existing Sanctum tokens before the OTP is completed. This logs out every device when a reset is merely initiated, before the user proves possession of the recovery channel.

### Other public API route correctness and tenant findings

- `api/events/show/details/{id}` points to `Api\\EventsController@showdetails`, but the inspected controller class has no `showdetails` method. Treat this as a broken route candidate; add a route smoke test and either implement the intended safe action or remove/deprecate the route after checking mobile callers.
- `api/school/info` returns `School::first()`, not a school selected by request context. This is incompatible with a group of institutions unless the endpoint is deliberately bound to a single configured public tenant.
- The 23 API routes without explicit Sanctum middleware are not all vulnerabilities: login, reference lookups and public school listing are expected to be public. Password reset and account-state endpoints need a separate threat model and stricter controls. Authenticated API data routes still require record-level family/student/tenant authorization.

**Required implementation acceptance:** OTP entropy and hashed storage; expiry at every transition; atomic single-use consumption; attempt limits by account and source; generic responses; safe missing-record behavior; per-route rate limits; session/token revocation at the correct point in the verified reset flow; synthetic tests for no OTP, expired OTP, wrong OTP, replay, concurrent use, inactive user, unknown mobile and two-institution bootstrap.
