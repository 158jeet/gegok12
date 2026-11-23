# Realtime T304F biometric integration — current safe boundary

## Implemented in this slice

- Register multiple Realtime T304F devices against an institution and physical site.
- Store serial number, device name, firmware metadata and connection readiness.
- Map a device-side user ID only to an existing employee account with an active, date-effective Tagore staff role in the same institution.
- Preserve the device registry, employee mappings and raw-punch schema with restrictive foreign keys so deleting an institution/device cannot silently cascade-delete punch evidence.
- Device sync is disabled by default. The UI and controller do not assume a vendor endpoint, SOAP operation, authentication scheme or firmware behavior.
- No fingerprint/face templates are copied into Tagore ERP; the future connector should ingest only attendance-event metadata needed for attendance/payroll.

## Not implemented / deliberately blocked

- Live polling, vendor API calls, push callbacks, device clock configuration, punch ingestion, reconciliation and payroll posting.
- No endpoint is stored or polled until the exact vendor contract is confirmed.
- A registry entry is not evidence that the device is connected or syncing.

## Required vendor evidence before connector implementation

1. Exact model variant and firmware version for each device.
2. Official vendor API/SDK/WSDL documentation and supported operations.
3. Authentication method, credential provisioning/rotation and least-privilege read-only account.
4. Network/cloud configuration and allowlisted host/port requirements.
5. Device-side user-ID export format, event identifiers, timezone behavior and pagination/overlap semantics.
6. Vendor confirmation of duplicate delivery, late-arriving punches, correction/deletion events and rate limits.
7. A synthetic/test device or sandbox for a read-only connectivity test.

## Future connector acceptance

- Multiple institutions, sites and devices remain strictly isolated.
- Raw punch payloads are append-only and retained with a deterministic dedupe key; replaying the same vendor event creates no duplicate.
- Unknown device user IDs remain explicitly unmapped; no guessed employee is assigned.
- Punch timestamps are normalized using the configured device timezone while retaining the original event timestamp/payload.
- Corrections require a reason, actor, approval and immutable history; raw punches are never overwritten.
- Sync remains disabled on credential, protocol, clock, timezone or schema errors and exposes a clear stale/failed state.
- Attendance/payroll calculations consume only reconciled, approved punch records and never write directly from unverified raw events.

## Guardrail

No real device call, live biometric data, credential, production import or payroll posting is part of this PR. Enablement requires separate owner approval after vendor documentation and synthetic acceptance pass.
