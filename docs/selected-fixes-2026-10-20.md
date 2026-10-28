 # Selected audit fixes — 20 October 2026

Implemented the selected scope from the [audit](qa-audit-2026-10-20.md). F02 remains excluded. No pre-existing application records or review answers were changed.

## Before and after

| Finding | Before | Implemented behavior |
|---|---|---|
| F03 | Invalid/negative prices could become zero. Zero displayed as a peso amount. | Exact decimal validation permits 0–99,999,999.99 PHP and up to two decimal places, matching DECIMAL(10,2). Invalid values fail before uploads or writes. Catalog, details, contact and inquiry-history prices show **Contact for pricing** for intentional zero values. Existing prices are untouched. |
| F04 | Array-valued email/budget could return 500. | Shared scalar guards return controlled 400 responses. Invalid budget values show a validation message with 422 and do not overwrite preferences. |
| F05 | Negative stock became zero; fractions/text could be coerced. | Only whole quantities from 0–2,147,483,647 are accepted, matching signed INT. Invalid quantities produce errors without writes. Safe resource fields are retained. Zero still correctly means Missing. |
| F06 | Some saves accepted missing tokens. | Package, inventory, director account/location, parlor registration/editing, parlor selection and admin shop saves require the shared CSRF token. Forms supply tokens; malformed/missing/invalid tokens return 403. Existing role/ownership guards remain. |
| F07 | Names longer than 200 characters were silently truncated. | Registration and client/director profile names have matching browser/server limits. Invalid names are rejected and safe text retained. Phone limits are 50 characters. Package/parlor/resource names also enforce their 200-character limit. |
| F08 | Sample-license URLs were public. | The entire sample-license folder denies direct HTTP access, including all future filenames. Authorized administrators still use the existing document reader. Public package images remain available. Approval states and license file contents are unchanged. |
| U01–U03 | Registration, director cards and long package titles overflowed. | Flexible children can shrink; long text wraps; narrow-screen controls/actions wrap. Content is not concealed with horizontal-overflow clipping. |
| U04 | Numerous fields lacked accessible names. | Static fields have linked labels and stable IDs; shared municipality/barangay controls have individual labels. Repeated service controls have accessible names without duplicate IDs. Validation messages are announced and relevant errors associated with controls. |
| U05 | Admin shop editing used a different form style. | It now uses the existing management card, spacing, rounded fields and blue Save button. Create/update/token behavior was checked in isolated admin fixtures. |
| X01 | Registration errors discarded safe text. | Name/email/phone are escaped and retained, with field-specific guidance. Passwords and verification file inputs remain empty for resubmission. |
| X03 | Clients lacked account management. | **My Account** appears in desktop/mobile navigation. Clients can update only their own name and phone; email remains read-only. Local password changes verify the current password, require matching new passwords of 8–72 bytes, update a hash scoped to the client, and rotate the session ID/token. |
| X04 | Approval stages offered limited direction. | Pending login/registration explains administrator review. Parlor cards distinguish account approval from listing approval and explain missing license/next steps. Required approval gates are retained. |
| X05 | Inventory pages repeatedly altered schema/status rows. | Inventory requests perform a read-only readiness check. An idempotent, CLI-only versioned migration installs the inventory columns/enums separately and records its version. It was tested on a disposable database before running locally. |

### Firebase account behavior

Linked accounts use a separate in-memory Firebase Auth instance for password changes, verify the expected UID, reauthenticate, then update the Firebase password. The PHP password-change handler rejects linked-account password writes. Provider failures clear password inputs and show a controlled message. This follows [Firebase's password and reauthentication guidance](https://firebase.google.com/docs/auth/web/manage-users).

A previously linked local account may still have a separate local PHP password under the existing dual-login model. Changing the Firebase password does not rewrite that local credential; the page explains this. Non-password providers must manage their password with their provider. Real cloud password changes were **not** performed; SDK behavior was tested with mocks.

## Validation and evidence

- **16/16 live local checks**, using two dedicated synthetic client/director accounts: normal login, malformed requests, invalid price/stock rejection without inserts, valid saves, token enforcement, custom-price rendering, profile isolation, password change and subsequent login, private document access and escaped registration values.
- **85/85 final browser combinations**: 16 live pages plus the isolated authenticated admin shop template, at 320, 375, 768, 1024 and 1536 CSS pixels in installed Chrome. The final matrix combines the original run with the 15 passing rechecks after shared location/license label corrections. No document overflow, unnamed visible fields or duplicate IDs in the checked pages. Client My Account navigation was visible at every tested width.
- A valid 200-character package title and long synthetic parlor name were used in live layout checks. Dynamic service-row addition also worked. The admin shop fixture was visually inspected at mobile size.
- Existing **59 endpoint scenarios**, location/ranking policies, questionnaire-source checks, package-rating JavaScript checks and real MySQL temporary-table inquiry/feedback tests passed. The 22-question questionnaire and repeat-inquiry rules were unchanged.
- **69/69 targeted route scenarios** cover invalid numeric/type/length boundaries, no-write behavior, valid saves, missing/array/bad tokens, profile ownership, password verification and authenticated admin document content. Firebase mock tests cover reauthentication ordering, UID mismatch rejection, mismatched passwords and failure cleanup.
- All **67 PHP and 19 JavaScript files** passed syntax checks at the final syntax pass.
- Anonymous HEAD requests to all three sample-license files returned **403**; an existing public package/inclusion/inventory image returned **200**. The authorized admin document fixture returned a byte-for-byte matching sample file, while a client was denied.
- Inventory migration tests upgraded a legacy schema, preserved expected data, added columns/statuses and verified a repeat run without further changes. Its random disposable database was removed.

Private evidence, source snapshots, SQL backup, HTTP checks, browser screenshots and test manifests: [selected-fix artifacts](../.codex-review/20261020-selected-fixes/). These remain behind the existing HTTP-denied private artifact directory.

### Cleanup and data integrity

Both temporary live accounts and their synthetic records were removed with ownership guards. No uploaded files were created or deleted during this implementation's live tests. All pre-existing rows in the 14 audited application tables still match their recorded hashes, and the original row counts are restored. This includes original parlor feedback, 880 survey entries and all 22 answers of the existing package review. The new schema_migrations table records the inventory migration separately.

## Migration and backup notes

Database backup: `.codex-review/20261020-selected-fixes/before.sql`. Source snapshots are in `source-backup/` within that directory. Keep these private.

For another installation, back up that database and run from the project root:

```powershell
& 'C:\xampp\php\php.exe' sql/20261020_inventory_schema.php
```

The migration is safe to repeat. Normal inventory pages return a controlled maintenance response if the required schema is missing; they do not attempt schema repairs. Legacy `unavailable`/blank inventory statuses become `missing` during migration. The local database already had the current vocabulary, so its existing inventory rows required no changes.

For rollback, restore the changed source snapshots first. The additive inventory schema can remain compatible with the previous source. Do not blindly restore the entire SQL backup over newer user activity; any database rollback must account for records written since the backup.

## Remaining scope and limits

- **F01 was not selected:** permanent package deletion can still cascade into inquiry/review history. This implementation did not delete any real package or review or introduce an archival policy.
- **F02 is intentionally deferred** as requested. X02 post-login routing and X06 broader documentation cleanup were not selected.
- No live administrator credentials were borrowed; administrator behavior was validated through isolated fixtures. Real cloud authentication changes, physical phones and production concurrency/load remain unverified. Firefox automation could not start a session; the completed browser matrix used Chrome.
- Existing director confirmation, one questionnaire per confirmed occurrence, repeat inquiries, ratings and the simplified Package Details page remain in place.
