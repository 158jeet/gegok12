# Multi School ERP V3.8 vs Tagore ERP — Comparative Audit and Integrated Delivery Plan

**Audit date:** 2026-11-28  
**Tagore repository baseline:** `main` at `131e0b154554110b376f5a7d24148b5da4cf73f7`  
**Reference product:** ProjectWorlds Multi School ERP; public website, documentation portal, live demo and vendor videos  
**Status:** Discovery baseline and proposed implementation strategy; not purchase approval, source-code verification, or release approval.

## 1. Executive decision

Use Multi School ERP as a **workflow and product benchmark**, not as an automatic replacement for Tagore ERP.

The public product materials and live school demo show a broad education operations suite. The live school dashboard advertises a catalogue of 183 screens. The marketing site describes 48+ modules in its current search-indexed V3.8 material, while an opened page snapshot still contains older V3.0/V3.6 copy. The demo footer identifies its running build as V3.8.0-beta. The documentation's downloadable Android APK filenames still identify V3.5. These version and count inconsistencies must be resolved with the vendor before purchasing or using any source as a foundation.

Tagore already has meaningful custom foundations that should not be discarded: group/institution model, role and permission scopes, admissions CRM, fee ledger and payment services, family dashboard, school exam lifecycle, Olympiad, JEE/NEET CBT work in draft branches, HR/payroll, tasks/KPIs, communications, hostel/transport/inventory foundations, visitor/gate-pass, parent dashboard, mobile clients, offline sync schema and deployment/acceptance automation. Much of this is partial or not yet functionally accepted, not absent.

**Recommended path:** complete the comparative audit, obtain vendor clarifications, then execute one consolidated Tagore programme with a frozen scope, shared architecture contracts, parallel workstreams, one integration candidate and one release-blocking acceptance gate. Do not resume disconnected feature-by-feature improvisation. Also do not make one unreviewable giant commit: parallel implementation with controlled integration is faster and safer.

## 2. Sources reviewed

### Product and live demo
- Product/purchase site: https://multischoolerp.com/
- Demo landing page: https://demo.multischoolerp.com/
- Live school dashboard: https://demo.multischoolerp.com/school/dashboard
- Demo sign-in: https://demo.multischoolerp.com/login

### Documentation
- Documentation home: https://docs.multischoolerp.com/
- Demo and downloads: https://docs.multischoolerp.com/guide/demos
- Who does what / roles: https://docs.multischoolerp.com/guide/who-does-what
- Role permissions: https://docs.multischoolerp.com/school/roles-permissions
- Student and parent logins: https://docs.multischoolerp.com/school/student-parent-logins
- Fees setup: https://docs.multischoolerp.com/school/fee-setup
- Exams: https://docs.multischoolerp.com/school/exams
- Academic session: https://docs.multischoolerp.com/school/academic-session
- Backups: https://docs.multischoolerp.com/superadmin/backups
- Plans and subscriptions: https://docs.multischoolerp.com/superadmin/plans
- Superadmin reports: https://docs.multischoolerp.com/superadmin/payments-reports
- Knowledge base: https://docs.multischoolerp.com/superadmin/knowledge-base

### Videos and visuals
- V3.8 preview (ProjectWorlds, published 2026-10-14): https://www.youtube.com/watch?v=R5Biv23kBnw
- V3.5 preview: https://www.youtube.com/watch?v=ACM8u5mCLbs
- Product screenshot gallery includes school dashboard, superadmin dashboard, fee collection, student management, mobile apps, CBT exam builder/player, surveys, website builder, biometric management, ID-card designer, engagement creatives and Apps Center.
- The documentation portal and live demo provide more useful operational detail than a standalone brochure. No independently downloadable feature PDF was verified during this pass; vendor should provide the current dated PDF and release notes if one exists.

## 3. Product architecture and role model observed

The vendor advertises six connected application surfaces:
1. Super Admin SaaS web panel.
2. School web portal.
3. Parent/student Flutter application.
4. Staff/admin Flutter application.
5. Driver GPS Flutter application.
6. Windows biometric synchronization agent (.NET 8).

The documentation describes three administrative levels:
- Platform owner / Superadmin: all schools, billing, platform settings, backups, updates and platform reporting.
- Optional group/branch admin: only assigned branches.
- School-level roles: school admin, teacher, accountant, receptionist and librarian; custom roles can be configured.

Parent/student access is through a parent web/app experience and is restricted to linked children. A parent can switch between multiple children when linked to the same parent account. Driver access is vehicle-based in the documented flow.

### Tagore adaptation (mandatory)
- Keep **one global Owner account only**, controlled by the Tagore Owner. Do not copy the vendor's recommendation to create a second school-admin account.
- Owner has group-wide visibility and authority across every Tagore institution.
- Central Accounts has group-wide finance access, with explicitly separated institution books and approval controls.
- Cashier is institution-scoped and cannot edit fee masters, approve concessions/refunds/write-offs, alter ledgers or reconcile bank settlements unless separately approved.
- Principal, Coordinator, Teacher, Parent, Student, Fee Editor, HR, Hostel/Warden, Transport, Reception/Security and College roles are restricted by assigned institution, class, section, department or linked family.
- Preserve one verified parent identity across siblings, including children in different Tagore institutions, with guardian verification and revocation.
- Reject predictable default passwords. Force first-login password reset or OTP-based onboarding; never use admission number or phone number as a permanent password.

## 4. Vendor feature catalogue captured

The live school dashboard exposes a searchable catalogue advertised as 183 screens. Its visible groups include:

### Finance and fees
Fees dashboard, collect fees, search dues, all transactions, online transactions, challans, fee assignment, carry-forward, fee groups, discounts, fee types, due slips/history, fee data audit, fee import, late-fine rules, accounts dashboard, income, expenses, income/expense heads and bank accounts.

### Student information and lifecycle
Student dashboard/admission/list, photo search, parents/guardians, attendance, behaviour records, houses, categories, transfer certificate/exit, health records and deleted-student recycle bin.

### Academics and website
Academic dashboard, sessions, classes, sections, subjects, subject/elective/class-teacher assignment, periods, timetable, student promotion, website pages/slides/facilities/testimonials/navigation/CBSE disclosure/templates/design settings/full HTML.

### Front office and admission CRM
Front-office dashboard, admission enquiries, visitor book, complaints, postal records, gate passes, gate terminal, campus workers, lead dashboard, pipeline board, lead sources and stages.

### Examinations and assessment
Offline exam dashboard, exams/types/schedule/marks setup/marks entry, co-curricular areas and grades, grade management, report-card setup, marksheet generation/upload/history, teacher remarks, datesheets, online exam dashboard/management, question bank, chapters/topics, CBC strands/outcomes/competencies/pathways/reports, assessments and assessment reports.

### HR and staff
HR dashboard, staff directory/attendance, payroll, salary setup, leave approval/types, departments/designations, staff ID cards, HR settings, staff loans, salary templates, appraisal cycles/criteria/appraisals.

### Other operational surfaces
PTM scheduling/attendance/follow-ups/reports; lesson planning/review/approval/coverage/reports/settings; QR attendance/settings/reports/audit; device attendance logs and face-monitoring agent logs; surveys/feedback triage; compliance packs/document vault/checklists/government reports/inspections/calendar; classroom/building/room capacity/timetable/free-room/clash/booking/maintenance management; reports/analytics; certificates and ID cards; notice board, events, broadcasts, image gallery, creatives, birthdays and auto-send settings; school settings, custom fields, role permissions, payment/notification/admission settings, audit trail, subscriptions, module settings, content safety, backup management, communications wallet, chat moderation and messages.

### Marketing site modules and additional features
Admissions/enrolment, role permissions, import/export, certificate generation, front office, alumni, promotion/transfer, discipline, online exams, attendance, homework, timetable, report cards, library/virtual library, lesson plans, live classes, study materials, question bank, fees/payments, expenses, payroll, Tally integration, inventory, vendors, scholarships/discounts, miscellaneous income, WhatsApp/SMS/email, notice board, chat, parent helpdesk, events, teacher evaluation, recruitment, staff ID cards, parent/staff apps, GPS tracking, REST API, biometric agent, transport, hostel, SSO and backups.

V3.8 marketing material additionally names online courses/LMS, cashless canteen, lab management, classrooms/rooms, alumni/reunions, ERP import hub, undo center, 360-degree reports, ERP navigator, CCTV live view, parent-teacher chat, surveys, engagement/creatives, compliance/governance, CBT Pro, admission CRM, website builder, live classes, two-way ADMS, QR attendance, CBC/competency, health records and Apps Center.

## 5. Important findings and product risks

1. **Version mismatch:** current search-indexed marketing copy says V3.8; the opened purchase-site HTML snapshot includes older V3.0/V3.6 text; demo footer says V3.8.0-beta; demo APK names in documentation say V3.5. Obtain written delivered-version and component manifest.
2. **Module count mismatch:** marketing references 37, 46/48+ and 50+ modules in different current/older page variants; live demo calls out 183 screens. Treat screen count as navigation inventory, not proof of fully implemented, tested or included features.
3. **Pricing/licence:** purchase page advertises ₹10,000 one-time, perpetual worldwide commercial modification/deployment rights, no royalties, and one year updates/support. It prohibits resale of the unmodified source; modified white-label/client SaaS use is permitted. Digital source purchase is non-refundable. One production installation assistance is included; additional deployments may cost extra.
4. **Support discrepancy:** marketing page uses 24x7 support language, while purchase FAQ describes bug fixes and version updates during business hours. Get support hours, response SLA, update delivery and scope in writing.
5. **Subscription enforcement:** documentation explicitly says max-student and storage limits are displayed but not technically enforced. Do not assume plan limits are hard controls.
6. **Parent credentials:** documentation describes predictable initial credentials based on admission number/date of birth or parent's phone. This is unacceptable as a permanent credential policy for Tagore.
7. **Cron dependence:** reminders, queued messages and subscription expiry require scheduled tasks; the vendor documentation calls this out. Our deployment must verify scheduler/worker health.
8. **Backup caveats:** database and uploaded files are separate backup domains. Require off-site encrypted backups and an actual restore drill, not just a green backup status.
9. **Hardware mismatch:** vendor's strongest native biometric claim is ZKTeco/eSSL ADMS. Tagore has RealTime T304F; compatibility is unproven. Do not assume support or activate without exact protocol/SDK and a test device.
10. **CCTV:** product advertises CCTV live view, but Tagore Hikvision integration must be validated for model/NVR/RTSP/ONVIF, authentication, network isolation and privacy. No assumption of compatibility.
11. **Education breadth:** vendor is primarily a school ERP. Its general college/coaching claims do not establish Tagore PG College semester/affiliation workflows or advanced JEE/NEET examination depth.
12. **Demo safety:** vendor says the live demo is shared and data may be reset/changed by other visitors. Use only for read-only evaluation or synthetic, non-sensitive interactions.
13. **Feature PDF:** no current downloadable feature PDF was verified in this pass. Request the current versioned feature matrix, PDF brochure, release notes and exact deliverables from vendor.

## 6. Preliminary Tagore comparison

Status vocabulary:
- **Existing foundation:** source surfaces exist; functional acceptance still required.
- **Partial / verify:** some workflows exist, but parity/depth is not established.
- **Gap / priority:** missing or materially incomplete according to current evidence.
- **External dependency:** cannot be completed without hardware/provider details or approval.
- **Not applicable:** vendor feature is not required for Tagore's current operating model.

| Domain | Tagore baseline evidence | Benchmark gap / Tagore adaptation | Priority |
|---|---|---|---|
| Owner / platform | Tagore platform controller, group/institution schema, roles/scopes | Owner-only global admin; institution hierarchy; consolidated dashboards; audit and secure recovery | P0 |
| Role security | ScopeService, role matrix and institution scopes | Test every route/API/export/job; cashier and teacher/coordinator class-scope; deny cross-institution access | P0 |
| Finance / cashier | FeeService, fee obligations, installments, payments, imports, reconciliation; cashier PR #160 open | Finish cashier command center, till/cash drawer close, payment allocation, refunds/void approval, bank settlement separation, family split checkout | P0 |
| Parent family | Parent-student link table and multi-child dashboard | Verified cross-institution family linking; one checkout across children/institutions; per-child allocation and privacy | P0 |
| Payment reliability | Razorpay order/webhook foundation | Reservation/idempotency, duplicate webhooks, timeouts, refund/reversal, concurrent checkout and ledger invariants | P0 |
| School academics | Academic structure and school exam lifecycle | Board-specific CBSE/RBSE templates, HPC, competency/co-curricular evidence, timetable conflict/substitution, promotion undo | P1 |
| JEE/NEET coaching | CBT engine and related draft work exist; Olympiad is separate | Full QBank taxonomy, paper blueprint, section timers, negative marking, randomisation, robust player/autosave/resume, percentile/rank/topic analytics | P0 |
| College | Existing general ERP foundation; detailed programme not established | PG College course/semester/registration/internal/university exams/backlog/revaluation/transcripts/affiliation/AISHE/NAAC/IQAC only after course/affiliation discovery | P1 |
| Hostel | Hostel room/allocation schema and operations foundation | Bed constraints, warden workflow, roll-call, mess, visitor, leave/outing, deposits and integrated hostel fee ledger | P1 |
| HR / payroll | Staff, leave, payroll controller/service and migrations | Shift/roster, attendance exception approval, payroll run/lock, statutory settings and substitution impact | P1 |
| Biometric | No verified T304F integration in main | RealTime T304F adapter, immutable raw punches, device registry, offline retry and idempotency; blocked on vendor docs | P1 external |
| Transport | Transport controller/foundation | Route/stop/student mapping, driver app, GPS provider events, trip attendance and parent visibility | P2 |
| CCTV | Platform/CCTV foundation | Hikvision NVR/device compatibility, secure access and audit; blocked on model/network/privacy details | P3 external |
| Front office/security | Admissions CRM, visitor/gate pass foundation | Enquiry-to-enrolment conversion, photo/badge/QR, gate terminal and institution consistency tests | P1 |
| Communication | Communication service, parent dashboard, diary | Per-event channel preferences, delivery/retry logs, WhatsApp/SMS/email/push, parent chat/helpdesk, consent and moderation | P1 |
| Mobile apps | Parent/teacher Android clients and API scaffolding | Full Tagore API parity, family account, push notifications, offline queue/conflict resolution and release pipeline | P1 |
| Website / engagement | Public websites and internal communication | Per-institution website builder, admissions landing pages, CBSE disclosure, templates, events/creatives, SEO and content governance | P2 |
| Library / inventory / assets | Generic operations foundation and inventory/transport services | Complete circulation/barcode/fines, stock/procurement/vendors, asset assignment/depreciation/maintenance | P2 |
| Reports / compliance | Tagore reporting foundations | 360-degree dashboards, role-scoped exports, audit/compliance vault, regulator-specific outputs and data validation | P1 |
| Backups / undo | Backup/archive work in draft branches | Encrypted offsite database + file backups, verified restore, record-level undo with audit and protected financial immutability | P0 |
| AI / analytics | No accepted data-grounded assistant evidenced | Owner MIS, fee-risk and admission forecasting; role-aware grounded retrieval only after data permissions and core workflows are stable | P2 |
| Canteen / labs / online courses / alumni | Not established | Include only if Tagore confirms operational need; avoid expanding scope without owner decision | P3 |

The detailed Tagore feature register and repository evidence audit already exist in `docs/research/tagore-erp/`. Their statuses are a starting point and must be reconciled against the latest main branch, open PRs and actual runtime.

## 7. Integrated one-pass implementation strategy

### Operating principle

**One discovery pass → one scope lock → one integrated build programme → one consolidated acceptance cycle → one controlled release.**

This is not a proposal to write every line in a single unreviewed patch. It is a proposal to design all interfaces and dependencies first, then implement the complete agreed scope in coordinated parallel tracks, integrate continuously into one candidate branch, and fix the integrated defect set together. No production rollout until the candidate passes the complete acceptance matrix.

### Gate 0 — Complete evidence and scope lock
- Enumerate every Tagore route, Blade screen, controller, service, API, mobile endpoint, role, permission, job, scheduler, import/export, report, migration and external integration.
- Walk the Multi School ERP demo by persona and workflow; capture screen title, route, action, data inputs/outputs, permissions and edge cases.
- Walk Tagore with the same persona/workflow checklist.
- Reconcile current main plus every relevant open PR before duplicating work.
- Classify each item as keep, extend, build, replace, external dependency, or not applicable.
- Freeze acceptance criteria and a dependency map before broad coding.

### Gate 1 — Shared architecture contracts
Define and document before parallel coding:
- Institution/group/class/section/academic-year identity and data-scope rules.
- Owner-only admin and canonical role/permission matrix.
- Parent/guardian identity, verified cross-institution family linking and child visibility.
- Fee ledger invariants, payment allocation contract, order reservation/idempotency, refund/reversal accounting.
- Event/notification contract, audit-event schema, document/storage contract.
- API versioning and authentication for web/mobile/desktop.
- Shared UI design system, navigation, responsive layouts and common form/table patterns.
- Migration, import/export and backup/restore contract.

### Gate 2 — Parallel implementation tracks

| Track | Complete scope owned | Key deliverable |
|---|---|---|
| A. Platform, Owner, security | Owner-only access, institution hierarchy, roles, permissions, session/recovery, audit, settings | Secure platform foundation |
| B. Finance and cashier | Fee structures, demands, collection, cashier, central accounts, partial/family checkout, reconciliation, refunds, receipts, reporting | Fully reconciled finance engine |
| C. Student lifecycle and admissions | Enquiries, applications, documents, admissions conversion, family links, 360 profile, transfer, promotion, alumni | End-to-end student lifecycle |
| D. School academics | CBSE/RBSE structure, timetable, attendance, substitution, homework/diary, exams, HPC/report cards, certificates | Complete school academic operation |
| E. Coaching and examination | JEE/NEET QBank, paper builder, CBT player, scoring, rank/percentile, analytics, Olympiad | Independent coaching-grade exam engine |
| F. College and hostel | PG College semester and affiliation workflows, hostel/warden/mess/room/fee operations | College and residential operations |
| G. HR and campus | Staff lifecycle, attendance, leave, payroll, biometric adapter, visitor/gate, transport, inventory, library, CCTV adapter | Integrated workforce and campus operations |
| H. Communication and clients | Parent/teacher/student portals, mobile apps, driver app, notifications, diary/chat/helpdesk, offline sync | Unified multi-device experience |
| I. Reporting and platform operations | MIS, exports, compliance, backups/restore, undo, AI readiness, performance, security and observability | Operational command and recovery layer |
| J. Website CMS and public-site platform | Per-institution websites, owner portfolio, scoped institute editor, page/block builder, themes, navigation, media, bilingual content, admissions forms, SEO, domains/TLS, revisions, previews, publish/schedule/rollback | Independently manageable, tenant-isolated public websites |

Parallel tracks must own separate bounded code surfaces. Shared schemas and service interfaces are agreed at Gate 1; one owner controls any shared hot file (routes, role matrix, migrations, central finance services). Avoid concurrent conflicting edits.

### Gate 3 — Single integrated candidate
- Use one long-lived integrated candidate branch for all implementation tracks. Workstreams may use short-lived task branches only when needed, but every completed slice must be integrated into this candidate continuously; do not create standalone feature release trains or deploy individual modules.
- Reconcile existing open PR work into the candidate (including the cashier implementation and comparative/CMS contracts) rather than leaving parallel product versions.
- Integrate all tracks into this single release-candidate branch with a migration sequence and feature flags for external dependencies.
- Run static analysis, format/lint, Composer/npm builds, migration/seed validation and route inventory.
- Run unit, feature, API, integration, role/permission and data-isolation suites.
- Run browser E2E for Owner, Central Accounts, Cashier, Principal, Coordinator, Teacher, Parent, Student, HR, Warden, Reception/Security and relevant college/coaching roles.
- Run mobile Android smoke and offline/reconnect/conflict tests; desktop agent tests only where device protocol is available.
- Use synthetic data only. No live student/parent/financial data during acceptance.

### Gate 4 — One consolidated defect and acceptance cycle
All failures are recorded against the exact candidate SHA and grouped by root cause, not patched randomly. Fix related defects in coordinated batches, rerun affected tests, then rerun the complete release gate. No “page returns 200” acceptance: each role must complete real workflows including create, update, approval, reject, export, notification and audit.

Required release gates:
1. Backend tests and static checks.
2. Frontend production build.
3. Database migration from clean and upgrade path.
4. Role/action/institution/class/family isolation negatives.
5. Fee ledger, partial allocation, concurrent order, duplicate webhook, refund/reversal and reconciliation invariants.
6. Admissions-to-student and student-to-family lifecycle.
7. CBSE/RBSE report cards and coaching CBT complete mock.
8. HR/payroll, leave and attendance workflows.
9. Parent/teacher mobile API parity and offline recovery.
10. Import/export accuracy and failure rollback.
11. Backup of database and files plus restore into disposable environment.
12. Browser/responsive/accessibility smoke and production Docker/HTTPS smoke.
13. Performance and security review.
14. Owner sign-off on exact release SHA.

### Gate 5 — Release
- Merge only the fully accepted candidate into the production branch.
- Deploy the exact tested SHA; record release tag and database migration state.
- Verify health, queue, scheduler, TLS, logs, backup and role smoke tests.
- Only then authorize live data onboarding.

## 8. Vendor due-diligence questions before source purchase

Obtain written answers and a dated manifest:
1. Is the delivered source exactly the same build as the current demo footer V3.8.0-beta? Why do marketing, page snapshots and APK filenames show V3.0/V3.5/V3.6/V3.8?
2. Provide a list of all 183 demo screens and identify which are implemented, licensed, optional, beta, demo-only or add-ons.
3. Provide the complete web, Flutter, .NET agent source, database dump/schema, migrations, API docs, install guide, build instructions and release notes.
4. Confirm perpetual commercial rights to modify and deploy for all Tagore institutions; clarify whether one installation can support our group and whether additional production deployment fees apply.
5. Confirm support hours, response SLA, one-year update mechanism, security fixes and whether custom integrations are excluded.
6. Demonstrate role permissions, cross-school isolation, cashier workflow, family-wide checkout, refund/reversal and bank reconciliation.
7. Demonstrate JEE/NEET CBT section rules, negative marking, randomisation, autosave/resume, rank/percentile and question-bank import/export.
8. Confirm RealTime T304F support explicitly; provide protocol/SDK/device models. ZKTeco/eSSL compatibility is not evidence of T304F compatibility.
9. Confirm Hikvision NVR compatibility and security model for CCTV live view.
10. Clarify data encryption at rest, key management, password policy, OTP/2FA, audit retention, backup encryption and restore process.
11. Explain which AI features require paid Gemini/OpenAI keys and expected running costs.
12. Confirm all mobile apps build from delivered source, package IDs can be rebranded, push notification setup and app-store publishing responsibilities.

## 9. Current Tagore work that must be reconciled, not discarded

- Current production baseline main SHA at audit: `131e0b154554110b376f5a7d24148b5da4cf73f7`.
- Cashier PR #160 is open and unmerged at the time of this audit; it must be reviewed and reconciled with the final finance architecture rather than duplicated.
- Existing research files include `report.md`, `expanded-market-feature-register.md`, `feature-gaps.csv`, `repository-evidence-audit.md`, `roadmap.md` and `parallel-execution-board.md`.
- The current repo contains dedicated Tagore controllers/services/migrations/views and Android/desktop scaffolds; do not rebuild those without a verified gap.
- Preserve untracked local diagnostic files and avoid destructive cleanup.
- No production data, live financial transactions, production deployment or merge was performed as part of this comparative audit.

## 10. Immediate execution order

The discovery and architecture-contract work already recorded in this programme is sufficient to begin implementation. Remaining vendor questions and hardware-specific details are tracked as external dependencies; they must not block software work that can proceed safely.

1. Establish one integrated candidate branch from the latest cashier implementation so existing work is preserved.
2. Fold the comparative audit, shared architecture contracts and Website CMS scope into that candidate; reconcile every relevant open implementation branch against it.
3. Execute Tracks A–J concurrently where their dependencies permit, with shared contracts and ownership of hot files enforced. No module is treated as a separately shippable product.
4. Maintain a single integrated defect/acceptance board and continuously run targeted CI while the programme is being built.
5. When all agreed scope is integrated, run the complete cross-role/cross-institution acceptance programme once against the frozen candidate SHA; fix failures in coordinated batches and rerun the full gate.
6. Merge and deploy only that accepted candidate. No intermediate feature-by-feature production deployments.


## Website CMS scope addendum

A dedicated implementation track J, Website CMS & Public Site Platform, is defined in `website-cms-product-scope-and-acceptance.md`. The existing basic CMS page form and generic website records are foundations only, not a completed website builder. All institute websites must be independently editable by authorised institute administrators with strict content, media, preview, publish and hostname isolation.


## Superadmin / Platform Owner benchmark addendum — 28 November 2026

Reference reviewed: Multi School ERP v3.8 Super Admin demo entry and its public platform documentation. The protected dashboard itself redirects to login, so this addendum uses only publicly visible demo descriptions and documentation; it does not claim a visual inspection of authenticated screens.

### Reference capabilities worth adopting
- A clear platform-owner layer above institution-level administration, with a single cross-school overview.
- Institution portfolio and usage reporting, with drill-down into each institution.
- Consolidated fee collection and financial reporting.
- Login/security audit, school engagement/usage signals, attendance trends and communication-cost visibility.
- Operational control surfaces for backups, updates, integrations, platform settings and server health.
- Dashboard KPI cards with trends and role-appropriate quick actions.
- A clean separation between global platform controls and institution-local administration.

### Tagore adaptation
- Keep the existing Owner role as the only global platform authority; do not introduce a second Superadmin/Branch Admin login in this release. Principals and other institute staff remain scoped to their assigned active institutions.
- The integrated Owner dashboard now adds a group control centre with active-institution portfolio cards, student/staff counts, pending admissions leads, active tasks, recorded fee collections, outstanding fees and a derived collection rate.
- The same view exposes the current database connection plus the configured environment, queue driver and cache store. These are configuration/runtime indicators, not a substitute for real infrastructure monitoring.
- The existing Administration, Reports, Platform Services, Operations and Performance workspaces remain the drill-down destinations.

### Remaining Owner-console gaps (release work, not completion claims)
1. Actual server/container health, worker and scheduler heartbeat, disk/storage capacity and TLS expiry monitoring.
2. Backup freshness, off-site copy verification, restore rehearsal history and retention status surfaced in the console.
3. Login/session security audit and institution engagement/usage reports.
4. Attendance trend and communication delivery/cost analytics with institution filters.
5. Fee collection trend and reconciliation reporting by institution, academic year and payment channel.
6. Institution-level module enablement, branding/domain status and integration health.
7. Owner audit history for high-risk platform changes and safe, reasoned rollback/restore workflows.

### Acceptance
- Owner can see all active institutions and consolidated group figures; a Principal sees only assigned institutions and never sees the Owner command centre.
- Institution cards reconcile to the corresponding institution-scoped reports.
- Runtime configuration is labelled accurately; no configured driver is represented as a verified healthy service.
- Platform settings, backups and server-health controls remain unavailable to non-Owner roles.
