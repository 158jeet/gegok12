# Tagore ERP — Authoritative Requirements Index

**Purpose:** Single navigation point for the locked product scope and implementation programme.  
**Research baseline:** 14 November 2026.  
**Execution mode:** Seven-day parallel sprint; synthetic data and CI only until a separately approved release.

## Scope lock

The product target is a secure, multi-institution operating platform for Tagore Public School (CBSE), Tagore Bal Niketan Senior Secondary School (RBSE Hindi/English), Tagore Smart School (RBSE Hindi/English K–5), Tagore Vedant Academy (pre-foundation, MNS and JEE/NEET), Tagore Hostel, Tagore PG College and authorised group-level services.

The requirements already researched and recorded in PR #72 are copied into this programme branch unchanged as the baseline. The additional approved requirements in `docs/INITIAL-REQUIREMENTS-AND-UX-REVIEW.md` are retained as a supplement. This index does not delete, narrow or silently reprioritise any approved requirement.

## Canonical requirement sources

| Source | Authority / purpose |
|---|---|
| `docs/research/tagore-erp/report.md` | Owner-supplied scope, institution model, benchmark summary and core outcomes |
| `docs/research/tagore-erp/expanded-market-feature-register.md` | Detailed market benchmark, workflows, feature-level acceptance criteria and work packages |
| `docs/research/tagore-erp/feature-gaps.csv` | Structured feature backlog with IDs, priority, roles, preliminary status and synthetic acceptance tests |
| `docs/research/tagore-erp/role_matrix.csv` | Intended role/action/data-scope matrix and prohibited actions |
| `docs/research/tagore-erp/roadmap.md` | Dependency-aware implementation and acceptance roadmap |
| `docs/research/tagore-erp/repository-evidence-audit.md` | Evidence-backed current-code findings and corrections |
| `docs/research/tagore-erp/route-and-authorization-inventory.md` | Route and authorization review |
| `docs/research/tagore-erp/runtime-route-manifest-audit.md` | Runtime route inventory and security/mutation findings |
| `docs/research/tagore-erp/authentication-hardening-implementation-plan.md` | Authentication and recovery requirements |
| `docs/INITIAL-REQUIREMENTS-AND-UX-REVIEW.md` | Additional approved requirements: versioned form studio, fieldwork/offline submissions, admissions campaigns, entrance-test delivery, discount approval and multi-institution leave |

## Authority and interpretation rules

1. The feature register is the complete discovery backlog; the role matrix defines intended access boundaries. The repository evidence audit determines whether a capability is actually implemented.
2. Preliminary statuses in the feature register are not completion claims. A feature is accepted only with current code evidence and passing synthetic end-to-end acceptance.
3. Where older market-register notes conflict with newer repository evidence, the newer evidence audit governs current implementation status; it does not erase the required product outcome.
4. No second top-level Admin/Super Admin role is introduced. The existing Owner remains the sole highest business authority. Technology Controller is a separately scoped technical capability only where explicitly authorised; it does not become another Owner.
5. Parent/guardian access is limited to verified linked children. Institution ledgers remain separate. Parent-facing screens do not expose internal transaction audit/history.
6. Cashier collection operations are not bank/gateway settlement administration. Teacher and Coordinator fee visibility is minimum, read-only and class/grade scoped.
7. No automatic student enrolment from an enquiry, no silent sibling payment allocation, no unaudited financial adjustment, and no unlogged change to submitted or published records.
8. Hardware/vendor integrations are not marked complete without documented protocols, security review and end-to-end tests.
9. Every requirement remains in the register until accepted or explicitly deferred by the Owner. P0/P1/P2 labels govern delivery order, not deletion.
10. All implementation and acceptance before production onboarding uses synthetic/disposable data. No production deployment, live database mutation, real payment, email/SMS or hardware activation is authorised by this index.

## Delivery traceability

Use the stable IDs in `feature-gaps.csv` as requirement IDs. Each implementation PR must state:
- requirement IDs addressed;
- evidence paths and affected roles;
- migration/API compatibility impact;
- automated tests and exact-head workflow results;
- known gaps and deferred acceptance cases.

The integration dashboard must count a requirement as complete only when the implementation is merged into the sprint integration branch and its acceptance evidence passes on the integrated SHA.

## Governance

This programme branch is stacked on the authentication-hardening candidate so the requirements and execution plan travel together. PR #72 remains the original research source PR; PR #78 is the consolidated programme/requirements PR. Keep both reviewable and draft until their dependencies and checks are verified. No production merge or deployment is implied.
