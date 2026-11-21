# Owner Command Centre, Daily Assurance & Hostel Operations

**Status:** Owner-approved scope addition; discovery and architecture required before implementation.  
**Added:** 20 November 2026  
**Execution rule:** Git-first, synthetic data only, no live data, hardware activation, production migration, merge or deployment without explicit approval.

## 1. Product objective

Tagore ERP becomes a group-wide operational command centre. The Owner should spend approximately ten minutes reviewing one screen to understand whether every institution, hostel, department and critical service is functioning, what failed, what is late, what data is stale, who owns each exception and what corrective action is pending.

This is not a wall of manually typed reports. It is a **verified operational control system**:
- Reuse evidence already created by attendance, timetable, diary, fees, admissions, HR, payroll, transport, inventory, communication, hostel and finance modules.
- Generate expected daily work automatically from role, institution, unit, shift, calendar and approved leave.
- Ask people to attest only to facts the ERP cannot independently observe.
- Cross-check attestations against source records, independent signals, stock/financial equations, timestamps, random inspection and downstream outcomes.
- Missing or stale data is UNKNOWN/STALE, never green.
- Alert the person accountable for correction and escalate unresolved material issues to the Principal/Unit Head and Owner according to severity and scope.

## 2. Command-centre experience

### Owner's 10-minute cockpit
A single group-wide view with filters and drill-down:
1. Overall health across schools, college, coaching, hostels, transport and central departments: healthy / attention / critical / unknown.
2. Audit coverage: expected, submitted, independently verified, failed, late, missing and stale by role, unit, shift and institution.
3. Critical alerts: safety, missing student/resident, food safety, unexplained cash/stock variance, attendance anomalies, overdue approvals, data-feed failure and repeated control failure.
4. People and accountability: accountable role/person, unresolved tasks, overdue corrective actions and repeated verified failures, with approved leave, staffing shortage and system outage context.
5. Finance/resources: fee collection vs demand, cash close variance, hostel dues, procurement, consumption, stock and payroll exceptions.
6. Academic delivery: class/teacher coverage, attendance, diary/homework completion, assessments and unresolved student support concerns.
7. Trends: today's exceptions vs 7/30-day trend, recurrence and deteriorating controls.
8. Data trust: last source update, sync failures, missing expected data and confidence/provenance.

Every KPI opens its definition, source records, calculation time, scope and exceptions. No unexplained composite score. Principals see their institution; Coordinators their assigned grades/sections; Hostel Superintendents their assigned hostels; department heads their departments. Owner retains group-wide visibility. Managers cannot silently delete or self-close critical failures.

## 3. Role-based daily audit catalogue

Audits are generated from versioned templates and operating calendars, not created ad hoc.

| Role | Daily evidence and controls |
|---|---|
| Teacher | Timetable periods covered, class attendance, diary/homework where due, absence follow-up and assessment exceptions; reconcile against timetable/register |
| Coordinator | Teacher/class coverage, missing attendance/diary, substitution gaps, section exceptions and unresolved concerns; aggregate teacher records rather than duplicate totals |
| Principal | Opening/closing readiness, staffing/timetable coverage, attendance and safety exceptions, fee/operations review and overdue corrective actions |
| Hostel Superintendent | Resident occupancy/roll-call, warden shift handover, leave/outpass, mess/stock variance, laundry, fees, incidents, maintenance and daily close |
| Warden | Shift roll-call, resident movement/leave returns, room inspection, welfare/complaint follow-up and visitor/incident handover |
| Mess Incharge | Meal plan vs actual service, headcount, ingredients received/issued, opening/closing stock, waste, hygiene/storage, vendor/invoice and variance |
| Laundry Incharge | Batch received, resident-wise issue/return, pending/damaged/lost items, custody handover and resident acknowledgement |
| Cashier | Opening float, receipts by mode, cancellations/reversals, pending/overdue follow-up, closing cash and system-vs-cash variance |
| Accounts | Bank/gateway/collection reconciliation, unmatched entries, refunds/adjustments, payroll/expense exceptions and closing balances |
| Transport Incharge | Vehicle/driver/route readiness, assigned student coverage, trip start/end, approved GPS/device freshness, breakdown/late/missed pickup and fuel/maintenance exceptions |
| HR | Staff attendance/punch exceptions, leave approvals, shift gaps, overtime anomalies, payroll inputs and device/source sync health |
| Department Head | Service queue, assets/stock, incidents, SLA breaches, approvals, staff coverage and corrective actions |
| Owner | Group-wide exceptions, overdue critical actions, unresolved management alerts, audit coverage, source freshness and repeat failures |

Exact questions, frequency and thresholds require mapping real Tagore schedules and responsibilities. Never ask a role to re-enter a metric already reliably produced by a source module.

## 4. Anti-fabrication and evidence integrity

A checklist tick is an **attestation**, not verification. Every control gets an evidence class:
- **A — system-derived:** reconciled from source records (timetable vs attendance, receipts vs ledger, stock equation, assigned residents vs roll-call).
- **B — independently corroborated:** submitter plus separate system, peer, supervisor, resident/guardian acknowledgement or sampled inspection.
- **C — human attestation:** unavoidable; record actor, timestamp, reason and proportionate evidence, with risk-based review. Never label it independently verified.

Controls:
1. Server-generated instance ID, template version, due window and accountable assignee.
2. Server timestamps and append-only history for submit, edit, evidence, review, fail, reopen and close.
3. Late submissions remain late; edits never erase original values or due state.
4. Duplicate attachment hashes, repeated photos, copied text, impossible quantities, backdating and out-of-window submissions raise risk flags.
5. Source comparisons: cash/receipts, stock equations, roll-call, meal counts, timetable, attendance, route events and approved leave.
6. Random risk-weighted spot checks; reviewer is not submitter. Retain sample selection, inspection, finding, response and appeal.
7. Cross-role confirmation for high-risk claims (mess issue vs store issue, laundry issue vs resident receipt, class period vs coordinator review).
8. No deletion/overwrite of audit, evidence, alert or critical finding. Corrections are new events with reason and author.
9. Missing evidence or unavailable integration is UNKNOWN/STALE, not PASS.
10. Device time, GPS, photo and biometric signals are corroboration only, never sole proof. Collect location only when necessary, disclosed and approved.
11. Restrict safeguarding, wellbeing, health and employee-sensitive details; show managers the minimum necessary operational signal.
12. Staff can challenge inaccurate records and provide context. No automated score alone triggers discipline.

## 5. Alert and corrective-action lifecycle

Severity is policy-driven:
- **Critical/immediate:** missing resident, credible safety concern, food-safety critical limit, suspected cash/stock loss or imminent risk. Notify on-duty manager immediately and escalate to Principal/Owner by configured response SLA.
- **High:** missed critical audit, unexplained reconciliation variance, repeated coverage absence, unresolved security/transport incident. Notify unit head/Principal; escalate to Owner when overdue or repeated.
- **Medium:** late routine audit, recurring service defect, unresolved concern. Assign owner and due date; escalate on recurrence/SLA breach.
- **Informational:** trend, reminder or data-quality warning.

Lifecycle: generated → assigned → acknowledged → investigated → corrective action → independently verified → closed; reopen on recurrence. Record root cause, action owner, due date, evidence and reviewer. Originator cannot independently close own critical finding. Managers see their scope; Owner sees all. No silent deletion or suppression.

## 6. Dedicated hostel management suite

Existing room/allocation tables are a basic foundation, not a complete hostel ERP. Build a dedicated Hostel workspace with hostel/building/wing/floor/room/bed and staff/shift scopes.

### Residents and occupancy
Hosteller 360 linked to canonical student/guardian identity; admission, allocation, bed changes, check-in, leave, outpass/return, checkout, deposits and history. Enforce bed capacity and prevent double allocation. Reconcile occupancy to active residents.

### Attendance, movement and safety
Morning/evening/night roll-call by building/wing/room/shift; leave approval, gate out/in, late return and visitor/guardian pickup with actors/timestamps. Missing-resident escalation and warden handover are mandatory. Restricted incident, complaint, safeguarding and welfare workflows require minimum disclosure and retention controls.

### Hostel fees
Hostel fee heads, instalments, deposits, concessions, arrears and parent statements use the common finance ledger. Cashier collects within permission; hostel staff cannot edit central entries. Reconcile demand, receipts, waivers and outstanding.

### Laundry
Resident/batch item intake, tagging, sorting, vendor/staff custody, issue/return, acknowledgement, missing/damaged/lost dispute and turnaround SLA. Begin with auditable batch ledger; QR/barcode optional later.

### Mess and food controls
- Weekly menu and breakfast/lunch/dinner/snack service plan.
- Expected headcount from occupancy, approved leave and meal opt-in; actual meal count/service confirmation.
- Ingredient master, units, vendors, purchase orders, invoices, receipt/quality checks, stock lots, expiry and storage.
- Recipe/BOM per meal, planned consumption, kitchen issue, actual usage, waste/returns, closing stock and variance.
- Cost per meal/resident/day, menu cost, vendor price trends, stock-outs and wastage.
- Hygiene, water, cleaning, temperature/storage and pest-control checks with thresholds and corrective actions.
- Student meal feedback and sampled service verification. A published menu is not proof the meal was served.

Stock equation: **opening stock + accepted receipts − kitchen issues − approved waste/adjustments = closing stock**. Support unit conversions, lot/vendor/invoice evidence and dual approval for adjustments.

### Morale and wellbeing
Periodic confidential resident pulse on food, cleanliness, safety, staff conduct, study environment, sleep and belonging. Support anonymous feedback with minimum cohort thresholds; protect identity from implicated staff and prevent retaliation. Route safety concerns to authorised safeguarding roles, not public leaderboards or popularity scores.

### Facilities and daily close
Room/asset inspection, maintenance tickets, vendor, SLA and recurring defects. Superintendent close consolidates occupancy, roll-call, outpasses, incidents, mess, food stock, laundry, fees, maintenance and morale. It cannot be marked fully clear with unresolved critical exceptions; submit-with-exception must preserve escalation.

## 7. Architecture work packages

Avoid one giant audit table. Proposed bounded contexts:
- Audit policy: templates, versions, role/unit assignments, calendars, schedules and due instances.
- Evidence ledger: immutable events, evidence metadata/hash, source references, verification checks, sampling and reviewer outcomes.
- Exception management: alerts, severity, recipients, acknowledgement, SLA, escalation, corrective action, root cause and closure/reopen.
- Operational metrics: source-backed daily snapshots, KPI definitions, lineage and freshness.
- Hostel core: buildings, rooms/beds, assignments, resident allocations, roll-call, movement, outpasses and incidents.
- Hostel services: laundry, menus/meals, ingredients/units/recipes, procurement/receipts/issues/waste/stock counts, hygiene, feedback and maintenance.
- Reporting: Owner group cockpit, Principal institution cockpit and role-specific daily workspace/evidence packs.

All records carry institution and relevant hostel/unit/shift scope. Enforce server-side role checks on pages, APIs, exports, jobs and attachments. Prefer foreign keys, unique constraints, transactions and idempotency. Preserve source lineage; never duplicate operational facts into manually editable audit fields.

## 8. Delivery sequence and acceptance

1. Discover existing role seeds, hostel routes/controllers/views/models, attendance/fees/inventory/transport/audit events, scheduler and notification delivery. Map actual daily routines.
2. Foundation: KPI definitions, templates/versioning, instance generation, evidence provenance, immutable log, alert/SLA lifecycle, access matrix and freshness.
3. School daily assurance: teacher → coordinator → principal controls, system-derived class coverage and escalation.
4. Finance/transport/HR assurance: cashier/accounts/transport/HR reconciliation and exceptions.
5. Hostel core: occupancy, warden shifts, roll-call, outpass/return, incidents and fees.
6. Mess/laundry: inventory/procurement/recipe consumption, hygiene, meals, laundry custody and resident acknowledgement.
7. Management intelligence: Owner 10-minute cockpit, Principal cockpit, recurrence analysis and fair scorecards with human review.
8. Hardening: synthetic multi-institution/multi-hostel tests, privacy/security, concurrency, source outage, notification retries, evidence tampering and responsive role acceptance.

Acceptance proves exact-once audit generation; missing/late/failed states cannot be greened; source outages are stale/unknown; prohibited submit/review/close combinations fail; metrics reconcile to source rows; replay/edit preserves history; sampling is independent; critical alerts deliver/escalate; Owner and Principal scopes are correct; hostel occupancy/attendance/stock/meal/laundry/fees reconcile; wellbeing data is protected; desktop/mobile, exports, retries, performance and backup/restore pass.

## 9. Safeguards and non-goals

No covert surveillance; no assumption that photo/GPS/biometric presence proves quality; no green KPI when data is absent; no AI-only ranking or discipline; no broad exposure of wellbeing/safeguarding details; no live biometric/GPS/CCTV/vendor integration before protocol, security, privacy and owner approval.

## 10. Initial repository reconnaissance (20 November 2026)

The first code inspection confirms that there is useful foundation, but the requested workflows do not yet exist as a complete system:

1. **Generic audit log exists, not daily assurance.** `tagore_audit_events` and `App\\Services\\Tagore\\AuditService` record actor, institution, action, entity, old/new values, IP and user agent. This is a generic change log; inspection found no role-based audit template/instance lifecycle, evidence provenance, verification class, sampling scheduler, escalation SLA or daily close engine in this service.
2. **Some management signals already exist.** `Tagore\\DashboardController` calculates active/overdue tasks, employee task completion/workload and manager follow-ups for Owner/Principal/Coordinator. These are useful inputs, but task completion is not an independent measure of teaching, hostel, transport, finance or service quality and must not be presented as a verified reliability score.
3. **Hostel is currently a generic operations record, not a dedicated hostel product.** `OperationsController` exposes a generic `hostel` module with fields such as hostel name, room, bed, student, check-in/check-out and status. It stores generic rows in `tagore_hostel_records`; it does not implement resident lifecycle, occupancy rules, roll-call, outpass, warden shifts, mess, laundry, food stock/recipes, hygiene, confidential wellbeing, incidents or superintendent daily close.
4. **A small structured hostel foundation exists.** Migration `2026_09_27_180000_create_tagore_learning_hostel_store.php` creates `tagore_hostel_rooms` and `tagore_hostel_allocations`. The allocation schema records room, student, bed number, check-in/check-out and status, but this first-pass review does not find the full operational lifecycle, movement ledger, shift attendance, meal/ingredient stock, laundry or incident domains. Capacity/active-bed concurrency and cross-institution student validation require explicit tests before relying on allocations.
5. **Role alignment is incomplete.** `TagorePrototypeSeeder` seeds eight primary roles (Owner, Principal, Coordinator, Teacher, Parent, Student, Accounts and Fee Editor). The operations controller references HOSTEL, TRANSPORT and HR role codes, and the seeder creates Hostel/Transport/HR departments, but the dedicated superintendent, warden, mess and laundry roles are not in its primary role catalogue. Department existence is not staff authorization.
6. **Hostel routes are generic.** `routes/tagore.php` routes hostel create/update/delete through generic `/operations/{module}` endpoints; this is not a domain-specific hostel workflow or a resident/warden/mess portal.
7. **The existing audit service is not by itself tamper-proof.** It inserts events through application code, but the inspected service/table design does not establish database-enforced append-only permissions, cryptographic evidence integrity, independent review or immutable retention. Those properties must be designed and tested, not assumed.

**Discovery conclusion:** Do not simply add more fields to the generic hostel form or more checkboxes to the existing task module. Build a dedicated assurance domain that consumes existing operational data, and a dedicated hostel domain that can reconcile its own attendance, fees, food, laundry, safety and resident experience. The first implementation slice should be the audit foundation and alert lifecycle; role-based templates can then be delivered incrementally by unit. Hostel core should follow as a separate, testable bounded context.

## 11. Traceability

Detailed requirements are in `feature-gaps.csv` as AUD-097–AUD-107 and HOST-001–HOST-013. This document is the canonical scope and acceptance reference for this programme.
