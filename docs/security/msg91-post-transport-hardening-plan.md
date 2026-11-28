# MSG91 SMS transport hardening plan

**Status:** Investigated; implementation not yet started.  
**Scope:** Legacy MSG91 HTTP SMS transport shared by standard SMS, OTP delivery, and emergency notifications.  
**Observed on:** 2026-11-15, in the authentication-recovery branch at `e72c2ebe4b7d3487078cccbebbdfda66cbb5d2cb`.

## Finding

`app/Traits/MSG91.php` currently constructs `https://api.msg91.com/api/sendhttp.php` URLs with `authkey`, `mobiles`, `route`, `sender`, and `message` query parameters in all three methods:

- `sendSMS()`
- `getOTP()`
- `emergencySMS()`

TLS peer and hostname verification were hardened in PR #76, but TLS does not prevent sensitive query parameters from appearing in application, proxy, gateway, or observability URL logs. For OTP delivery, the message parameter contains the one-time code.

## Provider compatibility evidence

MSG91's official legacy PHP sample for `sendhttp.php` demonstrates HTTPS POST form submission using `CURLOPT_POST` and `CURLOPT_POSTFIELDS`:

- https://api.msg91.com/apidoc/samplecode/php-sample-code-send-sms.php

MSG91's legacy API material also describes GET/POST parameter submission:

- https://fonts.msg91.com/api/

This supports a POST migration in principle, but the deployed account's route, sender, template/DLT settings, and actual response behavior must be verified in a non-production test account before release. No live SMS should be sent by CI.

## Required implementation

1. Migrate all three call sites consistently to HTTPS POST with URL-encoded form fields; keep the URL free of auth keys, phone numbers, message bodies, and OTPs.
2. Preserve parameter names and current sender, route, country, and response behavior unless provider validation proves a required change.
3. Move environment reads behind Laravel configuration (for example, a dedicated SMS config entry) so configuration caching behaves predictably.
4. Keep TLS peer verification enabled and hostname verification at strict mode; enforce connection and total timeouts.
5. Close cURL handles deterministically and distinguish transport errors from provider rejection without exposing secrets.
6. Do not log request bodies, auth keys, OTPs, full URLs, or raw provider payloads that may contain sensitive values. Redact any retained diagnostics.
7. Preserve `sendSMS()` reminder response updates and audit all existing callers for return-value/error compatibility.
8. Add mocked transport tests for each method asserting POST, form body, HTTPS endpoint, absence of sensitive query parameters, TLS verification, timeout configuration, and safe error behavior.
9. CI must never call MSG91 or send live SMS; mock the HTTP/cURL boundary.
10. Document required environment/config keys and a rollback procedure.

## Acceptance gates

- All three methods use the same safe transport implementation.
- Unit/feature tests cover success, provider rejection, timeout, connection failure, and redaction.
- Existing SMS caller behavior is checked for compatibility.
- Exact-head ERP CI, Full Acceptance, Prototype, and Windows Client workflows pass.
- A non-production provider/account test is completed by an authorized maintainer before production rollout.
- No production configuration changes, live SMS, deployment, or merge are authorized by this plan.

## Repository constraints

Git is the source of truth. Keep implementation isolated in a dedicated PR. The repository has Issues disabled (GitHub returned HTTP 410 when attempting to create a tracking issue), so this plan is retained as a version-controlled document instead.
