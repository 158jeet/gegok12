# Authentication and Device-Recovery Hardening — Implementation Plan

**Status:** implementation-ready specification; code changes not yet made  
**Target:** isolated feature branch and draft PR from current main  
**Scope:** mobile password reset, parent/teacher device-wide logout recovery, OTP validation and session revocation  
**Data boundary:** synthetic CI fixtures only; no live accounts, production DB, SMS delivery or token revocation

## 1. Verified current behavior

Source files inspected on main:

- app/Traits/AuthenticationProcess.php
- app/Http/Controllers/Api/UserController.php
- app/Http/Requests/API/OTPRequest.php
- app/Http/Requests/API/ResetPasswordRequest.php
- app/Http/Requests/API/ResetChangePasswordRequest.php
- app/Http/Controllers/Api/LoginController.php
- app/Http/Controllers/Api/Teacher/LoginController.php
- routes/api.php
- routes/teacherapi.php
- database/migrations/2020_06_24_170946_create_authentications_table.php

Confirmed details:

1. AuthenticationProcess generates OTPs with rand() and stores the OTP plaintext in authentications.token.
2. ResetPasswordRequest identifies the account by mobile_no + usergroup and checks account/profile status. UserController::resetPassword deletes all Sanctum tokens and clears platform_token before sending the OTP. This revokes sessions before proof.
3. OTPRequest does check the pending OTP's expiry and source IP during the initial storePassword transition. This check is implemented in its custom FormRequest validators, not in the controller. Those validators have unsafe empty-result indexing and missing-user dereferences.
4. storePassword compares the plaintext OTP, marks the row verified (status=1), and leaves the challenge reusable. Its mismatch response returns without explicitly rolling back the opened DB transaction.
5. ResetChangePasswordRequest compares the OTP again against the latest verified row but does not check expiry. The final password-change transition does not consume the challenge atomically.
6. Both OTP completion controllers and AuthenticationProcess contain exception paths that call dd().
7. Parent and teacher device-wide logout endpoints are outside their authenticated Sanctum groups. Their handlers select accounts by submitted mobile number and fixed usergroup (parent 7, teacher 5), then revoke all tokens and clear device metadata without proof of account control.
8. The authentications table has a string token column, a text type column, expires_on, status and soft deletes. Existing AuthenticationProcess may serve purposes other than password reset; call sites must be inventoried before changing shared semantics.

## 2. Design rules

- Do not simply add auth:sanctum to lost-device recovery endpoints; users may have lost the device carrying their existing token.
- Separate purposes explicitly: password reset, parent device recovery and teacher device recovery must not share a challenge state.
- Use a cryptographically secure random generator (e.g. random_int for numeric OTPs or random_bytes for opaque challenges).
- Persist only a password-hash/keyed verifier, never the raw OTP/challenge. Compare with the framework hash verifier.
- Bind each challenge to user ID, user group, purpose, recovery channel and expiry.
- Enforce issue throttling and verification attempt limits. Return generic public responses for unknown accounts, wrong role and failed proof.
- Enforce expiry at every state transition, including final password change and device revocation.
- Consume a successful challenge atomically and exactly once. Concurrent/replayed requests must fail.
- Do not revoke sessions or clear device metadata until the relevant recovery proof succeeds.
- Keep current-device logout authenticated and distinct from device-wide recovery.
- Preserve legacy registration/login OTP behavior only after all call sites are inventoried and each purpose has explicit tests.
- No live SMS gateway calls in CI. Use a fake/mock delivery adapter.

## 3. Implementation sequence

### Phase A — dependency and compatibility inventory

1. Search every call site of createAuthentication(), checkAuthentication(), Authentication model writes, and authentication token comparisons.
2. Record each type/purpose currently used (registration, login, reset or other) and identify mobile-app payload compatibility.
3. Confirm supported Laravel/PHP versions, cache/atomic-lock support, DB engines used by CI and production, and existing rate-limiter conventions.
4. Decide whether to add dedicated recovery_challenges storage or extend authentications with explicit purpose, verifier, attempt count and consumed_at. Prefer a dedicated table if legacy OTP consumers cannot be safely migrated together.
5. Write migration rollback and legacy-record behavior. Existing plaintext records must not accidentally validate as new hashed challenges.

### Phase B — challenge service and storage

1. Add a dedicated challenge service with issue, verify and consume operations.
2. Add purpose/account/role/channel binding, expiry, attempt count, consumed timestamp and hashed verifier.
3. Add atomic row locking/conditional updates for single-use consumption, compatible with the project's supported database engines.
4. Add configurable per-account and per-source issue/verification throttles.
5. Add a fakeable SMS delivery boundary; no credentials or external calls in tests.

### Phase C — password-reset flow

1. Make reset initiation generic and avoid token/session revocation.
2. Verify OTP using the challenge service and reject expired, wrong-purpose, wrong-role, wrong-account, exhausted and replayed challenges.
3. Complete password change in one transaction: consume challenge, update password, update reset state, revoke existing Sanctum tokens and clear device metadata only after proof.
4. Remove dd() paths and replace with safe logged errors and generic API responses.
5. Remove or neutralize checkReset account-state disclosure; preserve client compatibility with a generic response contract.
6. Remove unsafe array indexing and make missing challenge/account paths deterministic.

### Phase D — parent and teacher lost-device recovery

1. Replace mobile-number-only logoutDevices handlers with challenge issue and verified recovery completion.
2. Bind parent recovery to usergroup 7 and teacher recovery to usergroup 5; do not permit cross-role challenge use.
3. Revoke all target tokens and clear platform_token/device_id only after atomic challenge consumption.
4. Return the same public response shape for unknown account and challenge failure.
5. Keep the authenticated current-device logout endpoints unchanged except for separately tested correctness fixes.

### Phase E — tests, compatibility and acceptance

Required synthetic feature tests:

- OTP uses a cryptographically secure generator and persisted verifier is not the raw OTP.
- No account enumeration across known/unknown mobile numbers or wrong user groups.
- Reset initiation does not revoke Sanctum tokens or clear device metadata.
- Initial verification rejects wrong, expired, wrong-IP (if IP binding remains justified), wrong-purpose and wrong-role challenges.
- Final password change independently rejects expired challenges.
- Attempt limits and issue throttles work per account and source.
- Successful completion consumes the challenge once; replay and concurrent replay fail.
- Parent challenge cannot recover teacher account and vice versa.
- Device-wide logout leaves tokens intact before proof and revokes all tokens only after verified proof.
- Password reset and device recovery cannot cross-use challenges.
- Missing account/challenge returns safe generic responses; no dd() or sensitive debug output.
- Legacy registration/login OTP flows remain compatible or are migrated explicitly.
- Migration works on the supported MySQL CI smoke and the project's disposable test DB.

Acceptance gate:

- Targeted authentication/recovery tests pass.
- Full ERP CI, browser E2E, prototype acceptance and MySQL migration smoke pass on the exact feature-branch head.
- Review mobile app callers and payload compatibility before marking ready for review.
- No merge, deployment, production DB access, live SMS or live account/session changes are included.

## 4. Release sequencing and dependencies

This work is a prerequisite for enabling any new lost-device recovery flow. Keep the device-wide logout endpoints unavailable to unverified account-control requests until the new proof flow is implemented and tested. Do not break password reset by changing the shared AuthenticationProcess until its other call sites are mapped. The route/middleware audit and this plan are source-level only; no live exploit testing has been performed.

## 5. Exit criteria

The implementation is complete only when all phases above are implemented, all required synthetic tests pass on the exact PR head, mobile client compatibility is documented, and a separate explicit owner decision authorizes merge/deployment. A passing documentation PR is not evidence that this code work is complete.
