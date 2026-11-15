# Authentication compatibility inventory

**Branch:** security/authentication-recovery-hardening  
**Base:** main at a5806356ad317c8126845a6512b80575376a7f63  
**Date:** 2026-11-15  
**Status:** repository-wide CI call-site search completed on 2026-11-15; implementation can proceed in isolated phases  
**Data boundary:** source only and synthetic CI fixtures; no live accounts, production database, SMS or token revocation

## 1. Scope and evidence standard

This inventory traces the authentication and OTP entry points found in the repository tree and inspected source files. It distinguishes confirmed call sites from unverified repository-wide coverage. GitHub's code-search endpoint returned zero results even for known symbols, so zero search results are not treated as proof that no other call sites exist. Before modifying the shared AuthenticationProcess trait, run a local repository-wide search in the CI checkout for:

- createAuthentication(
- checkAuthentication(
- Authentication:: / new Authentication
- authentications table queries
- ->tokens()->delete()
- logoutDevices(

The following list is the confirmed source inventory from direct file inspection, not a claim that every repository file has been exhaustively searched.

## 2. Confirmed shared trait call sites

| File | Use | Purpose | Compatibility constraint |
|---|---|---|---|
| app/Http/Controllers/Auth/RegisterController.php | Uses AuthenticationProcess; calls createAuthentication($user, $request, 'register') | Public school-admin registration mobile verification | Must preserve registration OTP creation and web verification behavior unless deliberately migrated |
| app/Http/Middleware/MustBeOTP.php | Uses AuthenticationProcess; calls checkAuthentication(Auth::id()) | Admin access gate while mobile_verified is false | Depends on latest registration record status semantics; do not repurpose status values for password recovery |
| app/Http/Controllers/Api/UserController.php | Uses AuthenticationProcess and ResetPasswordProcess; calls createAuthentication($user, $request, 'reset') | Parent/other mobile API password reset | Must migrate to purpose-separated challenge flow; currently coupled to Authentication model and legacy status |
| app/Http/Controllers/Admin/UserController.php | Uses ResetPasswordProcess, not AuthenticationProcess | Admin-initiated email/SMS reset link using password_resets table | Separate token workflow; preserve unless independently assessed |

## 3. Confirmed direct Authentication model consumers

| File | Operation | Purpose / risk |
|---|---|---|
| app/Http/Controllers/OTPController.php | Queries latest status=0, type=register record; marks status=1; copies token into users.mobile_verification_code and marks mobile_verified | Web registration OTP completion; validation is delegated to app/Http/Requests/OTPRequest.php |
| app/Http/Requests/OTPRequest.php | Reads latest pending registration token, IP and expiry | Web registration OTP validation; each custom validator indexes [0] without an empty-result guard |
| app/Http/Controllers/Api/UserController.php | Creates and reads type=reset records; transitions status 0→1; reuses same token for final password change | API password reset flow; final transition has no expiry check and no atomic challenge consumption |
| app/Http/Requests/API/OTPRequest.php | Reads latest pending reset token, IP and expiry | Initial API OTP verification checks expiry/IP, but custom validators index [0] without a safe missing-record branch and dereference a possibly missing user |
| app/Http/Requests/API/ResetChangePasswordRequest.php | Reads latest status=1 reset token and compares submitted oldpassword | Final API password change does not check expires_on and does not consume challenge |
| app/Http/Middleware/MustBeOTP.php | Calls checkAuthentication, which queries type=register and latest status | Registration verification status gate |

## 4. Confirmed routes and handlers

### Web registration OTP

- GET /verifyotp → OTPController@create
- POST /verifyotp → OTPController@store
- Request class: app/Http/Requests/OTPRequest.php
- Route declarations: routes/web.php lines 31–34
- Purpose: complete registration mobile verification.

### API password reset

- POST /password/reset → Api/UserController@resetPassword (ResetPasswordRequest)
- POST /password/store → Api/UserController@storePassword (API OTPRequest)
- POST /reset/check → Api/UserController@checkReset
- POST /reset/change/password → Api/UserController@resetChangePassword (ResetChangePasswordRequest)
- Route declarations: routes/api.php lines 21–27
- Current reset initiation deletes all Sanctum tokens and clears platform_token before OTP proof; this must be reversed.

### Device-wide logout / recovery

- POST /logout/devices → Api/LoginController@logoutDevices (routes/api.php line 9)
- POST /teacher/logout/devices → Api/Teacher/LoginController@logoutDevices (routes/teacherapi.php line 32)
- Both routes are declared outside the later Sanctum-protected groups.
- Parent handler selects mobile_no from request.email and usergroup_id=7; teacher handler uses usergroup_id=5. Both delete all tokens and clear platform_token/device_id without authenticated principal or verified recovery proof.
- These handlers do not currently use AuthenticationProcess; do not silently couple device recovery to legacy registration/reset status records.

## 5. Shared model and schema compatibility

- Model: app/Models/Authentication.php
- Table migration: database/migrations/2020_06_24_170946_create_authentications_table.php
- Columns: id, user_id, type, token, ip_address, expires_on, status, timestamps, softDeletes.
- The existing schema has no dedicated purpose/channel/attempt counter/consumed_at fields. It is shared by registration and API reset; therefore changing token semantics in place risks breaking registration.
- Authentication model fillable currently lists user_id, token, ip_address, expires_on, status, but not type. Existing code assigns type directly; any rewrite should use explicit safe persistence and tests rather than assume fillable behavior.
- New recovery storage should be a dedicated table unless a full call-site audit proves every legacy consumer can be migrated atomically. Do not store new challenge verifiers in the legacy token column.

## 6. Other reset mechanisms

- app/Traits/ResetPasswordProcess.php uses Laravel password reset storage with Str::random(64), Hash::make(token), and configured password reset table.
- app/Http/Controllers/Admin/UserController.php calls resetPasswordToUser($user) for an admin-triggered reset link.
- This is a separate web/email reset-link workflow, not the mobile OTP challenge. Preserve and test it independently; do not conflate it with API reset or lost-device recovery.
- app/Http/Requests/ChangePasswordRequest.php and Api/UserController@changePassword implement authenticated current-password change, distinct from unauthenticated recovery.

## 7. Existing test coverage and gaps

Inspected tests:
- tests/Feature/AuthenticationTest.php
- tests/Feature/AuthenticationAdvancedTest.php

These currently cover web login, role redirects, login throttling, logout, email verification assumptions and selected role behavior. They do not provide focused feature coverage for:
- registration OTP expiry/missing record/replay,
- API reset initiation not revoking tokens,
- API OTP expiry at both transitions,
- hashed challenge verifier / secure random generation,
- attempt and issue throttles,
- atomic single-use challenge consumption and concurrent replay,
- parent/teacher device recovery proof and role separation,
- generic responses for unknown account/wrong role,
- no debug output or dd() on OTP exception paths.

## 8. Implementation decisions for the next phase

1. Leave AuthenticationProcess and the legacy authentications table semantics intact for registration until the full repository search is run in CI.
2. Introduce dedicated recovery challenge persistence and a service for API password reset and parent/teacher device recovery, with distinct purpose values and account/role/channel binding.
3. Keep the separate Laravel password-reset-link workflow distinct.
4. Remove pre-proof token revocation from API reset initiation. Revoke tokens only after a successful, atomic final recovery transition.
5. Harden the registration OTP FormRequest/controller separately: safe missing-record handling, expiry and single-use completion, no plaintext OTP persistence where a compatible migration path is available.
6. Add synthetic feature tests before enabling either recovery completion endpoint.
7. Do not activate live SMS, perform live account revocation, merge or deploy as part of this branch.

## 10. Exact-checkout CI search results (2026-11-15)

The ERP CI backend ran the repository-wide search against the exact PR checkout. The successful job preserved the raw output as the `authentication-call-sites-<commit>` workflow artifact. The artifact for commit `88953f2bd6a2a44992a772096c1f4d167f9aa8f2` contained 35 matching source lines across the following call-site groups.

### Authentication model / OTP / recovery references (26 lines)

| File | Confirmed references |
|---|---|
| `app/Traits/AuthenticationProcess.php` | `createAuthentication`, model construction, `checkAuthentication`, latest registration record |
| `app/Http/Controllers/Auth/RegisterController.php` | Registration OTP creation |
| `app/Http/Controllers/Api/UserController.php` | Reset OTP creation, verification/status update, final reset lookup |
| `app/Http/Controllers/Api/LoginController.php` | Parent device-wide logout handler |
| `app/Http/Controllers/Api/Teacher/LoginController.php` | Teacher device-wide logout handler |
| `app/Http/Controllers/OTPController.php` | Web registration OTP verification and status update |
| `app/Http/Middleware/MustBeOTP.php` | Registration verification gate |
| `app/Http/Requests/OTPRequest.php` | Web registration OTP validation (three custom checks) |
| `app/Http/Requests/API/OTPRequest.php` | API reset OTP validation (three custom checks) |
| `app/Http/Requests/API/ResetChangePasswordRequest.php` | Final API reset OTP validation |
| `app/Models/Authentication.php` | Legacy table binding |
| `routes/api.php`, `routes/teacherapi.php` | Parent and teacher device-wide logout route declarations |

### Direct Sanctum token revocation references (9 lines)

| File | Classification |
|---|---|
| `app/Http/Controllers/Api/UserController.php` | **P0:** reset initiation revokes all tokens before OTP proof; final reset revokes tokens after password change |
| `app/Http/Controllers/Api/LoginController.php` | Authenticated logout plus unauthenticated parent device-wide recovery |
| `app/Http/Controllers/Api/Teacher/LoginController.php` | Authenticated logout plus unauthenticated teacher device-wide recovery |
| `app/Http/Controllers/Admin/UserController.php` | Admin account disable/reset lifecycle; inspect exact operation before changing |
| `app/Http/Controllers/Admin/UserProfileController.php` | Admin credential update intentionally revokes tokens |
| `app/Http/Controllers/Api/TokenController.php` | Authenticated token logout/revocation |

The fixed-string token-revocation search is intentionally separate from the regular-expression call-site search so PHP parentheses cannot cause false negatives. It also surfaced administrative credential changes and normal authenticated logout; those are not to be rewritten as OTP recovery.

### Search boundary

The CI search covered PHP files under `app`, `routes`, and `tests`, and matched the named OTP/model/table/logout symbols plus direct `->tokens()->delete` calls. This is strong evidence for these specific patterns in the checked-out tree; it is not a semantic audit of every authentication mechanism, external mobile-app submodule, vendor package, or dynamically constructed query. The downloaded artifact is retained by GitHub Actions for 90 days.

## 11. Implementation sequence and compatibility boundary

1. Implement a dedicated recovery-challenge table/model/service; never place new challenge verifiers in the shared legacy `authentications.token` field.
2. Migrate the API password-reset flow first. Keep its existing route paths and request field names for mobile compatibility while changing the server-side proof lifecycle. No token revocation at initiation; verify OTP; re-check expiry and purpose at final password update; atomically consume; revoke sessions only after successful completion.
3. Keep public web registration OTP on the legacy table during this first phase. Harden it separately with missing-record, expiry and replay tests; do not change shared `AuthenticationProcess` semantics as part of the API reset change.
4. Implement parent and teacher device-wide logout as a purpose-specific, two-step verified recovery flow. The legacy request path now initiates SMS challenge delivery; a new `/confirm` endpoint verifies and atomically consumes the challenge before revoking sessions. Update and test the Android clients against this contract before any production rollout; never retain the old one-call revocation behavior.
5. Preserve the Laravel password-reset-link flow and authenticated current-password change as separate mechanisms.
6. Test against disposable SQLite and MySQL CI databases. Do not invoke SMS, revoke live tokens, or touch live accounts during implementation.

## 9. CI checkout search gate

Before implementation, add a CI step or run an equivalent local search against the exact checkout:

```sh
rg -n --glob '*.php' 'createAuthentication\s*\(|checkAuthentication\s*\(|Authentication::|new Authentication|authentications|logoutDevices\s*\(' app routes tests
```

Record all matches in the PR and expand this inventory if additional call sites appear. A successful test suite without this search is not sufficient evidence of compatibility.


## 12. Parent and teacher device recovery implementation (PR #76)

The P0 unauthenticated device-wide logout handlers have been replaced on the implementation branch by a dedicated `DeviceRecoveryController` using the existing `RecoveryChallengeService` and `RecoveryCodeDeliveryService`.

- Parent request: `POST /api/logout/devices`; confirmation: `POST /api/logout/devices/confirm`.
- Teacher request: `POST /api/teacher/logout/devices`; confirmation: `POST /api/teacher/logout/devices/confirm`.
- The request accepts the legacy `email` field as a compatibility alias for `mobile_no`, but returns a generic response and does not revoke sessions.
- Confirmation requires a six-digit SMS code, matching account group (parent 7 / teacher 5), issuing IP, unexpired challenge, and single-use consumption. Token deletion and clearing of device/push-token metadata occur in the same database transaction after proof.
- Parent and teacher challenges use separate purposes and cannot be replayed across account groups.
- Legacy controller methods now return HTTP 410 and cannot directly revoke tokens.
- Feature tests cover parent and teacher recovery, no revocation before proof, wrong-code rejection, successful revocation, replay rejection, and cross-role rejection. Delivery is mocked; CI uses synthetic users only.

**Compatibility / rollout gate:** this intentionally changes the old one-call endpoint from immediate revocation to challenge initiation. The Android parent and teacher clients must be updated to request, collect and submit the OTP before rollout. Until app parity is demonstrated, do not deploy or activate this server change. No live SMS, live accounts, or production token revocation has been used.
