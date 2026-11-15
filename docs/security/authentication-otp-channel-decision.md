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

## Owner-account authority and recovery policy (additional owner direction)

1. Tagore ERP has one administrative authority: the **Owner** account belonging to the proprietor. Do not create or introduce a separate general-purpose Admin account or parallel administrator role as part of this work.
2. Preserve the full administrative rights on the Owner account, including institution/group-wide oversight, configuration, user and role administration, and the ability to change and reset passwords for managed accounts. Do not grant these powers to cashier, accountant, principal, coordinator, or other operational roles by implication.
3. The Owner must be able to change their own password while authenticated, using the current-password verification flow. A successful password change must be auditable and invalidate other active sessions/tokens according to the established security policy, without locking out the current session unexpectedly.
4. For Owner password recovery, send the one-time recovery code only to the Owner account's own **registered and verified email address**. Resolve the destination from the uniquely identified Owner account on the server; never accept a destination email from the unauthenticated request, never use SMS, and never fall back to another administrator's email.
5. Do not hard-code the Owner's email address in source code, documentation, fixtures, or Git history. The production Owner account's verified email is the source of truth. If it is absent or unverified, fail closed and require a secure, authenticated Owner-side email verification/update process; do not disclose account existence or silently use another destination.
6. Ensure there is exactly one active Owner identity in the configured production tenant/system scope. CI should use synthetic fixtures to test the single-owner invariant and must not read or mutate production users.
7. The Owner retains the ability to manage passwords for other users through an authenticated, audited owner-only workflow. For a managed user's password reset, notify that user through their own verified email where available; never expose plaintext passwords or allow an operator to choose an arbitrary recovery destination.
8. This owner-only authority does not reinstate the unsafe unauthenticated device-wide logout endpoint. Parent/teacher device recovery remains deferred and legacy unauthenticated mass-revocation remains HTTP 410.

## Additional acceptance tests required

- A single active Owner account can access all Owner-authorized administration and user/password-management functions.
- No second generic Admin account/role is provisioned by the new workflow; non-owner roles cannot escalate to Owner.
- Owner self-service password change succeeds only after current-password verification and is audited.
- Owner recovery code is delivered only to the Owner account's verified email, with no SMS or caller-supplied destination path.
- Missing/unverified Owner email fails closed with a generic public response and a clear owner-side remediation path.
- Owner can initiate an audited password reset for another user; non-owner roles cannot.
- Password recovery remains hash-stored, short-lived, rate-limited, purpose-bound and atomically single-use.

