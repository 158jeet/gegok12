# Mobile device recovery — deferred

**Owner decision (15 November 2026):** Parent/teacher device-wide recovery is not required for the current release. Do not implement OTP-based device recovery in the Android parent or teacher applications and do not treat it as a release acceptance gate.

## Security boundary retained

- Legacy unauthenticated device-wide logout handlers remain disabled with HTTP 410.
- No replacement public device-recovery endpoint is registered in this release.
- Normal authenticated logout remains available through the existing Sanctum-protected routes.
- Do not revoke all sessions/tokens based only on a submitted mobile number.
- Revisit device recovery only after a separate explicit Owner decision and a reviewed recovery design.

This document supersedes the earlier client-acceptance proposal for OTP-based device recovery. No production sessions, devices or tokens were changed.
