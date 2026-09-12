# Multiple class photos and queued attendance suggestions

## Working application behaviour

Migration 9 keeps one attendance session per section/day. The original image is preserved, and staff can add up to four extra camera photos before confirming attendance. Each extra photo has its own server-issued, ten-minute capture attempt, actor, immutable image, capture time, received time and location decision. Images stay in the existing private PostgreSQL storage, bounded to 256 KB each; the organisation storage cap counts original and extra images together.

Open attendance, select **Add another class photo**, save it, and repeat as needed. Then select **Compare class photos**. A queue processes up to 50 roster students and five images using the newest currently checked, consented reference for each eligible student. Stored reference count remains three per student; comparison still uses only the newest checked one. No GPU or new dependency was added by this release.

The server processes one queued attendance job at a time across organisations, one verification request at a time. Maximum work is 250 comparisons per job, with a 12-second deadline for each external request. This is a resource-conscious pilot, not a real-time throughput guarantee. Reference photo checking is a separate operation and is not part of the attendance job queue. A maximum of 20 new jobs per organisation per rolling day limits pilot usage. The engine's own CPU and RAM limits must be configured when it is installed.

## Review rules

- The combined table contains each captured-roster student once. Appearing in several photos adds evidence, not another attendance count.
- Ambiguous or duplicate identity candidates within a photo remain unresolved. If an unresolved candidate implicates a student who matched elsewhere, that student's combined status remains **Needs review**. This cannot detect every confidently wrong match across different photographs.
- **Not yet identified** does not mean absent. All marks require a teacher's explicit review and confirmation.
- Copying suggestions only fills blank marks. Existing teacher selections are preserved.
- Every photo's location warnings are visible and must be acknowledged with a reason. The no-self-review policy includes staff who contributed extra photos.
- Adding evidence increments the attendance version, so an outdated review fails instead of overwriting it. No extra photos can be added after confirmation; existing marks remain correctable through the existing audited review flow.

## Queue, access and retention

Jobs, progress and results are stored in PostgreSQL. Closing the browser does not stop a job; reopening attendance retrieves its latest status. A database lease prevents two API instances from claiming the same worker slot. Queued work survives API restarts. Interrupted processing fails after the lease expires and can be explicitly retried from the beginning; it does not resume halfway through an image or silently repeat engine calls.

Each comparison rechecks the requesting account's current privileges, section scope, consent/reference set, model configuration and attendance version. A change invalidates the job. Result reads also perform current scope and input checks. Partial failed results are not published and attendance marks are never written by the worker. Completed, failed and unstarted jobs expire after one day and are removed by the worker maintenance pass; backups need their own retention policy. Result expiry does not delete original attendance evidence or review history.

Automated tests use a synthetic verification HTTP server. They test a 50-student/two-photo job, duplicate appearances, ambiguous identities, idempotent enqueue and photo submission, reference withdrawal, privilege revocation, expired worker leases, scoped media and review versioning. They do not establish real-image model accuracy or server capacity.

## Deployment and remaining setup

Run `deploy/virtualmin/update-bulk-attendance.sh` from the exact checked commit. It backs up the Tech4Learn database, builds, applies migration 9 and restarts only the Tech4Learn service. No assistant production writes and no ExamElite changes.

The engine is still not installed. The user reports 12 GB RAM and four CPUs on the existing shared server and prefers a pilot there before deciding whether to buy another server. Spare resources, AVX support, workload contention and the concrete pinned container deployment still need checking. `deploy/virtualmin/check-face-host.sh` is read-only. Engine configuration remains the private environment settings described in [student photos](student-photos-release.md); only Vecotrial Career Academy should be enabled for the requested live pilot. No real learner photos or credentials are included in this release.
