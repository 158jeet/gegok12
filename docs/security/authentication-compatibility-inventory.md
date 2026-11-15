# Authentication compatibility inventory

**Branch:** security/authentication-recovery-hardening  
**Base:** main at a5806356ad317c8126845a6512b80575376a7f63  
**Date:** 2026-11-15  
**Status:** source inspection recorded; code changes intentionally not started until compatibility decisions are reviewed  
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

## 9. CI checkout search gate

Before implementation, add a CI step or run an equivalent local search against the exact checkout:

```sh
rg -n --glob '*.php' 'createAuthentication\s*\(|checkAuthentication\s*\(|Authentication::|new Authentication|authentications|logoutDevices\s*\(' app routes tests
```

Record all matches in the PR and expand this inventory if additional call sites appear. A successful test suite without this search is not sufficient evidence of compatibility.
