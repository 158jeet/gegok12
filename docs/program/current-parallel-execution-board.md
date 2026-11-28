# Tagore ERP — Current Parallel Execution Board

**Baseline:** `integration/erp-7day` at `494b737bbd0e72ddce32d1e5d702f8b488237e74`  
**Execution mode:** Coordinated parallel workstreams; isolated branches; continuous integration into a single candidate branch.  
**Data boundary:** Synthetic fixtures and disposable databases only. No production deployment, live data, real account/session changes, real payment/email/SMS, or hardware activation.

## Operating correction

The agreed delivery model is the seven-workstream implementation programme plus independent QA, not a serial sequence of unrelated PRs. Existing PRs are candidate slices and validation evidence; they are **not accepted ERP functionality until integrated and re-tested together**.

No workstream should wait for another unless it depends on a shared contract or migration. Shared identity, institution/family scope, audit conventions, API conventions and UI primitives are centrally controlled. Every branch must state its requirement IDs, owned files, tests and dependencies.

## Workstreams

| ID | Scope | Current evidence / open work | Integration rule |
|---|---|---|---|
| WS-SEC | Single Owner, authentication/recovery, role matrix, institution/class/family authorization, audit | PR #90 fee scope, #87 academic authority, #88 owner account status, #101 backup safeguards; broader recovery and API/mobile route audit remains open | Own shared auth/role primitives; review every cross-institution change |
| WS-ADM | Admissions CRM, family linking, configurable forms/campaigns, fieldwork, entrance-test admission linkage | PR #94 CRM slice; dynamic form studio, campaign assignment, immutable versions, field submission and entrance-candidate linkage remain unimplemented/ unverified | No automatic enrolment or overwrite of existing family/student records |
| WS-ACA | Attendance, class/section, timetable, diary/homework, school exams, Olympiad, JEE/NEET assessment | PR #95/#97/#100 diary slices and #96 Olympiad correction; full combined school/coaching exam acceptance remains open | Enforce institution/class/academic-year scope server-side |
| WS-FIN | Fee ledger, cashier, family payment allocation, receipts, concessions, reconciliation | PR #90/#93 security and cashier slices; PR #99 multi-child family test; concession approvals, refunds/reversals and integrated ledger acceptance remain open | Keep child/institution ledgers distinct; no unauthorized adjustment |
| WS-HR | Employee records, leave routing, staff attendance, payroll and biometric ingestion | T304F registry candidate PR #69; device protocol/credentials and sync deliberately disabled; multi-institution Principal leave approval remains open | No live device polling until vendor contract and separate approval |
| WS-OPS | Gate pass, visitors, transport, hostel, library, inventory and campus workflows | Existing feature inventory must be reconciled with route/policy evidence; end-to-end operational acceptance remains open | No claim of CCTV/GPS vendor integration without verified provider tests |
| WS-APP | Parent/teacher apps, Windows client, notifications, offline sync and external integrations | Windows pilot CI exists; app parity, reliable offline queue/sync and provider integrations remain separately gated | Inspect each client/submodule and API contract; do not infer parity from web UI |
| WS-QA | Combined migrations, synthetic role journeys, browser/visual, regression, performance and release evidence | PR #98/#100 and #101 provide candidate CI evidence; no final integrated candidate SHA exists yet | Independent gate; skipped jobs are not passes |

## Current candidate PRs

These remain open/draft/unmerged and are not counted as integrated completion:

- #90 fee/class scope security
- #93 cashier workspace
- #94 admissions CRM
- #95 Daily Diary scope
- #96 Olympiad deterministic grade selection
- #97 Daily Diary field audit history
- #98 Diary + Olympiad validation candidate
- #99 family fee consolidation acceptance test
- #100 Diary audit + Olympiad validation candidate
- #101 backup and disposable restore verification

Related earlier research/plan PRs: #67, #69, #71, #72, #78. Their contents are requirements/evidence or candidate slices, not a substitute for current-baseline integration.

## Integration sequence

1. **Freeze shared contracts:** Owner-only top authority; explicit role/institution/campus/class/family membership; actor-attributed audit; validation/error conventions; migration ownership; synthetic fixtures.
2. **Run bounded workstreams concurrently:** Each owns separate modules/files and a branch from the current integration baseline. Avoid duplicate edits to shared routes, migrations and common views.
3. **Continuously integrate green slices:** Rebase/port changes onto a common candidate branch in dependency order; resolve shared-file conflicts centrally. Do not wait for every module to finish.
4. **Run combined acceptance on the integrated SHA:** clean migration, role matrix, cross-institution denies, financial invariants, admissions, diary, exam, HR/leave, operations, client build and backup/restore.
5. **Release decision:** record accepted/deferred/blocked requirement IDs with exact evidence. No production action is implied.

## Release-critical journeys

- Owner manages the group and roles without creating another Owner.
- Principal/Coordinator/Teacher see only their permitted institution, class and students.
- Parent sees only linked children, including all children under one login; Student sees only own permitted data.
- Accounts and Cashier remain distinct; family payments preserve per-child/per-institution ledger allocation.
- Admissions capture/review/conversion preserves existing records and family links.
- Published forms are immutable; staff see only assigned campaigns; submissions are auditable.
- Diary, attendance, examinations and results obey role and academic-year boundaries.
- Leave and concession approvals follow the configured authority chain and prevent self-approval.
- Sensitive files, exports, direct routes and APIs enforce the same server-side permissions as the UI.
- Backup integrity and a complete non-production restore rehearsal are demonstrated.

A dashboard, route, passing syntax check, or isolated PR workflow is not proof of a completed journey.

## Immediate execution focus

- WS-ADM: implement reusable form/campaign/version/submission foundation and connect it to admissions without auto-enrolment.
- WS-HR: map employee/institution assignments and design multi-institution leave approval routing; retain T304F sync disabled pending verified vendor protocol.
- WS-FIN: close family payment and cashier end-to-end invariants, concession approval boundary and refund/reversal coverage.
- WS-ACA: integrate diary audit/security and exam/Olympiad corrections, then extend to coaching test series and results.
- WS-SEC: close authentication recovery and API/mobile authorization gaps; preserve the single Owner model.
- WS-OPS/WS-APP: reconcile feature register to real routes/controllers/client builds and implement missing vertical slices.
- WS-QA: continuously run combined synthetic journeys against the candidate branch.

**Definition of done:** requirement is implemented, integrated into the common candidate, tested on that exact integrated SHA, and has no unresolved release-blocking security, financial-integrity, data-loss or tenant-isolation defect.
