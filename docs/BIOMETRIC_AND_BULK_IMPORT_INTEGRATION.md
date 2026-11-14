# Biometric attendance and bulk import integration

**Status:** implementation specification; no device connection or live-data mutation has been performed.

## 1. Realtime Biometrics T304F-family attendance

### Verified integration surface

Realtime publishes a RealtimeBioConnect ASMX service at `https://apirealtime.com/RealtimeBioConnect.asmx` with a WSDL. Its documented attendance operation is `Download_Attendance_Log`, keyed by device serial number and date range. The published integration guide describes HTTPS/TLS 1.2, server-side credentials, device registration, and scheduled log downloads. The guide explicitly says this service does not provide a webhook/push event for new punches; clients must poll it. The separate Realtime Biometrics API product describes transfer of raw punch data to third-party applications, but the exact contract and compatibility with the installed T304F variant must be confirmed with Realtime.

### Proposed ERP flow

1. Register each device by institution, site/location, serial number, model, firmware, timezone and active state. Store vendor credentials encrypted server-side; never expose them in browser/mobile responses or logs.
2. Map each device user ID to exactly one active employee record within the institution. Surface unmapped IDs for HR review; never auto-match by employee name.
3. A scheduled queue job polls each registered device for a short overlapping time window. Stagger devices; record the successful high-water mark only after raw events commit.
4. Preserve immutable raw punch events with device serial, device user ID, punch timestamp, verification mode, direction/state if supplied, payload hash and received/synced timestamps.
5. Enforce idempotency using device + device-user identifier + event timestamp + stable vendor event identifier where available. Overlap/re-downloads must not duplicate punches.
6. Derive daily attendance using assigned shifts, weekly offs, holidays, approved leave, grace periods, late/early rules, half-day and overtime policy. Keep raw punches separate from derived attendance.
7. HR reviews exceptions and unmapped punches. Payroll consumes a locked/approved attendance summary for the payroll period; it must not deduct salary directly from an unreviewed raw punch.
8. Provide last sync, device online/last-seen, queued/failed sync, missing punch, duplicate, unmapped employee and late-arrival dashboards. Retry transient failures and allow controlled historical resync.
9. Keep a manual CSV/Excel import path for offline devices or vendor outages, using the same validation and deduplication pipeline.

### Security and operational controls

- Do not import or store face/fingerprint templates or biometric images in Tagore ERP. The ERP needs attendance events and employee-ID mappings only.
- Do not expose remote gate-open, enrolment, disable-user or template-upload commands in the first release.
- Device credentials are encrypted at rest, redacted from logs, rotatable and institution-scoped.
- Attendance correction requires reason, approver and immutable audit history; preserve original punches.
- All device clocks and ERP processing use Asia/Kolkata, while retaining the device's raw timestamp and timezone metadata.
- Test with synthetic employees and mocked vendor responses; no real employee punches until explicitly commissioned.

### Vendor/device confirmation gate

Before coding the live adapter, confirm the exact model label (T304F, T304F+, Pro 304F+ or another variant), firmware version, serial count, whether RealtimeBioConnect/API Realtime access is enabled for the account, and the WSDL operation signatures. Run a read-only connectivity test against one test device. Do not guess the SOAP parameter schema: the vendor's published guide marks operation parameter signatures as requiring confirmation against the live WSDL.

## 2. Reusable bulk import center

Build a shared import framework with module-specific adapters, validators and commit handlers—not a generic mass-update endpoint.

### User workflow

1. Each supported module has a visible **Download sample file** link next to its upload action.
2. Templates are versioned CSV/XLSX files with exact column names, required/optional markers, allowed values, date/number examples and a short instructions sheet.
3. User selects institution and relevant context (e.g. academic year, class, fee plan or payroll period), then uploads a completed template.
4. ERP validates file type, size, headers, row count, data types, references, permissions and institution scope.
5. Preview shows total rows, ready rows, warnings, errors, duplicates and likely updates. Row-level errors identify row number, field and actionable reason; downloadable error report is provided.
6. User explicitly confirms Apply. Apply runs in a database transaction or safe chunked/idempotent batches, records before/after audit data and produces a reconciliation report.
7. Failed rows never silently disappear. For atomic imports, any validation/commit failure rolls back the batch. For chunked imports, each committed chunk and failed row is reported with a resumable batch state.

### Initial import adapters

- Student details and guardian/family links.
- Staff/employee master details and department/designation assignments.
- Fee structures and fee heads; separate adapter for student-wise fee assignments/demand where supported.
- Staff attendance/punch history as an offline fallback for biometric sync.
- Future adapters: class/section allocation, academic promotion, transport assignments, inventory opening balances and payroll variable components.

### Data safety

- Insert vs update behavior must be explicit per template. Use stable external identifiers (admission number, employee code, fee-head code), never name-only matching.
- Duplicate external identifiers, ambiguous matches, cross-institution references, invalid academic years and unknown fee heads block the affected rows.
- Never import passwords, biometric templates, bank credentials or other authentication secrets.
- Student/staff personal data is institution-scoped; preview and apply require the same permission checks.
- Financial imports (fee structure, fee demand, opening balance, payroll inputs) require preview, explicit confirmation, reconciliation and immutable audit.
- Re-importing the same batch must be idempotent or rejected as already applied; never double-create fee demands or salary components.
- Templates are downloadable without exposing existing student/staff personal data. Offer blank templates and separately permissioned exports only where necessary.

### Acceptance requirements

- Sample templates download and match parser expectations.
- Valid, invalid, duplicate, ambiguous and cross-institution rows are covered by automated tests.
- Preview makes no data changes.
- Apply creates/updates only explicitly mapped records and writes audit entries.
- A failed import cannot partially mutate an atomic batch.
- Re-upload/retry cannot duplicate fees, staff, students or attendance events.
- One representative account per permission profile is tested; all fixtures are synthetic.

## 3. Delivery sequence

1. Restore green profile/logout acceptance and keep that PR unmerged until fully green.
2. Build the reusable import batch/template/preview/error-report framework and implement Student, Staff and Fee Structure adapters.
3. Add the Realtime device registry, employee mapping, raw punch store, scheduler/queue poller, attendance reconciliation and HR review workflow.
4. Connect approved attendance summaries to payroll generation and test salary calculations, leave/holiday/shift rules and audit controls.
5. Commission one device in read-only mode after the exact model, firmware and vendor API contract are confirmed.

## 4. Out of scope for first release

- Uploading face/fingerprint templates or images into ERP.
- Remote gate unlocking or changing access-control permissions.
- Automatic salary deductions based solely on a raw punch.
- Silent updates/deletes without a preview and audit trail.
- Production device credentials or live employee data in CI.
