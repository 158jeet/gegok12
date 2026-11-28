# Tagore ERP Cashier — Market Gap Review and Delivery Roadmap

**Reviewed:** 28 November 2026
**Reference product:** Multi School ERP v3.8 live demo and public product documentation
**Scope:** Tagore institution-scoped CASHIER role; not the OWNER / ACCOUNTS finance-control role.

## Executive assessment

The current Tagore cashier role is intentionally least-privilege, but the experience is too thin for a busy multi-institution school fee counter. It can record offline payments and see balances / recent transactions, but lacks a dedicated operational cockpit, fast student lookup, a usable collection register, and cashier-scoped reporting.

The benchmark should be treated as workflow inspiration, not copied as a product wholesale. Finance setup, fee master changes, bank settlement reconciliation, concessions, refunds and ledger corrections must remain separately authorised.

## Reference workflow capabilities observed

The wider Tagore benchmark set remains Fedena, Entab CampusCare 10X, MyClassboard, iCloudEMS and the specialist learning / assessment platforms DigiERP, WiZiAI, Examezy, JEENEET and Eklavvya. The latter group is relevant to the wider ERP and coaching/exam engine, while the finance comparison is strongest against the first four plus the live Multi School ERP demo.

| Benchmark | Publicly documented finance strengths | What Tagore should adopt |
|---|---|---|
| Multi School ERP live demo | Fee dashboard, collection, due fees, transactions, challans, fee groups/types, discounts, due slips, audit, imports, late fines, bank accounts, income/expense | Fast counter workflow, due/receipt search, challans and separate cashier vs finance workspaces |
| Fedena | Fee structures and master particulars, advance fee, defaulter alerts, SMS/email reminders, daily and payment-mode reports, controlled refunds, receipt templates, audit | Advance balances with ledger controls, reminder history, detailed receipts and permission-separated refunds |
| Entab CampusCare 10X | Day-wise, instalment-wise and payment-mode trends, defaulter insight, online payment status, cheque bounce, branch-level access, fee group shifting | Branch-scoped reports, cheque clearance/bounce tracking and payment status visibility |
| MyClassboard | SMS/email/WhatsApp/voice/app due alerts, follow-up history, multiple payment modes, fines, concession approval, scholarships, Tally integration | Multi-channel reminders, concession workflow, follow-up history and accounting export |
| iCloudEMS | Fee collection, instalment plans, reconciliation, automated reminders and real-time role dashboards | Connect fee collection to reconciliation and role-specific finance analytics |

Public source links: [Fedena fee management](https://fedena.com/feature-tour/school-fees-management-system), [Fedena finance privileges](https://support.fedena.com/support/solutions/articles/214049-what-are-the-different-privileges-available-in-fedena-), [Entab CampusCare release notes](https://releasenotes.entab.info/), [MyClassboard fee management](https://www.myclassboard.com/finance/fee-management/), [iCloudEMS modules](https://icloudems.com/products/).


- Fee dashboard and quick collection / rapid collection.
- Student lookup by name or admission number, installment dues and partial payments.
- Cash, Card / POS, UPI / QR, Cheque and Bank Transfer.
- Payment date, transaction reference, bank account and counter receipt reference.
- Printable / downloadable receipts and a shareable receipt link.
- Due-fee search, class-wise collection, daily collection by payment mode, student ledger, defaulters and monthly summaries.
- Fee groups / types / assignments, discounts, late-fine rules, due slips, fee import and fee data audit.
- Separate income, expense and bank-account workspaces.
- Payment reversal rather than editing/deleting a posted receipt; reversal is restricted to an authorised finance role.

## Current Tagore gaps and ownership

| Capability | Current state | Recommended owner |
|---|---|---|
| Institution-scoped fee collection | Exists | Cashier |
| Partial payments | Exists; automatic oldest-due allocation | Cashier |
| Family / sibling balance view | Exists in Accounts | Cashier (read-only) |
| Printable PDF receipt | Exists | Cashier |
| Cashier-specific command centre | Added in this delivery | Cashier |
| Today / month collection KPIs and mode mix | Added in this delivery | Cashier |
| Student due queue with search | Added in this delivery | Cashier |
| Searchable, date- and mode-filtered collection register | Added in this delivery | Cashier |
| Institution-scoped CSV collection export | Added in this delivery, permission-gated | Cashier |
| UPI and card/POS payment modes | Added in this delivery | Cashier |
| Fee masters, fee types and fee groups | Existing; restricted | OWNER / PRINCIPAL / ACCOUNTS |
| Assign approved fee plans to class/section and generate missing demands | Added in this delivery; institution-scoped | Cashier |
| Send an audited fee-due reminder to linked parent app accounts | Added in this delivery; push channel only | Cashier |
| Fee discounts / concessions approval | Existing concession ledger; cashier cannot approve | OWNER / PRINCIPAL / ACCOUNTS |
| Online gateway settlement reconciliation | Exists; restricted | OWNER / ACCOUNTS |
| Refund / receipt reversal with reason and approval | Not yet delivered as a complete workflow | OWNER / ACCOUNTS approval |
| Cash drawer / shift open and close | Gap | Cashier opens; OWNER / ACCOUNTS reviews variance |
| Expected-vs-actual cash, denomination count and variance note | Gap | Cashier + finance reviewer |
| Backdated payment with correct financial-date receipt numbering | Gap; must be implemented with safe sequence locking | OWNER / ACCOUNTS policy; Cashier if permitted |
| Bank account selection and bank deposit slip tracking | Gap; no verified cashier-ready bank-account workflow | ACCOUNTS configures; Cashier selects |
| Installment-level allocation at counter | Current automatic allocation exists; explicit installment selection is limited | Cashier |
| Due slip / fee challan generation | Gap in cashier UI | Cashier |
| WhatsApp receipt sharing / resend delivery status | Email/PDF delivery exists; WhatsApp delivery is not yet cashier-complete | Cashier |
| Duplicate receipt marking / reprint audit | Gap | Cashier |
| Cashier activity / device login audit view | Audit events exist; cashier-facing review is limited | OWNER / ACCOUNTS |
| Counter queue / rapid next-student workflow | Added search queue; keyboard-first rapid mode still to improve | Cashier |

## Delivery in the current branch

1. Dedicated /tagore/cashier workspace.
2. Daily and month-to-date collection KPIs.
3. Today’s payment-mode breakdown.
4. Searchable outstanding-student counter queue.
5. Date-range, payment-mode and free-text transaction filtering.
6. Receipt download links in the collection register.
7. Permission-gated, institution-scoped CSV export.
8. Cash, UPI, Card/POS, Cheque, Bank Transfer and Other payment modes.
9. Cashier-specific navigation and dashboard entry point.
10. Assign approved fee structures to a class/section and safely generate missing demands without creating or editing fee masters.
11. Send a fee-due app push reminder to linked parent accounts and write an audit event.
12. Regression coverage for workspace access, scoped exports, fee assignment, reminders, least-privilege access and existing finance restrictions.

## Next delivery phases

### P1 — Cash control, family checkout and reconciliation
- Parent/guardian lookup across linked siblings and one counter checkout for multiple children.
- Split the collected amount across each child's specific fee obligations/installments; receipt must show every child allocation. No orphan credits or wallet-balance workarounds.
- Cashier shift open with opening float.
- Bind each offline payment to the cashier who recorded it.
- Expected cash = opening float + cash receipts during that shift - approved cash reversals.
- Close shift with denomination-wise actual cash, variance and mandatory explanation when non-zero.
- Immutable close record; owner/accounts review and approval. Never let a cashier rewrite a posted payment.

### P2 — Better counter collection
- Explicit installment selection and multi-installment allocation preview after family checkout is in place.
- Backdated payment date only after receipt numbering is made date-safe and concurrency-safe.
- Bank-account / deposit reference selection.
- Due slip / challan generation and receipt reprint audit.
- WhatsApp receipt delivery with delivery status and resend controls.
- Automated SMS/email reminders with delivery tracking and opt-out / retry controls; current cashier reminder sends an audited parent-app push only.

### P3 — Finance controls (not cashier privileges)
- Controlled refund / reversal workflow with reason, original receipt, approver and ledger reversal.
- Gateway settlement matching, mismatch queue and bank reconciliation.
- Concession / discount approval thresholds and full audit.
- Daily, class-wise, fee-head, defaulter and monthly finance reports.
- Income / expense and bank-account workspaces for ACCOUNTS, not general counter cashiers.

## Security and accounting rules

- Every cashier query and export must be restricted to active institution assignments.
- Cashier must not manage fee masters, edit demands, grant concessions, reconcile gateways, approve refunds or delete payments.
- Posted payments and receipts are immutable. Corrections must create a reversal / compensating entry and audit trail.
- Exports are narrow collection-register exports, not the broad report.export permission.
- No test, deployment or acceptance run may create a real fee payment on production. Use isolated synthetic fixtures only.
