# Shared forms and table template

Use `DraftForm` and `DirectoryTable` (or `SmartTable` for existing table markup) for new admin record screens. This is the common UI standard across organisations and modules.

## Forms

Keep one record's editable details in a single form with labelled fieldsets and a clear final save action. Learner enrolment combines student details, centre/class/section, guardian and custom fields, camera/upload and named portrait uses. Existing transfer/archive operations remain separate because they have their own permissions and audit meaning.

Drafts save automatically on the current browser/device, scoped by signed-in user, organisation and record. On reopening, offer Restore draft and Discard saved draft. Drafts expire after seven days and are cleared on sign out. They are not server records, do not sync between devices and do not provide a full offline application. If storage is unavailable/full, show that explicitly. Preserve drafts after failed writes; clear them after confirmed saves.

Never persist passwords, API keys, tokens or consent/confirmation acknowledgements in drafts. Explicitly model controlled fields with `draftState`/`restoreState` when native input restoration is insufficient. Learner portrait previews and organisation logos have explicit draft handling; arbitrary file inputs and classroom capture batches are not automatically persisted.

Learner save creates/updates the student first, then saves the prepared photo against that ID. A photo failure retains the prepared photo for retry; it does not undo an already saved student. Face checking remains a subsequent operation with its own visible result.

## Tables

Provide meaningful column headings, zebra rows, automatic field filters, search, Reset filters, sorting, 50/100/500 rows per page and match/page counts. Keep the first column and final Actions column fixed while scrolling horizontally. Show all available authorised learner fields including custom fields. Do not expose contact fields to users without contact permission.

Local tables filter the complete supplied collection. Large server-paged collections must use the remote query mode: learner directory, daily attendance and change history now return authorised total and filtered counts from the API. Dates and organisational/location scope constrain the collection before these counts. Sort/filter expressions are whitelisted, values parameterised and access enforced server-side.

Keep editable table rows mounted when paged/filtered so view changes do not destroy unsaved inputs. Exclude table search/filter controls from form drafts. Reset table filters must not submit its enclosing form. Audit export currently exports up to 50 records from the selected offset and is labelled accordingly.
