# Tagore ERP Product Modernisation — Baseline Audit

**Baseline:** `main` at `a5806356ad317c8126845a6512b80575376a7f63`  
**Purpose:** establish an evidence-led product improvement programme. This is a source-code baseline, not a claim that every workflow has passed visual or live acceptance.

## Executive assessment

Tagore ERP is not a blank product. The repository contains a large legacy school ERP surface plus a newer Tagore-branded operational layer. The next phase should consolidate and complete the newer experience, preserve working legacy capabilities, and remove inconsistencies between screens. Avoid adding duplicate modules until the route-to-view-to-service-to-test chain is understood.

### Evidence observed in the repository

| Area | Evidence in source | Baseline conclusion |
|---|---|---|
| Role-based workspaces | Tagore role matrix includes Owner, Principal, Coordinator, Teacher, Parent, Student, Accounts and Fee Editor; role access tests assert representative permissions | A role model exists; full route-level permission coverage still needs audit |
| Admissions CRM | Dedicated admissions routes, controller and list/create/detail views; lead assignment, activity and family-link routes | Core CRM surface exists; conversion workflow, reminders and mobile UX need end-to-end verification |
| Fees and finance | Fee accounts/manage/editor/import/reconciliation/receipt/wallet/vault views; fee services, receipt mailer, audit service and payment services | Strong specialised foundation; verify ledger invariants, refunds, receipt delivery, parent visibility and year-end controls |
| Academics and examinations | Academic structure, examination controller, marks/results views and dedicated migrations/tests | Core academic structures exist; assess complete teacher-to-result-publish workflow |
| Daily diary and learning | Daily diary controller/view, learning controller, homework/lesson-plan APIs and acceptance tests | Feature surface exists; verify parent/teacher workflow and attachments/feedback |
| HR and staff | Staff leave/self routes, payroll controller/services and payroll views | Partial HR/payroll surface; verify attendance-to-payroll and approvals |
| Hostel, transport, gate/security | Operations data model includes hostel/transport/visitor/gatepass; transport/security controllers and views exist | Data and UI surfaces exist; validate real operational lifecycle, not just record CRUD |
| Inventory and procurement | Inventory controller/service and operations screens | Foundation exists; validate purchase orders, vendors, stock movements, approvals and valuation |
| Communication and reporting | Dedicated communication/report controllers and views; notification/communication services | Surface exists; verify delivery channels, retries, consent, templates and scheduled reports |
| Mobile and offline | API controllers for parent/teacher/student, sync controller/service, offline-sync migration and Android/windows workflows | Technical foundation exists; device-level sync, conflict handling and release readiness require proof |
| Management visibility | Dashboard, performance and reporting controllers/views | Dashboards exist; evaluate actionable KPIs, drill-downs and cross-institution comparisons |

## Key product finding: the UI is not yet one coherent system

The published dashboard stylesheet currently defines a light, white/blue palette, while the inner-page polish stylesheet defines a separate charcoal/dark palette with green focus accents. The live owner dashboard tab also exposes a well-structured command-centre layout (KPIs, needs-attention work, quick actions and institution snapshot). The next design pass should preserve this useful information architecture while bringing the dashboard and inner screens onto the agreed Tagore charcoal visual direction with consistent green/red status semantics.

The newer dashboard uses a dedicated `tagore-erp.css` design system and a standalone shell. Other screens vary in implementation:

- The admissions list contains a separate inline light-theme style block and standalone HTML shell, while also loading Tagore stylesheets.
- Fee accounts has its own page-level styles and navigation markup.
- Operations extends the shared Laravel layout, then defines another Tagore shell and page-level responsive rules.
- The parent dashboard uses the Tagore stylesheet but has a distinct parent-oriented navigation and page composition.
- The base layout conditionally loads Tagore styles and contains global responsive overrides for legacy tables and forms.
- `resources/css/app.scss` contains only Tailwind directives; the Tagore-specific styling is being served from public CSS assets rather than a clearly centralised component source in that file.

This is a strong candidate for a UI-system consolidation project. It is not yet evidence that every page is visually broken; screenshots and browser-based review are needed to grade each page.

## Benchmark principles

Competitor feature claims should be treated as vendor-published capability descriptions, not independent proof of quality. Borrow patterns only after mapping them to Tagore's workflows.

| Benchmark | Patterns worth evaluating | Tagore application |
|---|---|---|
| [Entab One](https://www.entab.in/entab-one.html) | Broad school operations, management reporting, parent/staff communication and integrated modules | Cross-campus operations cockpit, consolidated reports, consistent role experiences |
| [uTab](https://utab.utab.in/school-erp) | Modern school workflow presentation, parent/teacher engagement and helpdesk-style support | Clear task inbox, support requests, mobile-first parent and teacher journeys |
| [MyClassboard](https://www.myclassboard.com/) | School-chain operations, fee/transport/communication workflows | Institution-level controls with group-wide oversight and comparable KPIs |
| [Teachmint](https://www.teachmint.com/) | Teacher-centred classroom and learning workflows | Low-friction attendance, homework, lesson plans, assessments and class communication |
| [TCS iON Campus Management](https://g01.tcsion.com/dotcom/iONHelp/Campus%20Management/index.html) | End-to-end campus administration including admissions, examinations, fees, hostel, transport and library | Reusable student lifecycle across schools, coaching and college |
| [Mastersoft](https://www.mastersoft.ai/product/college-management-software) | College student lifecycle, examinations, fees, scholarships and student services | Strengthen Tagore PG College workflows and reporting |

## Prioritised improvement backlog

### P0 — coherence, trust and daily usability

1. **Unified design system:** one shared shell, navigation, typography, spacing, page headers, cards, tables, forms, dialogs, status badges and responsive behaviour for all Tagore pages.
2. **Page inventory and visual QA:** catalogue every reachable route by role; capture desktop and mobile screenshots; grade layout consistency, readability, overflow, empty states, loading, errors and action feedback.
3. **Permission audit:** test every sensitive route and mutation against representative role accounts; enforce permissions server-side, not only by hiding navigation links.
4. **Finance integrity:** prove fee assessment, concessions, collection, receipt, reversal/refund, reconciliation, immutable audit, opening balance and year-end archive behaviour. Parent-facing views must never expose internal audit/transaction history.
5. **Family/student 360:** verify multiple children per parent, correct family linking, scoped access, attendance/results/diary/fees and admission conversion.
6. **Global interaction quality:** searchable/filterable/paginated lists, clear validation, safe destructive confirmations, bulk actions where appropriate, export controls and useful empty states.

### P1 — operational depth

1. Admissions lead pipeline with owner, next action, follow-up reminders, source/campaign attribution and conversion reporting.
2. Teacher daily workflow: timetable, attendance, diary/homework, marks, leave and parent communication.
3. Academic lifecycle: timetable, lesson plans, exams, marks moderation, report cards, promotion and academic-year rollover.
4. Hostel lifecycle: bed allocation, check-in/out, attendance, mess/health records, visitor and outpass approvals.
5. Transport lifecycle: route/stop/vehicle/driver assignment, student mapping, attendance, GPS events and parent alerts.
6. HR/payroll: staff master, attendance, leave approvals, payroll calculation, payslips and statutory/reporting needs.
7. College workflows: admissions/enrolment, course/semester, examinations, results, fees, library, hostel and student services.
8. Communication centre: audience segmentation, templates, delivery status, retries, scheduling, consent and message history.

### P2 — competitive advantage

1. Group-wide analytics across institutions and business units.
2. Workflow automation for admissions follow-ups, fee reminders, approvals and staff task escalation.
3. AI-assisted operational summaries and exception detection with human approval for consequential actions.
4. Offline-first workflows with explicit sync status, conflict resolution and auditable retries.
5. Native Android parent/teacher experience and Windows desktop operations, released from the same tested API contracts.

## Required audit method

For each module, record:

- routes and HTTP methods;
- controller actions and services;
- view files and shared layout dependencies;
- database tables, constraints and audit events;
- role permissions for view/create/update/delete/export/approve;
- automated test coverage and uncovered paths;
- desktop/mobile visual evidence;
- workflow acceptance from start to finish;
- defects, severity, owner and acceptance criteria.

Use synthetic/disposable test records only. One representative account per distinct role/permission profile is sufficient for interactive role acceptance; broader data integrity should be tested automatically. Do not require a census of every staff or student account.

## Delivery sequence

1. Finish route/view/controller/service/permission inventory and attach page screenshots.
2. Publish a module-by-module gap matrix with verified / partial / missing / unverified statuses.
3. Consolidate the UI shell and shared components without changing business rules.
4. Select one vertical slice at a time (recommended first: admissions CRM, then teacher/parent daily workflow, then fees).
5. Add workflow tests and representative-role desktop/mobile acceptance before merging.
6. Release only from a tested branch with backup-first deployment and post-deploy smoke checks.

## What this baseline does not claim

- It does not claim every listed module is complete or production-ready.
- It does not claim visual acceptance has been performed; source inspection is not a substitute for browser screenshots.
- It does not claim actual live role acceptance has passed.
- It does not recommend replacing existing functionality merely to imitate a competitor.

## External benchmark references

- Entab One: https://www.entab.in/entab-one.html
- uTab: https://utab.utab.in/school-erp
- MyClassboard: https://www.myclassboard.com/
- Teachmint: https://www.teachmint.com/
- TCS iON Campus Management: https://g01.tcsion.com/dotcom/iONHelp/Campus%20Management/index.html
- Mastersoft College Management: https://www.mastersoft.ai/product/college-management-software
