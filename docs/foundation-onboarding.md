# Fresh native organisation onboarding

Status: implementation plan, not working automatic onboarding. Exams remain in
Laravel; attendance uses the existing canonical API. Old organisations must not
be imported or adopted. FLN/mobile remain later work.

## Required sequence

1. Create the new native organisation with its existing SaaS workflow. Confirm
   active non-primary status in the native database. Provision a fresh attendance
   companion explicitly; the existing operator command is idempotent and refuses
   old mappings, occupied reserved slugs and platform realms.
2. Review an active native tenant administrator and its membership. The checked
   attendance administrator command creates a fresh ordinary canonical account
   and explicit staff link. It never adopts an existing email account or resets
   passwords on retry. Student accounts are separate from staff accounts.
3. Provide centre/location approval and class/year/section setup using existing
   attendance academic APIs and permission checks. Define an explicit native
   group-to-attendance-section ledger before using native group references.
   A native group has no inherent centre/year identity; numeric IDs or names
   cannot establish that relationship.
4. Create canonical learner enrolment in the reviewed section and explicitly
   link the native student using the existing identity ledger. Native student
   IDs alone are insufficient. The learner API checks active section/centre,
   current tenant ownership and permissions; reuse that validation.
5. Verify actual tenant sign-in, section selection, photo/location capture,
   submission, teacher review, correction history and native exam attempt/result.
   Synthetic fixtures supply references manually and are not automatic onboarding.

## Lifecycle and recovery requirements

Each cross-database operation needs a stable native key and an explicit local
pending/complete/error state. Do not display success for attendance until its
canonical response is verified. A retry after a lost response must return the
same identity, never create a second account/learner or match an existing name.
Do not keep database transactions open while waiting for remote HTTP calls.
Use the existing databases for durable state; no extra queue infrastructure is
required by this plan.

Organisation/user deactivation must deny native access immediately and revoke
its canonical link with version checks. Reactivation requires explicit review;
a revoked mapping must not be silently recreated. Enrolment transfers must
preserve attendance history and recheck current scope. Do not physically delete
new lifecycle records as part of previous-organisation cleanup.

Automatic background provisioning needs a reviewed authentication mechanism;
existing human session cookies cannot become unattended service credentials.
Until that mechanism and durable retries are implemented, use the reviewed
operator tools and label automatic onboarding as pending. The platform identity
realm must never impersonate a tenant staff account for attendance capture.

## Current release boundary

Operator organisation/staff provisioning, explicit learner mapping, native exam
journey and connected attendance checks exist locally. Automatic native hooks,
group/section mapping, canonical enrolment provisioning and deactivation
propagation do not yet exist. Production backup restore, legacy cleanup, native
administrator activation, runtime configuration and Apache cutover remain
separate prerequisites. No original ExamElite records are involved in cleanup.
