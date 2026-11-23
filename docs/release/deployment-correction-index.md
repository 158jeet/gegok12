# Tagore ERP — Deployment Correction & Reconciliation Index

**Purpose:** A compact, Git-tracked index of defects fixed, CI/workflow changes, deployment-time actions, and unresolved verification items. This is the final-release reminder; it is not a substitute for the actual code changes, migrations, tests, or release checklist.

**Canonical branch:** `integration/erp-7day`  
**Repository:** `158jeet/gegok12`  
**Policy:** Synthetic/test data only until ERP acceptance, verified backup/restore, and explicit owner approval. Do not deploy, migrate production, activate hardware, or load live data without approval.

## How to maintain this file

- Add one concise row for every material correction or CI/deployment improvement made from now on.
- Record the commit SHA, affected branch/PR, affected files or subsystem, and verification status.
- Never mark a correction as deployment-ready solely because it was committed; require passing relevant tests and integration acceptance.
- Before release, reconcile every row against the final integration/release commit. Cherry-pick/reimplement only where the correction is not already included; avoid duplicate application.
- Keep unresolved items in the final section until verified. Update this index in the same change/PR as the correction whenever practical.

## Correction and release-action register

| ID | Area | Correction / reminder | Recorded change | Final deployment action | Status |
|---|---|---|---|---|---|
| CI-001 | GitHub Actions | Reduce duplicate broad pipelines on feature work: ERP CI is PR/manual; Prototype is manual-only; Full Acceptance is integration/main push plus manual dispatch. Tests were not deleted. | Integration commits `f5cecb3f55782d8eb60ff94ab42653cef7236c6b`, `7e6c7f97a409a8feb3374bd8f8ee9c446cadb669`, and `ff1bc888c792e42765ac3eed5560f9b28e84c9a8` (documentation-only pushes are ignored by Full Acceptance; mixed docs + code still run). | Ensure final branch contains these trigger rules; verify each required test suite still runs at the intended gate. Older feature branches may retain old workflow definitions until reconciled. | Implemented on integration; branch propagation and end-to-end trigger policy still to verify. |
| CI-002 | Feature-branch workflow inheritance | Align an active feature branch with the integration workflow policy: ERP CI runs on PR/manual, Prototype is manual-only, and Full Acceptance runs on main/integration pushes or manual dispatch (documentation-only push exclusions retained). | PR #105 branch `work/ws-owner-security-hardening`, commits `8d09415ee27c83a663366a073c2220a774e147d4`, `8e1705246f84bd44144db14c51dc1e52c649f20f`, and `15c6d33ec4085693e19d00c1ae521445f8fc42c8`; three workflow files. | Apply the same policy to other active feature branches in a controlled sequence; verify PR CI still runs and broad workflows do not run on feature pushes. | Implemented on PR #105 branch; old runs triggered before final workflow alignment may still finish. |
| HOST-001 | Owner / hostel operational audit | Repair malformed Carbon imports in the operational audit service. | PR #118 branch `work/owner-command-centre-hostel-audits-clean`, commit `cfea987bf74f63f3674c8138ef43f45022ef031e`; file `app/Services/Tagore/OperationalAuditService.php`. | Ensure the corrected file is included in final integration/release branch; run PHP syntax, focused feature tests, migrations and full acceptance. | Committed on feature branch; validation pending. |
| HOST-002 | Owner / hostel operational audit tests | Repair incorrectly escaped `InvalidArgumentException` in the system-derived evidence test. | PR #118 branch `work/owner-command-centre-hostel-audits-clean`, commit `01499b14ddf852c8941b835a44d0019bdceb86f3`; file `tests/Feature/TagoreOperationalAuditFoundationTest.php`. | Ensure the corrected test is included in final integration/release branch; run the focused test and full acceptance. | Committed on feature branch; validation pending. |
| SEC-005 | Owner impersonation tenant and session safety | Restrict non-Owner impersonation to same-school targets; allow cross-school selection only to Tagore Owner; prevent impersonating Owner/admin targets; restore the original authenticated actor on stop; use POST + CSRF for start/stop actions. | Original PR #105 branch `work/ws-owner-security-hardening` (historical head `adfe2d8ed9e96508bf7f251b16fd013d2ea7a497`); rebuilt on current integration in draft PR #122 branch `work/owner-impersonation-integration-reconcile`, head `600beed9375956f19c728e25f52568409ec1b9aa`; files `ImpersonateController.php`, three Blade views, `routes/web.php`, and `AuthorizationGateRegressionTest.php`. | Await exact-head ERP CI; review all impersonation entry points, route verbs, session restore and audit behavior; run security acceptance. | Current-baseline PR #122 head `600beed9375956f19c728e25f52568409ec1b9aa` passed all five ERP CI jobs in run `44873655899`; comparison is 6 ahead / 3 behind with six changed files. Review and current-base drift resolution remain pending. Original #105 is historical source only. |\n| SEC-004 | Gate-pass institution isolation reconciliation | Resolve the selected institution from the actor's authorized active scope and require the student to belong to that institution's school before creating a gate pass; retain positive and cross-institution regression tests with a synthetic second institution when needed. | Original PR #111 branch `work/gatepass-institution-validation` (historical head `3aacf9bf78718cc1ee788f5fb9391aad77cb3d8d`); rebuilt from current integration in draft PR #120 branch `work/gatepass-institution-validation-integration-reconcile`, head `fbfe6b9cc2ea0d13e6d8f76032574e10a110556f`; files `SecurityController.php` and `TagoreGatepassInstitutionScopeTest.php`. | Await exact-head ERP CI; run focused gate-pass scope tests and current integration acceptance. | Current-baseline PR #120 head `fbfe6b9cc2ea0d13e6d8f76032574e10a110556f` passed all five ERP CI jobs in run `44873348882`; comparison is 2 ahead / 7 behind with only the gate-pass controller and focused regression test changed. Review and current-base drift resolution remain pending. Original #111 is historical source only. |\n| HR-002 | HR/payroll institution-scoped authority | Restrict payroll operations to the actor's authorized active institution assignments; prevent unrelated Teacher role assignments from expanding manager leave visibility/decision authority; preserve Owner group-wide payroll visibility; serialize leave decisions against concurrent updates. | Original PR #107 branch `work/ws-hr-leave-payroll-hardening` (historical head `d729b84042748edf9a6a055b71692b5f8418fcf2`); rebuilt on current integration in draft PR #121 branch `work/hr-payroll-scope-integration-reconcile`, head `e9290301eabac00da1231288bcf8a7fd64ea4067`; files `PayrollController.php`, `StaffController.php`, `TagoreHrPayrollScopeTest.php`, `TagoreManagerDashboardTest.php`. | Await exact-head ERP CI; run focused HR/payroll scope tests and integration acceptance after reconciliation. | Current-baseline PR #121 head `e9290301eabac00da1231288bcf8a7fd64ea4067` passed all five ERP CI jobs in run `44873432095`; comparison is 4 ahead / 5 behind across the two HR controllers and two focused tests. Review and current-base drift resolution remain pending. Original #107 is historical source only. |\n| ATT-001 | Staff attendance reliability | Ensure `createStaffAttendance` rolls back and rethrows `Throwable` failures instead of swallowing them; controller returns a safe logged JSON error and the UI displays a non-sensitive message. | Original PR #110 branch `work/staff-attendance-error-handling` (historical head `5066d8a5fa202f60a68bc75b3e92c780966e53b0`); rebuilt on current integration in draft PR #119 branch `work/staff-attendance-error-handling-integration-reconcile`, head `7b4070ea8f7e8e926ce5800abb88c2fc5a7ea0a2`; files `AcademicProcess.php`, `StaffAttendanceController.php`, `Create.vue`, and `TagoreStaffAttendanceFailureTest.php`. | Run exact-head ERP CI and focused attendance failure test on #119; then re-run integration acceptance after reconciliation. | Reconciliation PR #119 head `7b4070ea8f7e8e926ce5800abb88c2fc5a7ea0a2` passed all five ERP CI jobs in run `44872902241`. All five exact-head ERP CI jobs passed; comparison is 5 ahead / 9 behind across the attendance controller, trait, Vue form and focused regression test. Focused review and current-base drift resolution remain pending. Original #110 is historical source only. |
| SEC-002 | Gate-pass tenant isolation | Make cross-institution gate-pass regression deterministic by creating a second synthetic school/institution when the default CI seed has only one; remove the conditional skip. | PR #111 branch `work/gatepass-institution-validation`, commit `3aacf9bf78718cc1ee788f5fb9391aad77cb3d8d`; file `tests/Feature/TagoreGatepassInstitutionScopeTest.php`. | Run focused gate-pass scope test and full Laravel acceptance; confirm no skip and no gate-pass row for a foreign student. | Committed on feature branch; validation pending. |
| HR-001 | HR leave/payroll authorization fixture | Replace a conditional cross-institution test skip with a synthetic second school/institution, teacher and academic year; verify a principal's teacher assignment in another institution does not expand manager leave/payroll authority, while Owner retains group-wide payroll visibility. | PR #107 branch `work/ws-hr-leave-payroll-hardening`, fixture commits `2cdf250f3d19eb265b472a50e766333e07192c61` and `f4d108e0773985d0a8e34e8c1fe85730ca99764a`; current head `d729b84042748edf9a6a055b71692b5f8418fcf2`. | Reconcile `StaffController.php`, `PayrollController.php` and both HR scope tests with current integration; run focused HR tests and full integration acceptance. | Exact-head ERP CI run `44871879262` passed all five jobs. Feature branch remains 7 ahead / 21 behind integration; not integrated or release-ready. |
| SEC-003 | Impersonation exit safety | Restore the persisted original login identity after stop; the package's `stopImpersonating()` only clears its target flag and middleware uses `onceUsingId()` for the current request. Redirect based on the explicitly restored actor. | PR #105 branch `work/ws-owner-security-hardening`: `ab64fac13e588fa9ffae17888255c78383a3ec8c`, `140ef05384fcd0c591868a8de26b8025564394f2`, `a67113f9180bad727a373ae341e324b8e96bddc8`, `adfe2d8ed9e96508bf7f251b16fd013d2ea7a497`; controller and `AuthorizationGateRegressionTest.php`. | Re-run focused authorization test and full ERP CI on latest head; verify Owner-to-teacher stop returns to Owner dashboard and authentication is restored. | Initial regression test correctly failed (teacher dashboard returned); middleware behavior diagnosed and restoration fix committed. Latest head `adfe2d8ed9e96508bf7f251b16fd013d2ea7a497` passed all ERP CI jobs and acceptance gate in run `44869961036`; integration reconciliation and Owner review remain open. |

## Known release gates and unresolved reconciliation reminders

1. **PR #118:** The two syntax corrections above are on its feature branch, not yet confirmed in `integration/erp-7day`. Do not treat the Owner command-centre/hostel work as accepted until focused tests and full integration acceptance pass.
2. **Workflow inheritance:** Pushes to older feature branches (including PR #118 and other active PR branches) can trigger ERP CI, TagoreK12 Prototype, and Full Acceptance because those branches still have the previous workflow files. Reconcile workflow definitions on active feature branches or ensure the final integration branch's definitions govern the release path. PR #105 has been aligned; its old push-triggered runs are historical and do not establish current-head acceptance.
3. **Full acceptance:** Integration Full Acceptance passed on commit `ff1bc888c792e42765ac3eed5560f9b28e84c9a8` (run `44867964104`). Re-run after any runtime-code changes.
4. **PR reconciliation:** Resolve duplicate/overlapping PRs and dependencies using `docs/research/tagore-erp/open-pr-reconciliation.md` before final integration. Do not merge blindly.
5. **Production safety:** Before any production release, confirm a tested backup and restore, environment/secrets configuration, migrations, queue/worker/scheduler health, HTTPS, access controls, audit logging, and rollback procedure.
6. **Live data and hardware:** Keep live student/parent/employee data, payment gateway activation, biometric devices, CCTV integrations, and production messaging disabled until the relevant acceptance and explicit owner approval.

## Final release reconciliation checklist

- [ ] All register entries reconciled against the exact release commit; no required fix left on an unmerged feature branch.
- [ ] All feature-register P0/P1 requirements have implementation evidence and passing acceptance tests.
- [ ] No unresolved PHP syntax, frontend build, migration, route, permission, or dependency failures.
- [ ] Required focused suites, ERP CI, prototype checks (when applicable), and full ERP acceptance pass on the release candidate.
- [ ] Workflow triggers are intentional; no unnecessary duplicate broad runs.
- [ ] PR overlap/dependency reconciliation is complete and reviewed.
- [ ] Backup and restore tested; rollback plan documented.
- [ ] Production environment and secrets checked without exposing credentials in Git.
- [ ] Owner explicitly approves deployment and separately approves live-data/hardware activation.

## Important interpretation

This index is a deployment memory aid. It does not mean every correction should be replayed manually at deployment. The final release should contain the corrected source through normal Git integration; this index ensures we remember to verify that it does.


- **CI-003 — HR feature branch workflow inheritance:** PR #107 branch `work/ws-hr-leave-payroll-hardening` had legacy push triggers. The three workflow definitions were synchronized to integration in commit `d729b84042748edf9a6a055b71692b5f8418fcf2`. Current-head ERP CI run `44871879262` passed all five jobs. Earlier runs on `2cdf250` / `f4d108e` were triggered before synchronization and are historical. No runtime deployment or live data was involved.


## Finance / payment reconciliation

| PAY-001 | Finance / online payments | Prevent concurrent online checkouts from reserving the same outstanding fee balance; quarantine captured-but-stale allocations for office reconciliation rather than over-allocating the ledger. | Current-baseline draft PR #123, head `4402e3a7b1067f34155e8a7fc80bb010d75efec1`; controller, service and concurrency regression test. | Verify reservation expiry, gateway failure release, stale capture reconciliation, idempotency and receipt suppression; use gateway sandbox only after explicit owner approval. | Exact-head ERP CI run `44875663835` passed all five jobs. Draft only; not merged. |


| LIFE-001 | Academics / student promotion | Institution-scoped, bounded, idempotent promotion with immutable source-year records and audit events. | Current-baseline draft PR #124, head `9cd09eda3c0c97a4debce2c6b42fdc83352f9d0a`; controller, migration, Owner seeder, routes, UI and tests. | Validate role scoping, source/target year boundaries, no overwrite of existing target enrolment, batch cap, audit events, responsive UI and browser route audit. | Corrected shared fixture state leak, enforced Owner/Principal/Coordinator workspace authorization, removed unauthorized Academic Structure link. Exact-head ERP CI run `44876972392` passed all five jobs; Windows Pilot Package run `44876972367` passed. Draft only; no merge. |


| PAY-002 | Finance / partial fee allocation | Allow parents to allocate a checkout amount to one or more selected fee obligations, including partial payment of each, without exceeding available balances reserved by other active checkouts. | Draft PR #125, head `9741e99f21591ab7bd356fac005ac13849651751`, stacked on #123. | Verify exact sum of allocations equals checkout total; reject duplicate, foreign, stale or over-reserved obligation IDs; preserve FIFO fallback for internal callers; test responsive parent form. | Exact-head ERP CI run `44877160843`: backend, frontend and production Docker smoke passed; browser E2E still in progress. #125 currently includes #123 dependency diff until #123 is integrated; no merge/live payment. |

## Current-baseline validation note — 2026-11-21
- PR #119 attendance, #120 gate-pass tenant isolation, #121 HR/payroll scope and #122 Owner impersonation each passed all five exact-head ERP CI jobs. They remain draft and require functional review.
- PR #123 online payment concurrency passed all five exact-head ERP CI jobs in run `44875663835`.


| ADMISSION-001 | Admissions / lead conversion and family linking | Convert admitted leads into actual student accounts and enrolments transactionally/idempotently; permit cross-institution family links only through a single verified parent mobile match. | Draft PR #126, head `522f4c76e69aaed0720d22cae7b2d4739436bf85`, rebuilt from historical #115 on current integration. Five files changed: AdmissionsController, conversion migration, admission detail view, Tagore routes and admissions feature tests. | Verify admitted-only conversion, institution/class/year scope, unique lead conversion, no duplicate student/enrolment on retry, one-time credential flash, verified mobile, and rejection of unverified/ambiguous matches. | First exact-head ERP CI run `44877363759` passed all five jobs. Added an explicit duplicate verified-parent-mobile rejection test on new head `a4e752ecbd81e82c38aa4cf86cde205dd0c6e77e`; fresh exact-head CI is required. Draft only; no merge, deployment or live data. |


| DIARY-001 | Academics / Daily Diary and Olympiad | Enforce class/family-scoped diary visibility and writes; preserve parent/school field ownership; write append-only field-level audit records; deterministically resolve latest student grade for Olympiad. | Draft PR #127, head `5cb8847b7903ddb1ba18a7c5d5bcc7c9346cfb16`; six files rebuilt from historical PR #100 on current integration. | Verify Teacher current class assignment, Coordinator exact standard-link scope, Parent linked-child scope, transactional audit history, unchanged-field no-op, visible audit timeline and Olympiad grade tie-break. | Exact-head ERP CI run `44877552307`: frontend passed; backend, browser E2E and production Docker smoke in progress. No merge, deployment or live data. |

| ADM-LEGACY-001 | Legacy admissions / school isolation | Prevent cross-school admission disclosure or mutation, constrain Draft/Pending filters to school and academic year, constrain section choices to school, and remove debug exception dumps. | Draft PR #128, head `1f1c2e9010e84a098adf698e1810690851b0cc7b`; `app/Http/Controllers/Admin/AdmissionController.php`. Rebuilt historical #114 directly from current integration; source branch 3 ahead / 49 behind, affected file identical to merge base. | Add deterministic multi-school feature tests for list/show/edit/update/delete and status/year filtering; verify school-scoped sections and generic 500 responses with no exception leakage. | ERP CI run `44878442906` in progress. No dedicated tests yet; explicitly not accepted until added and passed. |


## Current-baseline reconciliation — 2026-11-21 (PRs #129–#132)

- Integration branch was verified at `af308809e43e6e36b3b5cf8dc62752719379a330`; PRs #129–#132 were opened against this exact commit.
- **ADM-LEGACY-002:** PR #128 was closed unmerged after its regression-test path needed replacement. Active replacement is PR #131, branch `work/legacy-admission-isolation-current-baseline-v2`, head `e9aa07f7435b1966285b4b60a4f4944d5fc2ec36`. Exact-head ERP CI run `44883714444` passed all five jobs. Review synthetic second-school HTTP fixture and confirm actual route behavior before acceptance. Do not use #128 as a release candidate.
- **CBT-001:** PR #130, branch `work/cbt-engine-current-baseline`, head `c9088d8805e1af44a3bae08e47d635407db2589b`, dedicated JEE/NEET CBT engine. Exact-head ERP CI run `44883870085` passed backend, frontend, browser E2E, production Docker smoke and release acceptance gate. Review question-bank lifecycle, exam configuration, mock player, scoring/rank/percentile and analytics before marking complete.
- **BACKUP-002:** PR #129, branch `work/backup-integrity-current-baseline`, head `761e70b84273cb1d61948d5460b7f9f660f60009`. ERP CI run `44884350353` and Windows Pilot Package run `44884350403` were still in progress at this update; backend/frontend passed, browser E2E and Docker smoke were still running. No backup/restore operational acceptance yet; disposable restore proof is required and must not touch production.
- **OWNER-OPS-001:** PR #132, branch `work/owner-assurance-hostel-current-baseline`, head `e7f328f532c8ebc68bdcf2a0298bd41ab3695332`. ERP CI run `44884210186` was still in progress; backend/frontend/Docker smoke passed, browser E2E still running. Review Owner-only visibility, institution-wide rollups, audit evidence and hostel scope before acceptance.
- #119–#127 exact-head ERP CI runs are recorded green in the execution board. They remain draft candidates and are not merged or production accepted.
- No merge, deployment, live data, live payment gateway, hardware activation, or production backup/restore was performed.


## Validation refresh — 2026-11-21 (CBT scope and Cashier)

- **CBT-SEC-001:** PR #130 management scope originally filtered active role assignments by status but did not enforce effective start/end dates when collecting institution IDs. Corrected `CbtManagementController::scope` on head `13a143aadf847a0d9ae7c24c8255200d21c3cffe`; added a scope regression test. The first follow-up CI run `44884647823` correctly failed because the new assertion counted qualified and unqualified SQL predicates incorrectly. Assertion was corrected on head `a099fab90f12349c877ce8d5281395af1961b04d`; fresh ERP CI run `44884930247` is in progress. Do not accept #130 on the earlier green head until the latest head passes.
- **CASHIER-001:** Draft PR #133, head `d2af80013d5a670c450182e19a0efa2a5b609e03`, adds CASHIER to Tagore prototype role/permission seeders and demo account seed; allows institution-scoped accounts/fee statements/offline collection only; denies payment reconciliation, fee-master editing and fee-editor access; enforces effective assignment dates in FeeController; adds role-matrix and cross-institution authorization tests. Exact-head ERP CI run `44884866775` and Windows Pilot Package run `44884866727` are in progress. No acceptance claim until both relevant workflows pass.
- PR #129 exact-head ERP CI `44884350353` passed all five jobs and Windows Pilot Package `44884350403` passed. Disposable MySQL restore and isolated application restore remain release gates.
- PR #132 exact-head ERP CI `44884210186` passed all five jobs. It remains foundation-only until Owner/hostel role journeys are functionally accepted.
- PR #133 does not resolve AUD-002 Teacher/Coordinator fee visibility by class/grade, nor the cashier admissions/promotions workflow. Keep those gaps open.


## Finance / CBT acceptance update — 2026-11-21

- **CBT-002 scope correction:** PR #130 includes section assignment, per-question marks, negative marks, duration and exam scheduling, but code review confirms there is no randomisation implementation in `CbtManagementController`. Keep randomisation open in the feature register; do not mark full paper blueprint complete. Latest exact-head ERP CI run `44884930247` passed all five jobs on `a099fab90f12349c877ce8d5281395af1961b04d`.
- **CASHIER-001:** PR #133 head `a80c67b4a07f6f8566e6c8b5f86e4c9a3cdfb41b`, ERP CI `44885119214` passed all five jobs; Windows Pilot Package `44885119208` passed. Dedicated cashier permission matrix, institution scoping and negative reconciliation/fee-master tests are green. Owner assignment UX and full operational workflow review remain pending.
- **AUD-002:** PR #134 current head `a549c751dac076b5d41a4e8f06f774b217271c94` implements teacher class assignment and coordinator exact standard-link scope across fee statements and accounts aggregates, and removes sibling/reconciliation details from academic viewer screens. ERP CI `44885549135` and Windows Pilot Package `44885549143` are in progress. It includes #133 as its prerequisite; no merge or deployment.
- **OWN-001:** PR #132 passed exact-head ERP CI `44884210186`; current code is an operational audit foundation, not full department task/KPI workflow acceptance.
- **PLT-001:** PR #129 passed exact-head ERP CI `44884350353` and Windows Pilot Package `44884350403`; disposable MySQL restore and isolated staging application restore are still required.


## Integration execution update — 2026-11-21 (current acceptance blockers)

- PR #131 legacy admission institution isolation merged to `integration/erp-7day` as `1b8690ffae6dbaa0b085b9d8d6bf6a1d10ea1cd4`. Exact-head ERP CI run `44886078112` passed all five jobs, including synthetic second-school list/show/edit/update/delete regression coverage.
- PR #126 admitted-lead conversion and verified family linking merged as `acd2718037fd6813bf5b8e4efe907ae38d7cb141`. Exact-head ERP CI run `44886653601` passed all five jobs, including conversion idempotency and verified/unverified/ambiguous parent-mobile tests.
- Full integration acceptance on pre-#126 integration commit `1b8690ffae6dbaa0b085b9d8d6bf6a1d10ea1cd4` failed closed in run `44892596729`. PHP syntax, migrations, backend tests, frontend build, app health and mutating admin workflows passed. Browser acceptance exposed two unresolved failures: (1) institution E2E expected a disabled “No unlinked GegoK12 schools available” option even though additional unlinked schools remained; the test should assert the just-linked school ID is no longer selectable and explicitly test duplicate-link rejection; (2) mobile Operations search left the count at “2 of 2 records” after filtering a unique newly-created item, while the desktop path passed. Investigate mobile input/filter event behavior; do not weaken the assertion.
- New full integration acceptance was triggered by merge #126 on commit `acd2718037fd6813bf5b8e4efe907ae38d7cb141`, run `44893340615`; result pending at this update.
- Production deployment remains blocked. These are synthetic CI defects only; no live data, migration or production deployment occurred.


## Cashier and academic fee-scope update — 2026-11-21

- PR #133 Cashier role/least-privilege institution fee operations merged as `c0193d062f830f74e6be2dc39dd7d95524baf79c`. Exact-head ERP CI run `44885119214` passed backend, frontend, browser E2E, production Docker smoke and acceptance gate; Windows Pilot Package run `44885119208` passed.
- Historical PR #134 branch `work/teacher-coordinator-fee-class-scope` passed its own five CI jobs in run `44887064281`, but was based on the older `a6c4d12` integration baseline. Rebuilt the academic fee-scope slice on current integration as draft PR #138, head `636bada97bd8aef17d29efc85cd4e85c13b80fed`.
- Security review clarification: `TEACHER` is included in `authorizeAccounts` for read-only accounts workspace visibility, but is not included in `authorizePaymentRecording`. Payment recording remains restricted to OWNER, PRINCIPAL, ACCOUNTS and CASHIER. PR #138 adds explicit negative tests proving Teacher and Coordinator cannot record offline payments or create payment rows. Do not treat the earlier preliminary warning as a confirmed vulnerability.
- PR #138 exact-head ERP CI run `44893659594` is in progress. Current integration Full Acceptance on `c0193d06` is run `44893549433`, also in progress. Do not merge #138 until exact-head CI and integration Full Acceptance are green.
- The earlier integration Full Acceptance run `44892596729` failed browser acceptance on the institution test's assumption that all unlinked schools were exhausted and the mobile Operations search count remaining `2 of 2`; both remain release blockers until the newer run confirms resolution or the failures are fixed.


## Academic fee visibility merge — 2026-11-21

- PR #138 (`work/teacher-coordinator-fee-class-scope-current-integration`) passed exact-head ERP CI run `44893659594` across backend, frontend, browser E2E, production Docker smoke and release-blocking acceptance gate.
- Merged to `integration/erp-7day` as `16d90ae8a955cd52af266c6e7c4ce81ac035ff35` (squash merge).
- Full integration acceptance run `44893549433` passed on pre-merge integration head `c0193d062f830f74e6be2dc39dd7d95524baf79c`; post-#138 combined-head acceptance must still be verified.
- Security invariant: Teacher and Coordinator are permitted read-only fee visibility within their assigned class/grade scope. `authorizePaymentRecording` remains limited to OWNER, PRINCIPAL, ACCOUNTS and CASHIER. Regression tests confirm Teacher/Coordinator payment POST attempts return 403 and do not create ledger rows.
- Historical PR #134 is superseded; PR #138 is the current-baseline rebuild and is now merged. Do not reopen or merge #134.
- Next promotion work: PR #124 has passing exact-head CI on its earlier base, but that base is stale and GitHub currently reports it non-mergeable. Rebuild/reconcile on current integration before merge; do not bypass exact-head CI or acceptance.


## Student promotion current-baseline rebuild — 2026-11-21

- Historical PR #124 is not mergeable against the current integration branch and must not be merged directly.
- Rebuilt the feature as draft PR #139 on `work/student-promotion-lifecycle-post-fee-scope`, head `9aae313dcad8612a2dd70c632adfe0260952bdea`, based on integration `007fd52365d6407d0afa1999ccc254a63bfcbb0f`.
- Ported the promotion controller, lifecycle-event migration, view, feature tests and browser test; updated the current dashboard and routes without replacing their merged admissions/fee changes; preserved the latest seeder changes while applying the global Owner-role correction.
- Exact-head ERP CI run `44894555904` is running and Windows Pilot Package run `44894555910` is queued. Full integration acceptance after #138 merge is run `44894435609`, still in progress. Keep PR #139 draft until exact-head CI and integration acceptance pass.
- No live student records, production migration or deployment.


## Student promotion and Daily Diary update — 2026-11-21

- PR #139 student promotion passed exact-head ERP CI run `44894555904` and Windows Pilot Package run `44894555910`; merged into `integration/erp-7day` as `6489cfe71fa49a236a23e0a99c106c8dd89b06f7`.
- Full integration acceptance on the combined #139 head is run `44895722308`; still in progress at this record.
- Historical PR #127 is superseded: its branch is 13 commits behind and 11 ahead of the latest integration. Current-baseline rebuild is draft PR #140, head `6eb4667b160b8ed2e3425afb5931541069dbdd9e`, based on `6489cfe71fa49a236a23e0a99c106c8dd89b06f7`.
- PR #140 exact-head ERP CI run `44895851964` is running. Its audit migration and tests must pass before merge; keep the PR draft until all gates pass.
- Partial fee allocation PR #125 contains online payment concurrency protection from closed/superseded PR #123. Rebuild this combined finance scope on latest integration before merge; no live gateway or real payment testing.


## Partial fee allocation rebuild — 2026-11-21

- Draft PR #141 (`work/parent-partial-fee-allocation-current-integration`) is the current-integration rebuild of historical PR #125 plus the superseded PR #123 checkout reservation/concurrency protections. Head `ec319cb53b50ac96b09824aaf69a51beea7be264`, base `07004728ff8dcb1f95775749eccaa1251901cd35`.
- Exact-head ERP CI run `44896021179` is queued. No live payment gateway, live transaction or production payment data used; gateway tests are mocked.
- PR #140 Daily Diary rebuild exact-head run `44895851964` has backend/frontend passed, browser E2E and production Docker smoke still in progress at this update.
- Full integration acceptance after student promotion merge is run `44895722308`, still in progress. Neither draft PR may merge until its exact-head CI and the post-merge integration acceptance are successful.


## Superseded draft closure — 2026-11-21

- Closed historical drafts #124, #127, #125 and #134 so stale branches cannot be merged accidentally. Active replacements: #139 (student promotion, merged), #140 (Daily Diary current-integration rebuild), #141 (partial fee allocation plus checkout concurrency current-integration rebuild), and #138 (academic fee scope, merged).
- No production release or live-data migration. The current full integration acceptance run `44895722308` and exact-head ERP CI runs `44895851964` / `44896021179` remain release blockers until they complete successfully.


## Diary merge, partial fee and backup validation — 2026-11-22

- PR #140 Daily Diary and field-level audit work merged as `8ebee123a82f29bcad927f8eb9b0d7d4683f58dd` after exact-head CI passed. The follow-on integration acceptance run `44897369398` failed; do not describe this baseline as release-ready.
- Failure evidence: the desktop Operations live-search acceptance expected `1 of` after searching a newly created unique inventory record, but the count remained `2 of 2 records`. Mobile passed. The correction is PR #143, branch `fix/operations-live-search-desktop-acceptance`, head `1a3b0fb677b751e753396c41bd1062b1eba1ca79`; exact-head CI is pending. The fix removes inline event dependence, reads live controls, and searches rendered record cells only. No assertion was weakened.
- PR #141 parent partial fee allocation + checkout concurrency passed exact-head ERP CI run `44896021179`; it remains unmerged pending a green integration acceptance on a corrected combined head.
- PR #142 encrypted backup and disposable restore verification passed ERP CI run `44897560584` and Windows Pilot Package run `44897560590`; it remains draft/unmerged pending integration gate and operational review. No live backup or restore was run.
- Institution duplicate-school-linkage browser scenario was reported flaky in the failing acceptance log. Retain it as an investigation item; do not skip or weaken the test.
- No production data, live payment gateway, production migration or deployment used.


## Operations search root-cause correction — 2026-11-23

- PR #143 merged after its exact-head CI passed, but full integration acceptance run `45107100304` failed again on desktop and mobile Operations live search. The institution duplicate-school-linkage scenario now passed.
- Failure evidence: desktop search count remained `2 of 2`; mobile remained `3 of 3` after the test created a new inventory row and searched for it.
- Root cause identified in the view: the script cached the record-row NodeList at initial page load. The create workflow inserts/replaces rows later, so filtering/counting continued to use stale rows. The previous fix updated input listeners but did not refresh the row collection.
- New correction branch `fix/operations-search-dynamic-dom-rows` resolves the current search/status controls, rows, count and empty state on every filter pass and uses delegated document listeners so replacement controls remain functional. No acceptance assertion is weakened.
- Exact-head CI and a new full integration acceptance run are required before release or finance/backup merges.


## Daily Diary full-acceptance correction — 2026-11-23

- After PR #145 merged, full integration acceptance run `45108815439` confirmed the Operations search failure was cleared; 91 browser tests passed, one Daily Diary desktop test failed.
- Failure: the E2E test saved school homework using the first diary card in the Owner's multi-student view, then asserted against the parent's linked-child view. The Owner view is not guaranteed to put that same student first; the test could write a different child's diary, leaving the parent view with an older coordinator entry.
- New branch `fix/daily-diary-acceptance-student-target` captures the linked student's card ID from the Parent view, targets that exact student card in the Owner view, and asserts the updated homework/feedback in that same Parent card. No application authorization or feature assertions were relaxed.
- Exact-head ERP CI and a new full integration acceptance run are required. PR #141/#142 remain blocked until full acceptance is green.
