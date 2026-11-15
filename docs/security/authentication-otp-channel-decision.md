# Authentication OTP delivery-channel decision

**Decision recorded:** 2026-11-15  
**Owner direction:** Email OTP is acceptable; SMS OTP is not required.

## Product decision

1. New API password-recovery challenges must be delivered to the user's registered email address. Do not send recovery OTPs through MSG91/SMS.
2. Device-wide recovery is deferred for now. Do not ship the new parent/teacher OTP-based device-recovery endpoints or require Android client changes for them in this release.
3. Preserve the security boundary: unauthenticated legacy device-wide logout must not directly revoke all sessions. The legacy handlers should remain disabled (HTTP 410) unless a different, explicitly approved recovery design is implemented.
4. Keep authenticated current-password change and the existing Laravel password-reset-link workflow separate.
5. Do not infer that SMS should be used as a fallback when email is missing or delivery fails. Fail closed with a generic response; do not reveal whether an account or email exists.
6. Store only a hash of the OTP verifier; retain short expiry, bounded attempts/resends, purpose/account binding, IP binding where appropriate, and atomic single-use consumption. The delivery channel must be recorded as `email`.
7. No SMS gateway call, live email, production account, production database, or live session revocation in CI or development acceptance.

## Implementation changes required in PR #76

- Replace `RecoveryCodeDeliveryService`'s MSG91 implementation with a mail-based delivery service for `API_PASSWORD_RESET`.
- Use the user's registered, usable email address as the destination. Do not accept an arbitrary email address supplied by the unauthenticated caller.
- Add a dedicated recovery-code Mailable/Notification with a neutral subject and clear expiry guidance. Do not reuse the password-changed notification as an OTP message.
- Set the recovery challenge channel to `email`.
- Ensure the initiation response is generic for unknown users, missing email, throttling and delivery failure.
- Ensure no SMS fallback occurs and no OTP is included in logs.
- Add tests that assert Mail is faked/queued and no MSG91/cURL transport is invoked.
- Remove or defer device-recovery challenge routes/controller/tests from the release candidate, while retaining the HTTP 410 legacy protection.
- Update Android compatibility documentation to mark the OTP client change as deferred, not a current release gate.

## Scope still requiring separate confirmation

The existing public web registration mobile-verification flow uses the legacy OTP system and is distinct from the new API password-recovery flow. This decision document does not silently rewrite that legacy registration flow. Audit its actual delivery path and seek explicit approval before removing or changing any registration verification behavior.

## Release gates

- Exact-head ERP CI, Full Acceptance, Prototype and Windows Client workflows pass.
- Email delivery is tested with faked mail transport; no live mail is sent in CI.
- No recovery code is sent by SMS under any fallback path.
- Existing email reset links, authenticated password changes and registration behavior remain regression-tested.
- Device-wide recovery remains out of scope until separately approved.
- PR remains draft until code, tests, and compatibility evidence reflect this decision.