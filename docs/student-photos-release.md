# Student photos and face-verification pilot

Migration 8 adds private profile/reference images and purpose-specific consent. Existing student fields and typed custom fields remain configurable. In the student directory, choose **Photos & attendance** (or open a saved profile → **Photos & attendance setup**). Record the appropriate permission before uploading; the recorded staff identity and time appear in audit history. Consent recording is an operational record, not proof of a guardian's identity or a legal-compliance certification.

## Photo setup workflow

1. Choose **Open camera** and **Take photo**, or **Upload photo**. Inspect the preview and retake/discard if needed.
2. Give the photo a name, such as Front view. Select profile picture, attendance face matching, or both. A single portrait can serve both purposes without another upload.
3. Confirm permission for the selected uses and save. Both uses are saved together or neither is saved. Profile selection replaces the current profile picture; attendance supports three distinct reference portraits.
4. When configured, **Save photo & check face** checks the saved reference automatically. A failure clearly distinguishes a saved photo from an unsuccessful face check; retry from the saved card. No attendance mark is created by this process.
5. Identical images appear together with their profile/attendance badges. Purpose-specific removal and consent withdrawal remain available. The capture source does not prove identity or presence; classroom group-photo evidence is captured separately.

Migration 11 is required for photo names. Browser camera capture is implemented; actual phone camera/device checks and real recognition accuracy still require the pilot user's testing.

## Working application features

- One replaceable profile image and up to three attendance reference images per student; separate consent for each purpose. Withdrawing consent removes that purpose's stored photos. Individual removal deletes the private image. Database backups require their own retention process.
- Images are resized/re-encoded in the browser, previewed before upload and stored as bounded private JPEG bytes in PostgreSQL. The server checks size/container/dimensions and removes pre-scan APP/comment segments. Browser brightness checks flag extreme lighting. This does not score blur, pose or authenticity, and container parsing is not full pixel decoding.
- Reference Check face uses the configured engine to require one face with a sufficiently large box and detection score. Profile photos cannot be sent through this operation. Dummy learners cannot receive real face references; create a separately consented test student in Vecotrial Career Academy. No dummy avatars or generated images are enrolled as real identities.
- New permissions: `learners.photos`, `learners.photo_manage`, `attendance.match`. Existing protected organisation admins receive them; other roles require explicit grants. Fresh permissions and centre/section scope apply to every read/write and are rechecked after remote operations. Photo URLs are authenticated and no-store.
- Attendance → Match enrolled faces generates numbered face-box suggestions from checked references belonging to the captured roster and still enrolled in that section. Only consenting, active, non-demo students are used. Unknown, close-score and duplicate matches remain unresolved. The reviewer can copy suggested present marks into the existing review form; this does not confirm attendance or infer absences.

## Private engine connection: not installed by this release

This pilot uses CompreFace's **stateless verification service** (`/api/v1/verification/verify`), with no face collection enrolment or remote stored examples. References remain in Tech4Learn; request data is sent only to the private engine. Disable request/body logging on that engine and its proxy. No age, gender, embedding or other optional plugins are requested or retained. See the [official REST documentation](https://github.com/exadel-inc/CompreFace/blob/master/docs/Rest-API-description.md#face-verification-service).

This supersedes the earlier collection-based candidate for the bounded pilot. It avoids external enrolment/deletion synchronisation, at the cost of more comparisons. The newer [bulk attendance release](bulk-attendance-release.md) supports 50 roster students and five photos per session through a persistent queue, using one newest checked reference each, one comparison at a time and a 12-second per-call timeout. Three references can be stored, but this release does not fuse all three during matching. A larger-scale collection/embedding architecture requires separate validation.

The user now requests an initial pilot on the existing server, with a separate private server optional later. It is not installed locally or on production, and real face accuracy/threshold calibration has not been measured. Tests use a synthetic protocol server, not a recognition model. Before installing on the shared host, inspect capacity with `deploy/virtualmin/check-face-host.sh`, reserve resources for existing websites, and prepare a pinned, loopback-only container deployment with CPU/memory limits and a separate volume. Resource limits reduce contention but cannot guarantee no effect on shared workloads. Review the chosen image/model and licences, then perform consented pilot testing and threshold calibration. Installation is not included in this release.

Operator configuration stays in the existing private `/etc/tech4learn/api.env` until the central encrypted API settings page is implemented. Never commit or display the key:

```text
T4L_FACE_VERIFY_URL=http://PRIVATE_ENGINE_HOST:8000/
T4L_FACE_VERIFY_KEY=PRIVATE_VERIFICATION_SERVICE_KEY
T4L_FACE_MODEL=PINNED_ENGINE_AND_MODEL_LABEL
T4L_FACE_ORGANISATIONS=EXACT_ORGANISATION_UUID
T4L_FACE_THRESHOLD=0.90
T4L_FACE_MARGIN=0.10
```

The organisation value must be the exact database UUID, not its name or slug. Only enable Vecotrial Career Academy for the requested live pilot. This must be a verification-service key, not a recognition-service key. Restrict network access to the Tech4Learn API; use HTTPS across an untrusted network. The two numerical defaults are provisional similarity-score filters, not probabilities or measured accuracy. Changing the model label requires rechecking references before they can be used again.

## Rollout and verification

User-run `deploy/virtualmin/update-photos.sh` backs up only the Tech4Learn database, builds and applies migration 8, then restarts only Tech4Learn. No assistant production writes. The profile/consent screens work before the engine is configured; face checks and suggestions report unavailable until configured.

Automated checks cover scoped private media, consent version conflicts/withdrawal, purpose separation, permission revocation, duplicate upload, metadata stripping, single-face validation, provider failures, ambiguity/duplicate matches and unchanged attendance marks. Local browser checks confirmed the new photo controls and separate purpose-specific consent states using synthetic records. Physical-register student import, native photo capture, a student portal and central editable API credentials are separate pending work.
