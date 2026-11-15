# Parent and teacher Android device-recovery compatibility gate

**Status:** blocked pending Android client source access and implementation  
**Server implementation:** PR #76, branch `security/authentication-recovery-hardening`  
**Server commit reviewed:** `29594e74a1d55a1ed9d1a4d3e252cc22a5db8c00`  
**Date:** 2026-11-15

## Purpose

The server no longer accepts an unauthenticated one-call request to revoke every device session. It now sends a recovery code and requires a second, verified request. The parent and teacher Android apps must implement this contract before the server change can be released.

## Submodule references

The ERP repository's `.gitmodules` declares:

| Client | ERP submodule path | Repository |
|---|---|---|
| Parent | `mobile/parent` | `https://github.com/saurindashadia/gegok12-parent-app.git` |
| Teacher | `mobile/teacher` | `https://github.com/saurindashadia/gegok12-teacher-app.git` |

The linked GitHub connection could retrieve public repository metadata and README files, but has read-only access. Code search returned no usable source matches, and the repository root layout/source call sites could not be verified through the available file interface. Therefore no claim is made that either app has been updated or that the existing screen/API contract is compatible.

## API contract

| Operation | Parent | Teacher |
|---|---|---|
| Request code | `POST /api/logout/devices` | `POST /api/teacher/logout/devices` |
| Confirm code | `POST /api/logout/devices/confirm` | `POST /api/teacher/logout/devices/confirm` |

Request body:
- `mobile_no`: account mobile number (10 digits)

Confirmation body:
- `mobile_no`: account mobile number
- `code`: six-digit SMS code

Temporary server compatibility aliases:
- Request/confirmation accepts legacy `email` as an alias for `mobile_no`.
- Confirmation accepts legacy `otp` as an alias for `code`.

The request endpoint intentionally returns a generic success message for eligible and ineligible accounts. The app must not infer account existence from that response. The confirmation endpoint returns success only after a matching, unexpired, purpose/role-bound, single-use challenge is consumed and all server-side sessions/device metadata are cleared. Failed confirmation returns HTTP 422. Legacy controller handlers return HTTP 410.

## Required client implementation

For each app independently:

1. Locate the existing “logout all devices” / lost-device recovery UI and all API client definitions/call sites.
2. Replace the old one-request expectation with request-code → OTP-entry → confirmation.
3. Display a neutral message after initiation; do not claim that devices have been logged out yet.
4. Provide resend handling consistent with server throttles and show invalid/expired-code errors without leaking account existence.
5. On confirmation success only, clear local access tokens, cached auth state, push/device registration state as appropriate, then return to login.
6. If confirmation fails or the network is unavailable, retain the current local session and clearly report that server-side logout was not confirmed.
7. Ensure legacy HTTP 410 is handled safely in older-client scenarios; do not silently report success.
8. Add automated tests with mocked HTTP and mocked SMS/backend fixtures.

## Acceptance checklist

- [ ] Parent app source and current API call sites inspected.
- [ ] Teacher app source and current API call sites inspected.
- [ ] Parent request/OTP/confirm flow implemented.
- [ ] Teacher request/OTP/confirm flow implemented.
- [ ] Correct API base URL and JSON response handling verified in both apps.
- [ ] Wrong, expired, replayed and throttled OTP cases tested.
- [ ] Local credentials are cleared only after successful server confirmation.
- [ ] Android build and relevant unit/instrumentation tests pass for both apps.
- [ ] Updated app commits pinned in ERP submodules.
- [ ] End-to-end compatibility tested against a disposable backend with SMS mocked.
- [ ] Exact app commit IDs and CI run links recorded here and in PR #76.

## Release boundary

PR #76 must remain draft/unmerged and must not be deployed until every checklist item is verified. No live SMS, production account, production database, production endpoint, or live token revocation is permitted during this compatibility work.
