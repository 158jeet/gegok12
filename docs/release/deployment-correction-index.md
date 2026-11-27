# Tagore ERP Deployment Correction Index

Purpose: preserve every verified defect, correction, test result, and deployment-only action so the final release is reproducible.

## Operating rules
- Active checkout: G:\Projects\TagoreERP.
- Preserve all modified and untracked files. Never run git reset, git clean, blanket checkout, or overwrite unrelated work.
- No live student, parent, employee, fee, or financial data before full acceptance.
- No production deployment until role workflows, regression tests, security checks, backup/restore, and browser acceptance pass.
- Record each correction with evidence and the exact commit/patch that carries it to release.

## Correction register

| ID | Date | Area | Defect / gap | Change made | Verification | Release status |
|---|---|---|---|---|---|---|
| COR-0001 | 2026-11-23 | Runner | PowerShell child-process exit status was unreliable | Private runner helper now uses a wrapper-written exit marker | G-drive source map request 20261123-07 and deep inventory request 20261123-08 both exit 0 | Verified in private operations repository |
| COR-0002 | 2026-11-23 | Source reconciliation | G-drive checkout is behind integration line and contains extensive local work | Inventory only; no reset, checkout, clean, or merge performed | Deep inventory reports 283 status entries; preserve and reconcile deliberately | Open |

## Required entry format
For each correction record unique ID, date, module, observed defect, root cause, files changed, migration/data impact, test command/result, evidence run, commit SHA, and propagation status.