# Tech4Learn native application

This directory holds the ExamElite-derived Laravel SaaS and exam application.
`source-origin.json` records the upstream revision, imported file hashes and
excluded files. The original checkout is not modified. Local working-tree
improvements are retained; this is a product fork, not a remote workspace launch.

The existing attendance/learner backend remains in `../apps/api`, with its
PostgreSQL records preserved. The Expo mobile scaffold remains in `../apps/mobile`.
Attendance identity/session integration and FLN development are pending; copying
this source does not connect the databases or validate all native features.

Vendor dependencies, environments, uploads, databases, raster photographs and
recordings are not included. Standalone legacy `api/` scripts are excluded because
they contain direct database connection details and unscoped database operations.
The supported native API is defined by Laravel routes and controllers.
Bundled MathJax glyphs and CKEditor icons are retained as required code assets.

Embedded framework/provider tokens are replaced by environment configuration.
No OCR.space request is made without its configured key. Provider credentials
and any required question-bank content must be provisioned separately.

Tenant resolution checks the stored platform flag or active membership for the
authenticated actor; a legacy admin role does not grant access to other tenants.
The isolated regression fixture is `../tests/foundation-tenancy.php` and uses
an in-memory database. This covers the central boundary, not full route acceptance.

Do not run the old ExamElite workspace installers to deploy this fork: they can
modify the original ExamElite application. A production deployment/migration and
rollback procedure is required before this replaces the existing site.
