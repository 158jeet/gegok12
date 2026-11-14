# Runtime route manifest — first-pass audit

**Evidence date:** 2026-11-14  
**Source:** GitHub Actions run `ERP Full Acceptance Convergence #598` (run ID `43591954144`), job `Full ERP acceptance (fail closed)` (job ID `132440628904`).  
**Candidate context:** PR #72 research branch; CI disposable SQLite database; `APP_ENV=testing`; E2E feature flag enabled.  
**Artifact:** `erp-full-acceptance-854e6c...` (includes `route-list.json`, `route-list.txt`, browser reports and server log). The exact artifact remains attached to the workflow run; it is not a production snapshot.

## Verified output

- Laravel `php artisan route:list --json` returned **1,444 registered route entries**.
- The workflow parsed the JSON with `JSON_THROW_ON_ERROR` and verified a non-empty array.
- HTTP method counts from the manifest: GET 952, POST 459, DELETE 18, PATCH 16 (GET routes also advertise HEAD; method totals are not a route-entry count).
- Route prefixes: `admin` 471; `teacher` 225; `api` 200; `tagore` 173; `accountant` 99; `receptionist` 96; `student` 75; `library` 52; remaining routes are authentication, public forms, package assets, testing and platform endpoints.
- **221 named / 1,223 unnamed** routes.
- **27 Closure** actions.
- No duplicate exact `method + URI` pairs were found in this manifest.
- API prefix: 200 routes; 161 explicitly include `auth:sanctum`. Other API routes include login, password reset and public lookup/info endpoints; each needs action-level review rather than assuming all are intentionally public.
- The manifest contains 69 routes whose expanded middleware list does not include an `Authenticate` class. This is a triage count, not a count of public vulnerabilities: it includes expected public/authentication routes, test-only routes, package routes and routes protected by other middleware.

## High-priority audit candidates

### 1. State-changing actions registered as GET

The manifest contains **50 GET/HEAD routes whose action is named `destroy` or `delete`**, plus **5 GET/HEAD approval-related actions** and **3 GET/HEAD reset/token-update candidates**. Examples include:

- Legacy web deletes: `admin/academic/delete/{id}`, `admin/document/delete/{id}`, `admin/notice/delete/{id}`, `teacher/homework/delete/{id}`, `student/assignment/delete/{id}`, `library/books/delete/{id}`, `receptionist/visitorlog/delete/{id}`.
- API deletes: `api/v2/leave/delete/{id}` and `api/v2/task/delete/{id}` are GET routes even though they are inside the Sanctum-authenticated v2 group.
- Other state candidates: `admin/event/approve/{id}`, `teacher/leave/approve/{id}`, `teacher/studentLeave/approve/{id}`, `admin/user/resetPassword/{id}`, and `api/v2/updatetoken`.

**Required next action:** inspect each controller method and all calling Blade/JS/mobile clients; move mutations to POST/PATCH/DELETE with CSRF/authentication, preserve compatibility only through safe redirects or explicit deprecation, and add tests proving GET/HEAD never mutates. Do not bulk-rewrite routes without checking callers and mobile API contracts.

### 2. Public and package routes require environment verification

The testing manifest includes `__e2e/*`, `_debugbar/*`, `_ignition/*`, and `tinker` routes. The E2E endpoints are explicitly conditionally registered only when `app()->environment('testing')` and `TAGORE_E2E_ENABLED` are true; they are expected in this CI manifest and should not be counted as production exposure.

The checked-in `config/debugbar.php` sets `enabled` from `DEBUGBAR_ENABLED` (default null) and enables collectors including database queries, session, mail, cache and models. Route registration alone does not prove these routes are enabled or reachable in production. Verify production dependency installation, `APP_DEBUG`, `DEBUGBAR_ENABLED`, Ignition and WebTinker package providers/routes, and test the deployed-equivalent configuration with a disposable environment. Explicitly fail the release gate if debug/tinker/ignition endpoints are reachable in production mode.

### 3. Route naming and auditability

1,223 routes are unnamed. This makes stable route references, route-level authorization mapping and automated UI-to-route audits harder. Prioritise naming modern Tagore routes and high-risk legacy actions; do not rename public API routes without client compatibility review.

## Important scope limitations

- This manifest was generated from the CI testing environment, seeded synthetic data and E2E flag. It is **not** a production route manifest and does not prove production exposure.
- A route's middleware list is not proof of record-level tenant, class, academic-year, guardian or employee ownership enforcement.
- The manifest's `path` field is null for most entries; source-file attribution still requires route-source inspection.
- No controller bodies, model scopes, policies, export/download paths or runtime negative authorization cases were fully audited by generating this manifest.
- The CI run passed: backend **145 tests / 1,327 assertions**, full authenticated browser suite **92 passed**, focused Operations checks **5 passed** and public login smoke **2 passed**. These are the run's reported results, not proof of full feature parity or tenant isolation.

## Next route audit work

1. Inventory all 50 GET delete/destroy routes and 5 approval candidates; locate every caller; test GET/HEAD non-mutation; produce a safe migration batch.
2. Inspect the 23 API routes without explicit Sanctum authentication individually; classify public login/lookup/info vs routes that need auth or ownership checks.
3. Inspect impersonation routes and impersonation stop flow; verify audit events, role boundaries, target institution and session revocation.
4. Audit modern Tagore and legacy parent/student endpoints for cross-child, cross-institution and IDOR controls.
5. Map all 1,444 route entries to controller action, request validation, model ownership, view/API client, policy/Gate and negative tests; prioritise finance, identity, documents, exports and impersonation.
6. Generate a production-equivalent route manifest in disposable CI with debug/tinker packages disabled as expected for production; compare against the testing manifest.

## Public API and password-recovery action review

The next source pass inspected the unauthenticated API route handlers, their request validators and OTP traits. This is source inspection, not live exploit testing.

### Public endpoints classified as expected candidates

- `api/parent/login` and `api/teacher/login`: public authentication entry points; require stricter per-account/IP/device rate limits and generic failure responses.
- `api/schools/list`, `api/apk/parent`, `api/apk/teacher`, country/state/city, blood-group, degree, certificate, marital-status and designation lookups: plausible public bootstrap/reference data. Confirm responses contain no private fields and add cache/rate controls as appropriate.
- `api/password/reset`, `api/password/store`, `api/reset/check`, `api/reset/change/password`: public recovery endpoints and therefore high-risk identity boundaries.
- `api/school/info`: public school bootstrap, but the current implementation selects `School::first()` rather than a school selected by slug/tenant. In a multi-institution platform this can return the wrong institution's identity and settings.

### Confirmed source-level defects / risks in password recovery

1. `AuthenticationProcess::createAuthentication()` generates the six-digit OTP with `rand()` and stores the token as a plain value in the Authentication table. Use a cryptographically secure generator and store a verifier/hash; never expose OTP material in logs or responses.
2. `OTPRequest` accesses `$otp[0]` in its custom validators without checking that a pending OTP exists. A user with no reset record can trigger an exception path instead of a controlled validation failure.
3. `OTPRequest` compares the OTP to `$_SERVER['REMOTE_ADDR']`-bound data. IP binding is brittle for mobile users and must use trusted proxy-aware request context if retained; it is not a substitute for rate limiting/device verification.
4. OTP expiry is checked while `storePassword` verifies the code. That endpoint then changes the Authentication row from status 0 to status 1. The subsequent `resetChangePassword` validator/controller reads status-1 reset rows but does **not** recheck `expires_on` and does not consume/invalidate the successful token after changing the password. A previously verified OTP can therefore remain reusable beyond its intended five-minute expiry and may be replayed for further password changes. Treat as a P0 identity defect; prove with a disposable synthetic-account regression test before fixing.
5. `ResetPasswordRequest` has custom validators and messages that distinguish unregistered/inactive accounts, enabling account-state enumeration. Its status validator dereferences `userprofile`; null/missing related profile handling is not defensive.
6. `api/reset/check` returns `is_reset` for a caller-supplied mobile number and user-group ID without authentication. It is a direct account-state oracle unless the product intentionally exposes and mitigates it.
7. `api/parent/login` uses the general API middleware throttle (`60,1`) in the inspected route setup; no stricter per-account/device failed-login throttle was found in the inspected route/request code.
8. `UserController::resetPassword()` revokes the user's existing Sanctum tokens before the OTP is completed. This logs out every device when a reset is merely initiated, before the user proves possession of the recovery channel.

### Other public API route correctness and tenant findings

- `api/events/show/details/{id}` points to `Api\\EventsController@showdetails`, but the inspected controller class has no `showdetails` method. Treat this as a broken route candidate; add a route smoke test and either implement the intended safe action or remove/deprecate the route after checking mobile callers.
- `api/school/info` returns `School::first()`, not a school selected by request context. This is incompatible with a group of institutions unless the endpoint is deliberately bound to a single configured public tenant.
- The 23 API routes without explicit Sanctum middleware are not all vulnerabilities: login, reference lookups and public school listing are expected to be public. Password reset and account-state endpoints need a separate threat model and stricter controls. Authenticated API data routes still require record-level family/student/tenant authorization.

**Required implementation acceptance:** OTP entropy and hashed storage; expiry at every transition; atomic single-use consumption; attempt limits by account and source; generic responses; safe missing-record behavior; per-route rate limits; session/token revocation at the correct point in the verified reset flow; synthetic tests for no OTP, expired OTP, wrong OTP, replay, concurrent use, inactive user, unknown mobile and two-institution bootstrap.

9. **Unauthenticated remote device logout:** `api/logout/devices` and `api/teacher/logout/devices` are POST endpoints without authentication middleware. Their handlers locate an account using only the submitted mobile number and user-group ID, then delete all Sanctum tokens and clear device metadata. No OTP or proof of account ownership is checked in these handlers. This creates an account-level denial-of-service vector for anyone who knows a parent's or teacher's mobile number. Require verified recovery proof or authenticated device-management authorization, and add negative tests for unauthenticated arbitrary mobile numbers.


## Follow-up source pass — route mutation classification (2026-11-14)

The route-definition files were inspected on the research branch after the runtime manifest flagged GET/HEAD mutation candidates. This pass distinguishes active declarations from commented-out historical examples; it is still source inspection, not a controller exploit test.

### Active source declarations confirmed in inspected route files

| Route family | Source declaration / action | Preliminary classification |
|---|---|---|
| Admin notes | `GET /notes/delete/{id}` → `NotesController@delete` | State-changing by route intent; inspect method body and UI caller |
| Admin subject | `GET /subject/delete/{id}` → `SubjectController@destroy` | Delete action on GET; migrate after caller inventory |
| Admin events | `GET /events/delete/{id}` → `EventsController@destroy` | Delete action on GET; migrate after caller inventory |
| Admin event approval | `GET /event/approve/{id}` → `EventsController@eventapprove` | Approval action on GET; inspect whether this renders a form or commits state |
| Admin homework | `GET /homework/delete/{id}` → `Approval\\HomeWorkController@destroy` | Active route in approval namespace; confirm route-group closure and callers |
| Admin notice | `GET /notice/delete/{id}` → `NoticeBoardController@destroy` | Delete action on GET |
| Admin leave type | `GET /leavetype/delete/{id}` → `LeaveTypesController@destroy` | Delete action on GET |
| Admin magazine | `GET /magazine/delete/{id}` → `BulletinsController@destroy` | Delete action on GET |
| Admin call log / postal record | `GET /calllog/delete/{id}`, `GET /postalrecord/delete/{id}` | Delete actions on GET |
| Admin class wall | `GET /classwall/page/delete/{id}`, `GET /classwall/post/delete/{id}`, and comment/reply delete routes | Delete actions on GET |
| Teacher assignment | `GET /assignment/delete/{id}` → `AssignmentController@destroy` | Active declaration; delete action on GET |
| Teacher leave approval | `GET /leave/approve/{id}` → `LeaveController@approveCreate`; POST same URI → `approveStore` | GET appears to be the approval form; POST is the mutation. Do not misclassify GET as a mutation without body/caller proof |
| Teacher visitor/call/postal logs | GET `/visitorlog/delete/{id}`, `/calllog/delete/{id}`, `/postalrecord/delete/{id}` | Delete actions on GET |
| Teacher class wall | GET post/comment/reply delete routes | Delete actions on GET |
| Librarian | GET `/bookscategory/delete/{id}`, `/booklending/delete/{id}` | Delete actions on GET; books delete declaration in this file is commented out |
| Receptionist | GET visitor/call/postal log delete routes | Delete actions on GET |
| API v2 | GET `/leave/delete/{id}` → `LeaveController@destroy`; GET `/task/delete/{id}` → `TaskController@destroy` | Authenticated Sanctum group, but HTTP method is unsafe; convert only after mobile-client compatibility review |

### Important false-positive controls

- Several route files contain commented-out GET delete declarations. They are not active routes and must not be included in an implementation change.
- Some GET paths containing `approve` are form/list pages (for example `approveCreate`); the paired POST route performs the state transition. Rename or migrate only after inspecting the action body and view form.
- Some destroy actions already use DELETE (for example admission and payroll resource deletes); preserve these compliant routes.
- Route declarations may be duplicated across role portals (teacher/receptionist/admin). A single controller action may serve multiple route families and must be changed consistently with all callers.
- The runtime count of 50 GET/HEAD destroy/delete candidates is a manifest-wide count. The source snippets above are a confirmed sample, not a complete one-to-one enumeration of all 50.

### Evidence still required before implementation

1. Retrieve the complete runtime route manifest artifact and build an exact table of all 50 method+URI+action+middleware records; do not infer the list from grep alone.
2. For every candidate, inspect controller body for actual mutation, soft-delete, redirect-only behavior, ownership and institution checks.
3. Search every Blade form, JavaScript handler, Android/desktop client and external API contract that calls the route; record caller path and current method.
4. Add regression tests that send GET and HEAD and assert no state change; then test the replacement POST/PATCH/DELETE route with CSRF/authentication and authorization.
5. Implement in small route-family batches with temporary compatibility only where required. Compatibility endpoints must never mutate on GET; return a safe deprecation response or redirect to a read-only page.
6. Re-run route manifest, browser tests, mobile API contract tests and negative cross-institution tests after each batch.

**Decision:** no application route or controller was changed in this pass. The next implementation prerequisite is the exact runtime 50-route export plus caller/controller mapping. This prevents an unsafe blanket rewrite and avoids breaking legacy clients.


## Exact runtime candidate inventory recovered from CI artifact

The original retained artifact `erp-full-acceptance-854e0af606b6c943783f77d56c1cb500524d459c` was downloaded and parsed again. It contains 1,444 route records. Applying the original narrow rule — GET/HEAD plus an action containing `destroy` or `delete` — returns exactly **50 routes**. The broader scan returns 5 approval-related actions and 3 reset/token-update candidates. This reconciles the 50 count with the broader 58-row heuristic.

### 50 GET/HEAD destroy/delete routes

| Route | Controller action |
|---|---|
| `accountant/task/{id}/delete` | `Accountant\\TaskController@destroy` |
| `admin/academic/delete/{id}` | `Admin\\AcademicYearController@destroy` |
| `admin/calllog/delete/{id}` | `Admin\\CallLogController@destroy` |
| `admin/classwall/page/delete/{id}` | `Admin\\PagesController@destroy` |
| `admin/classwall/post/delete/comment/{comment_id}` | `Admin\\PostCommentsController@destroy` |
| `admin/classwall/post/delete/{id}` | `Admin\\PostsController@destroy` |
| `admin/classwall/post/reply/delete/comment/{post_comment_id}` | `Admin\\PostReplyCommentsController@destroy` |
| `admin/discipline/delete/{id}` | `Admin\\DisciplineController@destroy` |
| `admin/document/delete/{id}` | `Admin\\DocumentsController@destroy` |
| `admin/events/delete/{id}` | `Admin\\EventsController@destroy` |
| `admin/holiday/delete/{id}` | `Admin\\HolidaysController@destroy` |
| `admin/homework/delete/{id}` | `Admin\\Approval\\HomeWorkController@destroy` |
| `admin/leavetype/delete/{id}` | `Admin\\LeaveTypesController@destroy` |
| `admin/magazine/delete/{id}` | `Admin\\BulletinsController@destroy` |
| `admin/notes/delete/{id}` | `Admin\\NotesController@delete` |
| `admin/notice/delete/{id}` | `Admin\\NoticeBoardController@destroy` |
| `admin/phonenumber/delete/{id}` | `Admin\\TelephoneDirectoryController@destroy` |
| `admin/postalrecord/delete/{id}` | `Admin\\PostalRecordController@destroy` |
| `admin/standardLink/delete/{id}` | `Admin\\StandardsLinkController@destroy` |
| `admin/subject/delete/{id}` | `Admin\\SubjectController@destroy` |
| `admin/task/{id}/delete` | `Admin\\TaskController@destroy` |
| `admin/visitorlog/delete/{id}` | `Admin\\VisitorLogController@destroy` |
| `api/v2/leave/delete/{id}` | `Api\\LeaveController@destroy` |
| `api/v2/task/delete/{id}` | `Api\\TaskController@destroy` |
| `library/booklending/delete/{id}` | `Librarian\\BookLendingController@destroy` |
| `library/books/delete/{id}` | `Librarian\\BookController@destroy` |
| `library/bookscategory/delete/{id}` | `Librarian\\BookCategoryController@destroy` |
| `library/task/{id}/delete` | `Librarian\\TaskController@destroy` |
| `receptionist/calllog/delete/{id}` | `Receptionist\\CallLogController@destroy` |
| `receptionist/leave/delete/{id}` | `Receptionist\\LeaveController@destroy` |
| `receptionist/phonenumber/delete/{id}` | `Receptionist\\TelephoneDirectoryController@destroy` |
| `receptionist/postalrecord/delete/{id}` | `Receptionist\\PostalRecordController@destroy` |
| `receptionist/task/{id}/delete` | `Receptionist\\TaskController@destroy` |
| `receptionist/visitorlog/delete/{id}` | `Receptionist\\VisitorLogController@destroy` |
| `student/assignment/delete/{id}` | `Student\\AssignmentController@destroy` |
| `student/classwall/post/delete/comment/{comment_id}` | `Student\\PostCommentsController@destroy` |
| `student/classwall/post/reply/delete/comment/{post_comment_id}` | `Student\\PostReplyCommentsController@destroy` |
| `student/homework/delete/{id}` | `Student\\HomeworkController@destroy` |
| `student/task/{id}/delete` | `Student\\TaskController@destroy` |
| `teacher/assignment/delete/{id}` | `Teacher\\Approval\\AssignmentController@destroy` |
| `teacher/calllog/delete/{id}` | `Teacher\\CallLogController@destroy` |
| `teacher/classwall/post/delete/comment/{comment_id}` | `Teacher\\PostCommentsController@destroy` |
| `teacher/classwall/post/delete/{id}` | `Teacher\\PostsController@destroy` |
| `teacher/classwall/post/reply/delete/comment/{post_comment_id}` | `Teacher\\PostReplyCommentsController@destroy` |
| `teacher/homework/delete/{id}` | `Teacher\\Approval\\HomeWorkController@destroy` |
| `teacher/leave/delete/{id}` | `Teacher\\LeaveController@destroy` |
| `teacher/lessonplan/delete/{id}` | `Teacher\\LessonPlanController@destroy` |
| `teacher/postalrecord/delete/{id}` | `Teacher\\PostalRecordController@destroy` |
| `teacher/task/{id}/delete` | `Teacher\\TaskController@destroy` |
| `teacher/visitorlog/delete/{id}` | `Teacher\\VisitorLogController@destroy` |

### Additional 5 approval and 3 reset/token candidates

- `admin/event/approve/{id}` → `Admin\\EventsController@eventapprove`
- `api/teacher/checkleave/approve/list` → `Api\\Teacher\\LeaveController@leaveCheckListApproved` (read/list action; false positive, not a state mutation)
- `teacher/leave/approve/list/{id}` → `Teacher\\LeaveController@approveList` (read/list action; false positive)
- `teacher/leave/approve/{id}` → `Teacher\\LeaveController@approveCreate` (GET form; paired POST performs approval)
- `teacher/studentLeave/approve/{id}` → `Teacher\\StudentLeaveController@approveCreate` (GET form; paired POST performs approval)
- `admin/user/resetPassword/{id}` → `Admin\\UserController@resetPassword`
- `api/v2/updatetoken` → `Api\\UserController@updatetoken`
- `password/reset/{token}` → `Auth\\ResetPasswordController@showResetForm` (GET form; not a password mutation)

### Controller-body spot checks — confirmed state-changing GET examples

- `Admin\\AcademicYearController@destroy` queries an academic year by ID, authorizes it with the `academic` Gate, calls `delete()`, and writes an activity log. The route does not show an explicit school_id constraint in the query; the Gate is the remaining object authorization boundary and needs a cross-school negative test.
- `Admin\\EventsController@eventapprove` loads an event by ID, sets `status='active'`, saves it, then triggers push and notification events. This is a confirmed mutation behind GET, not a form page.
- `Api\\LeaveController@destroy` loads a leave application by ID, sets status to cancelled, saves and deletes it, then logs the action. Its query is ID-only; it does not constrain the leave row to the authenticated user's school or student/guardian relationship in the method body.
- `Api\\TaskController` has ID-only task lookup patterns in its show/edit methods. Its destroy method needs a focused read and ownership test before changing the route; the API group has Sanctum authentication but authentication alone does not prove task ownership.

**Risk classification:** these examples establish that the candidate set contains real state-changing GET endpoints. They do not prove every one of the 50 has the same impact or missing authorization. The next pass must finish per-controller and caller review, especially ID-only lookups and notification side effects. Do not change route verbs until this review and client compatibility mapping are complete.


## Additional handler and UI trace sample

A follow-up read inspected selected handlers and their Blade entry pages:

- `Api\\TaskController@destroy` deletes all `TaskAssignee` rows for the selected task and then deletes the task itself. It loads the task with `Task::where('id', $id)->first()`; the destroy method does not constrain the task by the authenticated user's school, academic year, assignee relationship or ownership. This is a confirmed ID-only mutation candidate behind Sanctum authentication. The paired `show` and `edit` methods also use ID-only task lookups. Add cross-school and non-assignee negative tests before refactoring.
- `Api\\LeaveController@destroy` similarly loads by ID only, sets the leave application to cancelled, saves, deletes, and logs. The handler does not scope the selected leave application to the authenticated user's school or permitted student/guardian relationship.
- `Admin\\EventsController@eventapprove` is a confirmed GET mutation: it sets the event status to active, saves it and emits push/notification events. This action should become an explicit POST transition protected by the intended event-approval permission; GET must only display the approval form/details.
- `Admin\\AcademicYearController@destroy` loads by ID, checks the `academic` Gate, deletes and logs. The controller query itself is not school-scoped, so the Gate must be tested against a foreign-school academic-year record.
- The academic-year index Blade delegates rendering and actions to a Vue component named `list-academic-year`; the events index delegates to `show-event` and `event-popup`; the subject index includes `admin.subject.list`; the teacher assignment index delegates to `list-tab-assignment`. The precise component source and HTTP callers have **not** yet been traced, so caller compatibility remains open. The route audit must follow these component names to their source before implementation.

These are source-level conclusions only. No endpoint was invoked against any database, and no route/controller was changed. The next pass should trace the named Vue components and the remaining controller bodies, then write the GET-no-mutation and tenant-ownership regression tests on disposable fixtures.
