# Photo attendance — browser pilot

Migration 5 adds a working responsive browser flow: centre/group selection, live camera preview, automatic capture time and location request, private photo submission, explicit learner marks, review reasons, corrections and daily totals. Enable Attendance in organisation Setup as superadmin. Existing protected organisation admins gain attendance permissions; other roles require deliberate grants in Roles. Attendance access also requires groups.view. Policy management requires organisation-wide scope. Photo viewing is a separate permission.

Use an existing demo organisation and its synthetic learners for an initial walkthrough. Choose a centre, enter its actual test-site coordinates and radius, and approve them with an authorised account. Do not use real learner photos for a dummy-data test: point the camera at a non-identifying test scene. No sample photo or attendance is automatically inserted into live records. Demo removal also removes attendance belonging to that demo organisation, including its photos and reviews.

## Capture and review

- Open the site over HTTPS on a phone; allow camera/location permissions. Capture uses getUserMedia, not a gallery upload. Capture time and GPS cannot be edited in the UI.
- A server-created ten-minute intent binds the group, actor, roster, centre policy and field definitions. Submitted evidence cannot be replaced. Exact retry is idempotent; a changed retry is rejected. Capture time before the intent or too far in the future is rejected.
- Location is checked against the approved centre coordinates and radius. Missing/unapproved, outside-radius, inaccurate, stale or boundary-uncertain readings require review. Submissions delayed over five minutes also require review. A reading should be within one minute of capture. A village name is not used for the distance check.
- Default maximum uncertainty is 50 metres, configurable from 5–1000 metres. Time zone defaults to Asia/Kolkata. A policy can prohibit review by the capture owner. Historical centre/field/roster evidence stays unchanged when settings or enrolments change; stricter current self-review restrictions still apply.
- Attendance custom fields are filled at submission. Reviewers explicitly select Present, Absent or Excused for every learner. Warnings require acknowledgement and a reason. Confirmed corrections require a reason and preserve prior marks, reviewer and timestamp. A rejected pending capture can be replaced by a fresh capture. Confirmed attendance is corrected, not rejected.
- One pending/confirmed record per group per local calendar day. Daily totals include confirmed marks only; a missing capture never creates an absence. Lists, details, photos, submission and review resolve current membership and centre/group scope on every request. Historical records follow the captured group, even if a learner transfers later.

## Operational limits

This release is an online browser pilot, not the native app or recognition engine. Browsers can expose virtual cameras and spoofed GPS; fresh-preview UX is not device attestation. There is no face recognition, register OCR, CCTV, bulk marking or offline persistence. Losing the page before submission loses its unsaved capture. An ignored browser permission prompt can remain pending; close the tab to cancel it.

No new infrastructure or runtime dependency is introduced. JPEGs are compressed client-side to at most 256 KiB, checked for bounded JPEG structure/dimensions, and stored in a private PostgreSQL table outside list payloads. This is container validation, not a full image decoder. Photo responses are authenticated, no-store and nosniff. Pilot capacity is 1,000 photos per organisation and 500 learners per group; there is no automatic photo deletion or retention promise. Backups contain photos and must remain private. Plan object storage, retention/deletion controls and quotas before broad rollout; these limits are not a scalable final media architecture. Abandoned empty intents are cleaned on new capture after ten minutes and capped at 100 active intents per organisation.

## Deploy and verify

The user deploys a checked commit and runs `deploy/virtualmin/update-attendance.sh` as root. It backs up only tech4learn_app, builds Tech4Learn, applies migration 5 and restarts only tech4learn. It does not configure ExamElite or shared Apache/Python packages. A failed deployment exits; inspect its error before retrying. Database restoration requires an explicit operator decision, not an automatic rollback.

Automated tests exercise PostgreSQL migrations and real HTTP auth, scope/media denial, duplicate retries, expired intents, required custom fields, warning acknowledgement, review restrictions, roster completeness, corrections, historical enrolment changes and demo removal. Camera/GPS hardware accuracy still requires a phone field test after deployment. Test inside/outside the radius, denied permissions and a weak reading, then verify the corresponding warnings with a separate reviewer.

Browser API references: [camera capture](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia) and [location reading](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation/getCurrentPosition).

Centre setup now requests device location automatically when no coordinates exist. Browser permission is required. Existing coordinates stay unchanged unless Use my current location is chosen; manual correction and draft restoration take priority over an outstanding GPS request. Saving still requires the existing separate, permission-checked location approval. Device location must be captured at the centre; radius remains a configurable policy value (default 100 metres), not something GPS can determine.

Existing centres have a separate Update location action and scoped PATCH /centres/:id/location endpoint. Only coordinates and radius are updated; name, address and type are preserved inside the organisation transaction. Changed locations require approval again. The Add centre form opens only on explicit selection, so location updates never request a new centre name.

Attendance results display the snapshotted GPS uncertainty limit and centre radius separately, with a direct Change GPS accuracy limit shortcut for authorised policy editors. Changing centre radius does not change GPS uncertainty. Existing evidence remains tied to the policy captured when the session started; the UI identifies a newer GPS limit when one exists.

Confirmed records now show a saved confirmation with Present, Absent and Excused counts from server-saved marks. Test records explicitly remain excluded from daily totals. The instruction to confirm appears only for pending test records; comparison tools are collapsed after confirmation and remain available for optional corrections. Editing draft marks does not change the saved summary until Save correction succeeds. This UI change requires deployment; it does not alter attendance policy or existing records.
