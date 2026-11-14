# Route, middleware and authorization inventory — first pass

**Repository baseline:** `main` at `a5806356ad317c8126845a6512b80575376a7f63`  
**Research branch:** `research/tagore-erp-complete-audit-baseline`  
**Scope:** static source inventory of all route-definition files, route-provider registration, legacy role middleware and the central Gate/policy registry. This is not a runtime `route:list` export and does not prove every route is reachable or correctly protected.

## 1. Route-file inventory

The recursive Git tree contains 19 route-definition files. A source scan found 1,580 line-level `Route::` declarations matching the common HTTP/resource/group patterns below. These are declaration lines, not guaranteed unique or active endpoints; route groups, conditional registration, included files and commented declarations require runtime resolution.

| Route file | Declaration lines matched | Route family / notes |
|---|---:|---|
| `routes/admin.php` | 503 | Legacy school-admin portal; loaded with admin namespace and middleware |
| `routes/teacher.php` | 255 | Legacy teacher portal; includes role-specific approval groups |
| `routes/teacherapi.php` | 134 | Teacher API routes; includes Sanctum and role-specific groups |
| `routes/api.php` | 107 | API root; includes `teacherapi.php` |
| `routes/receptionist.php` | 118 | Legacy receptionist portal |
| `routes/student.php` | 77 | Legacy student portal |
| `routes/payroll.php` | 51 | Payroll routes under accountant URL prefix |
| `routes/librarian.php` | 52 | Library portal |
| `routes/accountant.php` | 50 | Legacy accountant portal |
| `routes/setting.php` | 22 | Admin settings routes; distinct middleware from main admin portal |
| `routes/web.php` | 31 | Public/auth web routes; includes Tagore routes |
| `routes/tagore.php` | 174 | Modern Tagore application routes |
| `routes/tagore_api.php` | 2 | Separate Tagore API route file; no registration reference found in inspected route provider/includes |
| `routes/addon.php` | 2 | Optional exam add-on; included by admin routes |
| `routes/superadmin.php` | 2 | Superadmin route file; route declarations must be interpreted with provider group |
| `routes/channels.php` | 0 | Broadcast channels, not HTTP endpoints |
| `routes/console.php` | 0 | Console command scheduling/registration, not HTTP endpoints |
| `routes/inventory.php` | 0 | Empty inventory route file |
| `routes/stock.php` | 0 | Empty stock route file |
| **Total** | **1,580** | Static declaration-line count only |

The recursive tree was not truncated and contained 7,920 tracked files. The route file set above is complete for the current tree.

## 2. Route-provider registration and top-level boundaries

`app/Providers/RouteServiceProvider.php` registers these route files:

| Route file | Prefix | Provider middleware | Controller namespace |
|---|---|---|---|
| `web.php` | none | `web` | `App\\Http\\Controllers` |
| `api.php` | `/api` | `api` | `App\\Http\\Controllers` |
| `admin.php` | `/admin` | `web, auth, schooladmin, privilegeconditions` | `Admin` |
| `setting.php` | `/admin` | `web, auth, schooladmin` | `Admin` |
| `inventory.php` | `/admin` | `web, auth, schooladmin, privilegeconditions` | `Staff` |
| `stock.php` | `/stock` | `web, auth, stockkeeper` | `Stock` |
| `librarian.php` | `/library` | `web, auth, librarian` | `Librarian` |
| `student.php` | `/student` | `web, auth, student` | `Student` |
| `teacher.php` | `/teacher` | `web, auth, teacher` | `Teacher` |
| `receptionist.php` | `/receptionist` | `web, auth, receptionist` | `Receptionist` |
| `accountant.php` | `/accountant` | `web, auth, accountant` | `Accountant` |
| `payroll.php` | `/accountant` | `web, auth, adminaccountant` | `Payroll` |
| `superadmin.php` | `/superadmin` | `web, auth, superadmin` | `Superadmin` |

Important inclusions:
- `routes/admin.php` includes `addon.php`.
- `routes/api.php` includes `teacherapi.php`.
- `routes/web.php` includes `tagore.php`; Tagore routes apply their own `auth` and `TagorePerformance` middleware and `/tagore` prefix.
- The inspected `RouteServiceProvider::map()` does not register `routes/tagore_api.php`, and no include reference to that file was found in the inspected route files. Treat it as a likely orphan/unregistered route definition until confirmed by a runtime `php artisan route:list --json` export. Do not count its declarations as live endpoints yet.
- `inventory.php` and `stock.php` are empty in this baseline even though provider groups exist for them.

## 3. Middleware and role-boundary observations

The legacy route provider enforces role-family middleware at the route-file boundary. The middleware aliases are declared in `app/Http/Kernel.php`, including `schooladmin`, `schoolsubadmin`, `teacher`, `student`, `parent`, `librarian`, `receptionist`, `accountant`, `stockkeeper`, `adminaccountant`, `privilegeconditions`, Laratrust `role`/`permission`/`ability`, and `auth`.

Static review observations:
1. Legacy role middleware generally checks the user's role and redirects other roles. This is a coarse portal boundary; it does not itself demonstrate record-level institution, class, section, academic-year or child-link enforcement for each action.
2. Admin main routes have `privilegeconditions`, but settings routes do not. Confirm whether academic-year/standard preconditions are intentionally bypassed for every settings action.
3. Payroll routes share the `/accountant` URL prefix but use a separate `adminaccountant` middleware. That middleware hard-codes user-group IDs 11 and 3 for access, then redirects IDs 1, 5, 6 and 10. This ID-based policy must be reconciled with the modern role system and tested against changed/seeded role IDs.
4. The teacher API has several role-specific nested groups (`principal`, `leave_checker`, `student_leave_checker`) in addition to its provider-level API mapping. Effective middleware must be checked from the runtime route manifest, not inferred from the source line alone.
5. The Tagore route group is authenticated but route-level auth is not evidence of tenant/class scoping. Each controller action must be checked for the relevant institution, academic year, class, section, child-link and object ownership rules.
6. No route declaration count in this document is a permission test. Every mutation and export still needs action-level authorization and cross-institution negative tests.

## 4. Central authorization registry findings requiring validation

Static review of `app/Providers/AuthServiceProvider.php` found:
- The policy map declares `App\\Models\\User` multiple times, mapping it to `AdminPolicy`, `ParentUserPolicy` and `UserPolicy`. PHP associative-array duplicate keys resolve to the last entry, so only the final mapping is retained in the effective array. Confirm intended user policy coverage and consolidate it explicitly.
- The `post_reply` Gate callback accepts `($user, $post)` but its body references `$post_reply`. This appears to be an undefined-variable defect and likely breaks or invalidates the intended post-reply school-scope check. Add a focused unit test and correct it only in a separately reviewed implementation change.
- Many Gates compare `$user->school_id` with a record's `school_id`. That is a useful legacy school boundary but is not equivalent to the multi-institution/group model required by Tagore; it does not automatically provide class/grade/academic-year scopes.
- Policy mappings and Gates are not evidence that every route invokes the corresponding policy. Route-action-to-policy usage remains to be inventoried.

These are static-source findings. They have not been exercised against a running app or database in this research phase.

## 5. Next audit work

1. Generate and retain a runtime `php artisan route:list --json` artifact in disposable CI, including method, URI, name, action and expanded middleware. Resolve the apparent `tagore_api.php` registration gap and any conditional/included route declarations.
2. Join each resolved route to its controller method, request validation, model queries, Gate/policy calls, view/API response, mutation/export action and test coverage.
3. Complete action-level scope review for all legacy Admin, Accountant, Teacher, Student, Parent, Librarian, Receptionist, Payroll and modern Tagore surfaces. Include cross-institution, cross-class, cross-year and unrelated-family negative tests.
4. Audit exports/downloads, API tokens, impersonation, queued work, notifications, listeners, console commands and scheduled paths.
5. Produce a role-by-page-by-action inventory with explicit allow/deny rules and test evidence. Compare every legacy page with the modern Tagore UI before proposing consolidation or removal.

## 6. Audit boundary

This pass inspected all 19 route-definition files, the route provider, HTTP kernel middleware aliases, selected role middleware and the central authorization provider. It did **not** execute the application, run `route:list`, inspect every controller method or every policy body, nor validate runtime authorization. The audit remains in progress. No application code, production data, live database, hardware or external integration was changed.
