# Tagore ERP Website CMS — Product Scope and Security Contract

**Status:** Approved for inclusion in the integrated ERP implementation scope; detailed implementation and acceptance required.
**Related:** comparative audit PR #161; Gate 1 shared architecture contracts.
**Existing baseline found:** `tagore_cms_pages` is referenced by the platform controller and a basic “Website CMS page” form exists. The generic Operations module also has a `website` record type with CRUD-like fields. These are useful foundations, not a complete website builder. The current evidence does not establish public-site rendering, theme/layout editing, navigation management, media library, custom-domain management, version history, preview/publish workflow or cross-institution isolation for every public route.

## 1. Product objective

Give each Tagore institution an independent public website that its authorised institute administrators can maintain from inside Tagore ERP, without code changes for routine content updates. Keep a consistent Tagore design system and centrally managed safety/security controls while allowing each institution to manage its own content, brand-approved settings and public information.

This is an integrated CMS and publishing platform—not merely a database form for page title/body.

## 2. Users, roles and boundaries

- **Owner:** group-wide oversight, create/retire institution sites, assign website administrators, approve shared themes/components, manage domain/DNS and platform-wide publishing/security settings. Owner may view all institutions.
- **Principal / Institute Admin:** manage only the public site belonging to their assigned institution, including pages, news, notices, events, gallery, staff/faculty directory, facilities, contact details, admission information, downloads and approved homepage sections.
- **Website Editor (delegated scoped role):** optional permission bundle assigned by Owner or Principal for content-only work in one institution. It cannot change domains, global themes, tracking scripts, integrations, user roles or security settings.
- **Coordinator / Content Editor:** may draft assigned content areas only when explicitly granted website content permissions; no automatic publishing privilege.
- **Teacher / Department Contributor:** may submit draft news/events/content for review; no direct publishing by default.
- **External public visitor:** read published pages and submit validated public forms. No ERP authentication or access to private student/staff data.

Every CMS object, media file, draft, preview token, revision, form submission, export and publish action must be institution-scoped. Institution ID is derived from the authenticated user's active assignment and verified server-side; never trust a hidden form field or URL parameter alone. Cross-institution IDOR tests are mandatory.

## 3. Website portfolio and public routing

- One site per institution by default, with support for a Tagore group landing site that links to institute sites.
- Each site has a stable internal site ID, institution ID, primary hostname, optional verified aliases, canonical URL, locale(s), timezone, contact details and lifecycle state.
- Support managed subdomains initially (for example, institute-specific Tagore hostnames) and custom domains after DNS ownership verification and TLS provisioning are automated and monitored.
- Public routing resolves hostname to one active site, then resolves a published page by site and slug. Unknown hosts/slugs return safe 404; no fallback may leak another institution's content.
- Canonical URLs, HTTPS redirects, sitemap.xml, robots.txt, OpenGraph metadata, structured data where appropriate and SEO-friendly slugs are required.
- Domain change, site disablement and redirect rules are Owner-controlled and audited.

## 4. Admin CMS workspace

The ERP navigation should expose **Website** / **Website Management** to Owner and authorised institute administrators. The workspace should provide:

1. Site overview: public URL, publish status, last update, pending drafts, broken links and basic traffic/health indicators where privacy-compliant analytics is enabled.
2. Page manager: create, edit, duplicate, archive, reorder and preview pages; page tree and navigation hierarchy.
3. Visual section builder: assemble approved blocks such as hero/banner, text, image, cards, stats, facilities, principal message, faculty grid, testimonials, FAQs, downloads, events, notices, gallery and contact/location.
4. Theme and brand settings: approved logo asset, colour palette, typography, header/footer, favicon, spacing and responsive preview. Institute editors cannot replace protected group identity assets or inject arbitrary CSS/JS.
5. Navigation manager: menus, nested items, internal/external links, visibility and order.
6. Media library: upload, crop/alt text/caption, folders/tags, replace/version, usage tracking and safe deletion only when unreferenced.
7. News/notices/events: schedule, expire, pin, category and archive.
8. Admissions and enquiry content: editable programme information, prospectus/downloads and public enquiry form; submissions enter the institution-scoped Admissions CRM with consent and spam protection.
9. Contact/location: verified phone/email/address, maps link, office hours and social links.
10. SEO and sharing: page title/description, canonical, social preview, index/no-index and sitemap inclusion.
11. Preview/publishing: private signed preview, draft autosave, review/approval, schedule, publish, unpublish and rollback to an earlier revision.
12. Audit and revisions: who changed what, before/after snapshots, publication history and restore action.

## 5. Page builder and content model

Use structured, validated block JSON rather than storing arbitrary executable HTML as the normal editing path. Each block has a type, schema version, content fields, display options and responsive settings. Renderer supports a curated allowlist of blocks and sanitises rich text/links. Unknown block types render safely as unavailable placeholders and are flagged for editor repair.

Core entities (names are logical; reconcile with existing migrations before implementation):
- `website_sites`: institution, hostname(s), locale, theme, status and SEO defaults.
- `website_pages`: site, parent, slug, title, template, structured content, metadata, status and publish timestamps.
- `website_page_revisions`: immutable revision snapshots, author, reason and created time.
- `website_navigation_menus` / `website_navigation_items`: site-scoped menu structure.
- `website_media`: site/institution ownership, storage key, MIME, size, checksum, alt text, dimensions and usage.
- `website_posts` / `website_events`: news, notices and events with schedule and archive state.
- `website_domains`: hostname verification, TLS state and canonical flag.
- `website_forms` / `website_form_submissions`: public enquiry/contact forms, consent, rate-limit and CRM linkage.
- `website_publish_events`: append-only publish/unpublish/schedule/rollback audit.

Avoid duplicate tables if current `tagore_cms_pages` or `tagore_website_records` can be safely evolved. First map all references, foreign keys, legacy data and public route usage; produce additive migrations and backfill plan. No destructive migration.

## 6. Publishing and security rules

- Draft content is never publicly addressable. Preview uses short-lived signed tokens scoped to one site/page/revision; tokens are revocable and never expose unpublished media globally.
- Publish requires an explicit permission and creates an immutable revision. Scheduled publish/unpublish uses the queue/scheduler and records execution outcome.
- For high-impact pages (admissions fee details, policy, legal/privacy, safety, official leadership/board disclosures), configurable second-person review is recommended; institution Principal is the default publisher.
- Rich text is sanitised with an allowlist. No arbitrary script, iframe, event handler, untrusted embed or CSS injection. Approved embed providers require Owner configuration.
- Media upload checks MIME by content, extension, file size, image dimensions and malware scanning where available. Store outside executable web roots; serve with safe headers and authorised paths.
- Public forms validate server-side, use CSRF where session-based, rate limiting, honeypot/CAPTCHA strategy, consent text/version, retention policy and spam controls. Never accept student records or payment details through generic forms.
- Public site renderer must never query or expose private ERP tables directly. Publish only approved CMS snapshots and explicitly public fields.
- All write operations and public-form CRM transfers are audited. Secrets, credentials and private preview tokens are never logged.
- Custom domains require DNS challenge/ownership proof and TLS issuance before activation; no insecure HTTP-only custom domain.

## 7. Design and usability requirements

- Modern, responsive, accessible public templates with consistent typography, spacing, navigation, clear calls to action and fast mobile rendering.
- Provide ready-made institute templates suitable for CBSE/RBSE schools, coaching/Vedant, PG College and hostel, while allowing per-institute content differences.
- Admin editor should be approachable for non-technical school staff: live preview, autosave, reusable sections, media picker, undo/redo, validation and clear publish state.
- Group brand system protects official Tagore logo assets; site-specific content cannot overwrite master assets.
- Support English and Hindi content per page/site, including correct Unicode, locale metadata and language switcher.
- Performance: image optimisation/responsive variants, caching of published snapshots, cache invalidation on publish, lazy loading and Core Web Vitals monitoring.
- Accessibility: keyboard-operable editor and public site, labels/alt text, contrast, heading hierarchy and WCAG 2.2 AA target.

## 8. Migration and integration

- Inventory current public school websites and domains, their page structures, media, SEO metadata and forms before migration.
- Build import tooling for existing site content and media with preview, mapping, duplicate detection and rollback; do not overwrite live sites during development.
- Public admissions forms create leads in the existing Admissions CRM with institution/source attribution and consent evidence.
- Public events/news may optionally feed internal ERP communications only through explicit publish controls; internal social posts must not automatically appear on public websites.
- Website pages may reference approved public documents and institution details, never private student/staff files.
- Keep each institution website deployable under one shared application release; a site content edit must not require a code deployment.

## 9. Acceptance scenarios

1. Owner creates/activates two institution sites and assigns one Principal/Website Editor to each.
2. Institute A editor creates a page, saves draft, previews it using a signed link, submits review, publishes and sees it at A's hostname.
3. The same user cannot list, read, edit, preview, publish, archive or download media from Institute B by changing IDs, slugs or request payloads.
4. Draft pages and draft media are inaccessible without a valid scoped preview token.
5. Publish creates a revision; edit/publish again creates a second revision; rollback restores the earlier content with a new audit event.
6. Navigation ordering, nested links, mobile menu, Hindi/English content, SEO metadata, sitemap and canonical host work correctly.
7. Public enquiry form applies validation, rate limit, spam protection and consent, then creates a lead in the correct institution CRM only.
8. Upload rejects executable/mismatched MIME files, oversized images and unsafe SVG/HTML; valid images receive safe variants and alt text.
9. Custom domain remains inactive until verified and TLS is valid; hostname cannot be claimed by another institution.
10. Public pages render only published snapshots and continue to work with ERP private tables inaccessible to the public runtime.
11. Cache invalidates on publish/unpublish and scheduled publication works after queue/scheduler restart.
12. Desktop, tablet and mobile browser acceptance passes with no horizontal overflow, broken links or console/5xx errors.

## 10. Delivery track and release gates

Create a dedicated **J — Website CMS & Public Site Platform** workstream, integrated against the shared architecture contract:
- J1 current CMS schema/controller/routes/public-site evidence audit and safe migration plan.
- J2 institution-scoped roles/permissions and audit events.
- J3 structured page builder, themes, menus, media library and revisions.
- J4 public hostname resolver, renderer, SEO, caching and custom-domain/TLS lifecycle.
- J5 news/events/admissions forms and CRM integration.
- J6 migration/import tooling and institute-specific templates.
- J7 automated security, content-safety, SEO, accessibility, responsive and multi-tenant acceptance.

Do not declare the existing generic website CRUD or single page form to be a completed CMS. The website builder is a P0/P1 integrated workstream; no custom domain or public launch until hostname isolation, content sanitisation, preview/publish and restore tests pass.
