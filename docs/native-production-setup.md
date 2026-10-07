# Native production setup

The private PHP 8.4 pool is verified. The Laravel production environment,
dedicated MySQL database, native accounts and Apache cutover are still pending.
The existing PostgreSQL attendance service remains authoritative and operational.

`deploy/virtualmin/native-production.env.example` is a secret-free configuration
template. The completed environment belongs on the server outside Git, with
access restricted to the application account. Generate a fresh application key
and database password on the server; do not reuse the original ExamElite secrets.
The native database/account names are `tech4learn_exams`. Grant that account
access only to this database. Keep the existing PostgreSQL database unchanged.

The native attendance gateway requires HTTPS in production. Route `/api/v1/`
to the existing Node service before routing other requests to Laravel; use
`https://tech4learn.com/api/v1` as the gateway destination. A loopback HTTP
destination is allowed only in local development.

Before migration, verify the dedicated MySQL database is empty and retain a
private backup. Run native migrations explicitly as the application account,
never through a public endpoint. A fresh database does not gain a working
administrator from `DatabaseSeeder`; it also initially receives the upstream
`examelite.com` organisation domain. Both require reviewed native setup before
cutover. Do not promote an existing account or merge identities by email.

After environment/database setup, run:

```bash
php8.4 deploy/virtualmin/check-native-readiness.php /absolute/checked/release/platform
```

This CLI check parses the private environment without booting Laravel, refuses
unexpected database targets, and uses a read-only transaction to check applied
migrations, the canonical domain and an active native platform administrator.
It prints neither secrets nor learner records. Exit zero indicates these
prerequisites passed, not that the complete website is ready.

Still required before cutover: durable writable storage, reviewed organisation
and staff identity mappings, an appropriate platform sign-in path, background
worker/scheduler configuration, and actual exam and attendance acceptance checks.
Learner links must refer to verified native students and existing canonical
learners at the same organisation. Never copy production records into local
fixtures or infer links from names/emails. Retain the existing Node routing and
release for rollback until the replacement workflows pass.

Local verification of the readiness checker: PHP syntax passed, a missing
installation failed closed, and the local SQLite development installation was
rejected before any database connection. Its MySQL success path still requires
verification against the separately prepared native database.
