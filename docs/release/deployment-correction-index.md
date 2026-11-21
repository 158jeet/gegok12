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
| ATT-001 | Staff attendance reliability | Ensure `createStaffAttendance` rolls back and rethrows `Throwable` failures instead of swallowing them; controller can then return a safe error and log the exception. | PR #110 branch `work/staff-attendance-error-handling`, commit `5066d8a5fa202f60a68bc75b3e92c780966e53b0`; file `app/Traits/AcademicProcess.php`. | Reconcile patch onto current integration baseline and rerun focused staff-attendance failure tests plus integration acceptance. | Feature-branch ERP CI, Prototype and Full Acceptance passed; integration reconciliation pending. |
| SEC-002 | Gate-pass tenant isolation | Make cross-institution gate-pass regression deterministic by creating a second synthetic school/institution when the default CI seed has only one; remove the conditional skip. | PR #111 branch `work/gatepass-institution-validation`, commit `3aacf9bf78718cc1ee788f5fb9391aad77cb3d8d`; file `tests/Feature/TagoreGatepassInstitutionScopeTest.php`. | Run focused gate-pass scope test and full Laravel acceptance; confirm no skip and no gate-pass row for a foreign student. | Committed on feature branch; validation pending. |
| SEC-003 | Impersonation exit safety | After stopping impersonation, redirect according to the restored original actor, not the impersonated user's role; cover Owner-to-teacher exit with a regression test. | PR #105 branch `work/ws-owner-security-hardening`, commits `ab64fac13e588fa9ffae17888255c78383a3ec8c` and `140ef05384fcd0c591868a8de26b8025564394f2`; controller and `AuthorizationGateRegressionTest.php`. | Verify Owner returns to the Owner/admin dashboard and authentication is restored; run focused authorization tests and full acceptance. | Committed on feature branch; new validation pending. |

## Known release gates and unresolved reconciliation reminders

1. **PR #118:** The two syntax corrections above are on its feature branch, not yet confirmed in `integration/erp-7day`. Do not treat the Owner command-centre/hostel work as accepted until focused tests and full integration acceptance pass.
2. **Workflow inheritance:** Pushes to older feature branches (including PR #118 and other active PR branches) can trigger ERP CI, TagoreK12 Prototype, and Full Acceptance because those branches still have the previous workflow files. Reconcile workflow definitions on active feature branches or ensure the final integration branch's definitions govern the release path.
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
