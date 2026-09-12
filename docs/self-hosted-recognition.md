# Self-hosted recognition evaluation

Status: selected direction, not installed or integrated. No accuracy claim or production approval is implied.

CompreFace is a candidate because it offers a self-hosted REST service for enrolment, recognition and subject deletion. Its documented deployment uses Docker/Compose and an x86 CPU with AVX. Docker was not available in the local development shell, so no engine execution was performed. [Official project](https://github.com/exadel-inc/CompreFace).

## Proposed boundary

Run the service on a separate private host or explicitly resource-limited deployment. Do not install it into the shared ExamElite/Tech4Learn server without inspecting capacity and reviewing the concrete deployment. The browser talks only to Tech4Learn; engine credentials remain server-side. Use one recognition service/collection and unique API key per organisation, with opaque learner subjects. Reject shared collection credentials across organisations.

The documented API supports adding a reference under `/api/v1/recognition/faces`, matching through `/api/v1/recognition/recognize`, and removing subject examples. Recognition returns face boxes and ranked subjects with similarity scores. [REST documentation](https://github.com/exadel-inc/CompreFace/blob/master/docs/Rest-API-description.md).

## Work required before attendance matching is usable

1. Pin and review the engine image and selected model, including their licences, maintenance and dependency security. Project code licensing alone does not establish every model's reuse terms.
2. Add scoped learner reference enrolment, single-face checks, replacement and deletion. Track external operations so retries do not orphan face records.
3. Match only against the organisation's reference collection, then restrict suggestions to the captured section roster. Validate returned subject IDs server-side.
4. Calibrate a similarity threshold and top-two margin using consented pilot examples. Leave unknown, low-confidence and duplicate matches unresolved. Do not infer absence from a missed face.
5. Display proposed matches alongside face crops for an authorised reviewer; only the existing explicit confirmation operation writes marks.
6. Test collection isolation, revoked access, failed enrolment/deletion retries, poor lighting, occlusion, crowded sections, server resource limits and restoration/deletion of stored references.

Do not enable age, gender or other unrelated recognition plugins. A profile photo is not automatic permission to enrol a face reference for every purpose.

The four general vision APIs remain separate tools for quality assessment and document transcription. They are not substitutes for this face-matching service.
