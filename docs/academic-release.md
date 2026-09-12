# Centres, academic years, classes and sections

Migration 7 adds centre type, organisation academic years, classes at a centre for a particular year, and nullable class links on existing learning groups. Linked groups are sections. Existing group IDs, student enrolments, private photos and attendance snapshots are preserved. No data is automatically assigned to a guessed class/year.

## Working flow

1. Open Centres and create/edit the centre type: School, College, Coaching centre, Community centre, Learning centre, Training centre or Other.
2. Open Classes & sections → Academic years. Organisation-wide staff with `groups.create` can create a named year with start/end dates. Year metadata is shared within the organisation, like field definitions; it contains no student records or counts.
3. Add a class by selecting its centre, year and name. Classes are unique by case-insensitive name within that centre/year. The same class name can be used in another centre or year.
4. Add sections under the class. Sections are unique by case-insensitive name within a class. Open Students or Photo attendance from the section's actions. These actions also require the relevant module and staff permission.
5. Register/import a student or create/open the synthetic test student in that section. The chooser supports centre/year/class filtering. Student lists can be filtered to a selected section. A linked section supplies the student's class label when saving or transferring.

Existing records appear under Unassigned groups until an authorised administrator links them to an active class at the same centre. This preserves the group ID, assigned staff scope, custom field values and learner links. The existing groups API remains compatible with unassigned groups for non-class programmes.

## History and access

Group permissions now describe class/section actions in the permission catalogue. Centre-scoped staff can create classes and sections only at their permitted centres; section-scoped staff can see only classes containing their assigned sections and cannot create other classes/sections or attach a legacy group. Existing grants are not changed. New sections are not automatically added to an existing section-scoped staff member's scope.

Class/year links cannot be moved once set. For a new academic year, create a new class/section and transfer students through the existing enrolment operation. Class/year names and dates have no edit operation in this release. Archive active sections before a class, and classes before a year or centre. Archiving a section still requires transferring or archiving its active students first. Archived names remain reserved to avoid historical ambiguity. Nothing is permanently deleted by these archive actions.

New attendance snapshots include the year/class/section display label. Old snapshots remain byte-for-byte unchanged. Old student free-text class labels are not bulk-rewritten when linking an existing group; a later save/transfer derives the label from the linked class. Current section context is visible in directory and enrolment labels.

Database composite foreign keys prevent cross-organisation/year/centre links independently of application checks. Duplicate checks and changes run under the organisation lock. This is application tenancy plus relational constraints; PostgreSQL RLS is still not implemented.

## Verification and rollout

`npm run check` covers the complete suite, including migration preservation, centre type/date validation, duplicates, cross-tenant IDs, assigned-section visibility, section filters, attendance snapshots, archive ordering and demo cleanup. Local browser verification created a synthetic year/class/section and dummy student, confirmed the derived class label and section filter, and opened photo attendance with the same section selected. No real photos or camera capture were used.

The user deploys an exact checked commit using `deploy/virtualmin/update-academic.sh`. It backs up only `tech4learn_app`, builds, applies migrations through 7 and restarts only `tech4learn`. No assistant writes to production and no ExamElite/server-wide configuration changes.

This release does not add face enrolment/recognition, the student portal, editable API credentials, FLN or the ExamElite connection.
