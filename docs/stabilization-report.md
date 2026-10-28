# FPRS stabilization audit — 14–15 October 2026

## A. System architecture
Existing procedural PHP pages, PDO/MySQL persistence, shared PHP layouts and Tailwind CDN styling are preserved. Admin verifies users/parlors; directors manage their own selected parlors, packages and inventory; clients search, view details, contact providers and submit feedback. SQL messaging is separate from optional Firebase modules. The live dashboard uses its own budget/religion/location filtering plus CollaborativeEngine; the standalone content/hybrid classes are not wired into its request flow. README, requirements, system diagrams, questionnaire and spreadsheet headers were reviewed. The questionnaire’s 22 service items match the eight implemented survey groups.

## B. Database analysis
SQL owns users, parlors, packages, inclusion rows, inventory, preferences, feedback and imported survey responses. Foreign keys cascade feedback/messages/parlors and set booking/package relationships to NULL; helper deletion transactions explicitly clean legacy parlor-scoped rows. Runtime ensure_* helpers add columns/tables that are absent from setup.sql. The diagram labels some parlor_id relationships as foreign keys, while the legacy SQL schema does not enforce those constraints; the explicit cleanup helpers remain necessary.

Firebase Auth can bridge an ID token into the SQL session; firebase_uid links identities. Realtime Database stores provider ownership and real-time inventory; Firestore chat is a standalone browser module. No live-page imports of these browser modules were found. The checked-in rules apply to Realtime Database, not Firestore. storage/.htaccess declares HTTP denial for the service-account directory; Apache enforcement was not live-tested. Private-key contents were not read or included in this report.

No schema migration or existing database write was performed. Read-only local MySQL inspection failed with connection error 2002, including outside the sandbox. Actual deployed columns, indexes, engines, foreign-key state and existing record counts remain unverified.

## C. Confirmed problems and causes
- Medium: shared Back navigation always used last_dashboard, losing catalog context.
- Medium: declined account rows deliberately hid all actions.
- High: service-area logic accepted seven municipalities and did not validate barangays; direct client routes and the no-package parlor list lacked the same scope check.
- Medium: barangay recommendations had no distance data and retained price ordering among alternative locations.
- Medium: the INC acronym matcher accepted unrelated words such as “includes,” producing a reproducible religion-filter false positive.
- High: survey imports created approved placeholder records with empty business licenses. Director-account licenses and parlor business licenses are distinct fields.
- High: seed/reset/import commands could be invoked through HTTP without an authorization guard; the document extractor could also reveal an uploaded DOCX through HTTP.
- High: Firebase linking trusted an unverified email and could replace an existing identity link.
- Medium: messaging trusted an existing session without rechecking account approval; its limited query returned the oldest 200 messages.
- Medium: saving package inclusions used DELETE followed by INSERT outside a transaction.
- Medium: inventory updates could report success for a foreign resource ID; error-page initialization discarded entered package/inventory fields.
- Low: the environment requirements listed PHP 7.4 despite PHP 8 language features.

## D. Corrections
Catalog Back links and allow-listed admin return destinations preserve the originating list/tab. Declined accounts use the existing confirmation modal and protected deletion handler. Future parlor approvals require a supported, readable business-license file inside uploads and an in-scope barangay. Missing licenses are identified in admin/director listings; directors can edit their listings and upload the missing license. Existing approved records were not bulk modified.

Client catalogs and direct package/contact/feedback routes enforce Guimbal/Miagao scope and account/parlor approval. Director tools remain usable for pending/out-of-scope parlors. New imports are pending and their inserts are transactional. Maintenance scripts require CLI. Firebase email linking requires verified ownership and refuses replacement of another UID. Messaging rechecks approval and validates payload/recipient. Package/inclusion saves are transactional; resource ownership is checked before update. INC now matches a whole word; the existing religion criteria remain intact. The client package catalog uses the joined parlor location instead of an extra lookup query. No latency improvement is claimed without a benchmark.

### Modified files
| Area | Files |
|---|---|
| Shared logic/layout | includes/functions.php, includes/header.php, includes/iloilo_locations.php; new location_policy.php, document_policy.php, navigation.php, barangay_adjacency.json |
| Admin | manage_clients.php, manage_directors.php, manage_parlors.php, manage_all_accounts.php, verify_user.php, parlor_action.php, user_action.php, serve_document.php, shops.php, shop_action.php |
| Client | dashboard.php, _package_result.php, package_details.php, contact.php, feedback.php |
| Director | dashboard.php, account.php, funeral_parlors.php, funeral_register.php, packages.php, package_action.php, inventory.php, inventory_action.php |
| APIs/maintenance | api/messages.php, api/firebase_auth_sync.php; scripts/seed_users.php, reset_passwords.php, import_survey_data.php, extract_docx.php; new build_barangay_adjacency.js |
| Requirements/recovery | requirements.php, .gitignore, this report; protected local backup/manifest |
| Tests | new policy_test.php, recommender_test.php, endpoint_fixture.php, run.js, viewport_test.js and tests/.htaccess |

## E. Responsiveness
Existing Tailwind configuration/design is preserved. New Back/delete controls have mobile touch targets; recommendation text can wrap and shrink. The global navigation Back control is removed to reduce narrow-header crowding. Representative admin, director catalog, director package form and client search screens passed 28 fixture checks at 320, 375, 640, 768, 1024, 1280 and 1536 CSS pixels in the installed headless Firefox. Modal and location-dropdown interactions were checked. No additional browser dependency was installed. Other screens, physical devices, real uploads and complete authenticated browser journeys remain unverified.

## F. Backend/database validation
PHP 8.2.12 and PDO SQLite are available. Syntax checks and isolated fixtures passed; SQLite does not certify MySQL-specific migrations, concurrency, deployed credentials, Apache directives or Firebase permissions. Live registration/login/database/Firebase workflows remain unverified because MySQL is unavailable. No real account deletion or maintenance command was run.

## G. Recommendation validation
Budget tolerance (15%), religion categories, exact/alternative result grouping, municipality-only survey ranking and collaborative scoring are preserved. Whole-word matching fixes the INC/includes false positive. For a barangay search, each existing result group sorts by fewest crossings, then price, then package ID. Unknown distances are last.

Source: [GeoRisk PH / PSA Barangay Boundary layer](https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4).
The source describes indicative boundaries observed June 2016, based on the 2015 census. All 33 Guimbal and 119 Miagao dropdown barangays map to polygons. Exact shared non-zero boundary segments produce 376 undirected edges and one connected component of 152 nodes; corner-only contacts are excluded. Paths stay within the two municipalities. These are administrative-boundary estimates, not drivable routes, kilometers, travel times, or proof of current legal boundaries. Local LGU review is needed before treating the geometry as current authoritative data. The local JSON includes original source names, codes, retrieval date and a source-response SHA-256. The Node generator downloads and outputs data for review; it does not overwrite files.

## H. Regression verification
- PHP syntax: all 56 project PHP files passed.
- JavaScript syntax: all 13 project JavaScript files passed.
- Policy checks: scope coverage, symmetric graph edges, shortest-path invariants, distance ordering, return-URL restrictions and temporary document-path/license fixtures passed.
- Existing engines: content scoring, maximum-distance exclusion, cold start, survey/feedback blending and hybrid weighting fixtures passed.
- Endpoint fixtures: 30 scenarios passed using SQLite in memory and mocked Firebase verification. These cover declined-account deletion, CSRF rejection, self-delete prevention, license/scope approval guards, cross-director ownership, package/inclusion commit and injected-failure rollback, zero-stock status, direct-link scope denial, pending/out-of-scope parlor director access and explanatory messages, client catalog/dashboard visibility, latest-message ordering, malformed messaging input, verified/unverified/conflicting Firebase links, maintenance HTTP denial and INC acronym matching.
- Browser fixtures: 28/28 layout/interaction checks passed in Firefox 140.17.0. Actual application layout templates used fixture records, with CSS viewports of 320, 375, 640, 768, 1024, 1280 and 1536 pixels. No horizontal document overflow was measured. Declined-account modal open/Cancel and cascading municipality/barangay options passed. Small viewports used frames because Firefox desktop clamps its outer window to 450px; these are CSS viewport tests, not physical-phone tests.
- Sample admin/client mobile and director-form tablet screenshots were visually inspected.
- All 46 tracked change hashes matched after implementation; no concurrent file edits were detected.

Run the isolated backend suite with `node tests/run.js` and the optional browser fixture suite with `node tests/viewport_test.js`. Neither uses MySQL records. Test scripts use temporary fixtures/profiles; browser artifacts and results are under the protected .codex-review/20261014-stabilization/viewport/ folder.

## I. Outstanding risks
- MySQL live schema and complete end-to-end workflows unavailable.
- Firebase network authentication, deployed RTDB/Firestore rules and real-time SQL synchronization unverified.
- Concurrent saves of the same record still use the last submitted value; transactions prevent partial package/inclusion writes but do not merge competing edits.
- Runtime schema mutations and repeated inventory ALTER TABLE statements remain; moving migrations out of page requests needs a separately planned migration/rollback.
- Local deployment still uses root MySQL credentials, default seed passwords and a non-secure session cookie; HTTPS production configuration must be verified.
- Existing imported/approved records need an administrator to review missing licenses and locations; no destructive repair or bulk status change was performed.
- Boundary data is indicative/dated and does not model roads or physical access.
- Browser checks cover four representative fixture screens; full browser journeys, physical-device behavior, accessibility audits and performance benchmarks remain unverified.
- Database-failure responses and other malformed-input paths are not fully validated.
- Firebase ownership rules do not themselves check SQL approval status; revocation synchronization needs live verification before those optional clients are enabled.
- Git is unavailable in this shell. SHA-256 baselines/backups were used to protect existing edits; no commit, reset or push was performed.
- README/diagrams mention funeral booking relationships, but no booking creation/scheduling controller was found; no speculative workflow was added.
- The existing hybrid class is not called by the live client dashboard; it was not wired in because that would change the intended criteria.

## J. Status
**NOT READY for production certification.** Targeted changes passed the available syntax, isolated backend and representative browser checks. Live MySQL/Firebase validation, production security configuration, and complete end-to-end coverage remain significant release-verification gaps.

## Change recovery
Affected existing files were backed up before writing under .codex-review/20261014-stabilization/original/. A hash manifest records original and applied versions. Restore only the intended files after coordinating with collaborators; never replace a file whose current hash differs from the applied version without reviewing those newer edits. New files are listed in the manifest. Database rollback is unnecessary because no existing database changes were performed.
