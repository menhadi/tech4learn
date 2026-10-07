# Native student and canonical learner identity

API migration 18 adds explicit, immutable learner links alongside the existing
organisation/staff links. PostgreSQL remains the canonical owner of learners,
enrolments, attendance, photo permissions and history. Native students remain
exam identities. This change copies no learner data, matches no names/emails,
and creates no student or organisation automatically.

## Link administration

Stored platform-superadmin authority is required for GET/POST
`/api/v1/platform/foundation/organisations/:native/learners` and PATCH
`/:student`. POST accepts string `nativeStudentId` and existing UUID `learnerId`.
The native student ID must first be verified in the intended Laravel installation
and organisation. PostgreSQL constraints bind both the native organisation link
and canonical learner to the same canonical organisation. Existing links cannot
be reassigned; activation edits require `active` and the current `version` and
are audited. Archived learners/centres/sections cannot be newly linked or
reactivated. GET currently returns the first 500 links.

## Staff resolution

`/api/v1/foundation/organisations/:native/staff/:user/students/:student/learner-identity`
requires the exact mapped API account, active organisation/staff/learner links,
current `learners.view` permission and current centre/section scope. Even a
superadmin cannot impersonate the mapped staff account. The reply contains only
linked IDs and version, never names, contacts, photos or exam answers.

Laravel exposes authenticated `GET /students/{student}/identity` under existing
student page rights. It checks the native student's own organisation and active
state, validates the returned mapping and reads it again before release. Native
authority and stored student state are rechecked after remote work. The response
is private/no-store. Disabling a link, transferring a canonical learner out of
scope, changing permissions or archiving its centre immediately prevents use.

This is a working identity ledger/read boundary, not a complete enrolment
synchronizer or student/mobile sign-in. Mapping administration UI, reviewed
native account provisioning and mobile/student session exchange remain pending.

## Verification and activation

Local tests passed 102 API tests plus typechecks/builds, 262 native tests / 1,155
assertions, PHP boundary checks and the real connected PHP-to-Nest learner,
attendance capture/review and logout workflow. Synthetic regression checks cover
foreign records, database constraints, current permission/scope, immutable links,
version conflicts, archived records and revocation. No production learner links
were created by these tests.

Migration 18 is explicit through the management CLI. Back up the API database
before applying it. It does not activate Laravel or repair its production setup.

## Checked live milestone

On 7 October 2026, `d5d2cd9fa48bc9c3dfac180000f1cd93f48b6deb` was deployed to
the existing API/admin service under the user's continuing deployment authority.
Migration 18 applied after a validated private PostgreSQL backup in
`/home/tech4learn/private-backups/foundation-20261007T101307Z`. Previous source is
`32b9f679e9433558b005b307a42037202a3848ba`; its runtime release remains available
for application rollback. Local/public health and the new compiled public asset
check passed. No production identity links or synthetic learner data were seeded.

The matching release's locked Laravel production dependencies were installed with
application scripts disabled, and Composer platform requirements passed under
PHP 8.4. Production `.env` and the native database were not created. The original
ExamElite website remains untouched. This stages dependencies, not native activation.

## Limited PHP preparation

`deploy/virtualmin/prepare-native-php.sh CHECKED_FULL_REVISION` is a user-run root
preparation step for an already-built release under `/home/tech4learn/releases`.
It creates one private PHP 8.4 pool running as `tech4learn`, with a www-data-only
Unix socket, validates FPM configuration, and performs a graceful FPM reload.
It refuses to overwrite an existing pool and removes its new configuration on
failure. It does not change Apache routing, credentials, databases or identities,
or touch the original ExamElite application. Its shell syntax, non-root refusal
and exact pool configuration syntax were checked; installation still needs root.

The live website stays on Node until native environment/database setup, reviewed
identity provisioning and production routing are ready. Preparing this pool alone
does not complete the cutover.

The staged root command is:

```bash
bash /home/tech4learn/releases/d5d2cd9fa48bc9c3dfac180000f1cd93f48b6deb/deploy/virtualmin/prepare-native-php.sh d5d2cd9fa48bc9c3dfac180000f1cd93f48b6deb
```

The user completed the root preparation on 7 October 2026. Read-only server
verification confirmed the pool configuration exists, PHP 8.4 FPM is active,
and `/run/php/tech4learn-native.sock` has ownership `www-data:www-data` and
mode `0660`. The existing Tech4Learn service is active and its local health
endpoint returned `ok`. The pinned native release still has no production
`.env`; native database setup and reviewed identity provisioning remain pending.
These checks confirm PHP preparation, not a working Laravel production site.
