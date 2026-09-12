# Demo data in an existing organisation

Use the privileged CLI `demo-academic ORG_ADDRESS --confirm-name="Exact organisation name"` after deploying the academic release. It resolves the unique address, verifies the stored display name, locks that organisation and creates only its records. A mismatch or collision rolls the whole transaction back. It does not create organisations, users, invitations, role grants, photos, attendance marks or assessment results, or change module settings.

For the user's test organisation, the address shown is `vector-academy`, with display name `Vecotrial Career Academy`. The command adds one DEMO academic year, two DEMO centres (school/coaching), two classes, four sections and eight labelled synthetic students, including required learner custom-field sample values. Locations remain unset and unapproved; configure the actual test location before checking location verification.

An audit manifest records all created IDs. Repeating the command returns that manifest without duplicating or overwriting data. Existing records are preserved. For later removal from active screens, archive the demo students, sections, classes and centres/year through the admin interface, in that order. There is no bulk permanent-delete command for a real organisation; do not use `demo-remove` for this seed.

Current testing preference: use only Vecotrial Career Academy for live dummy data. Local isolated automated fixtures may use synthetic organisations to test tenant isolation. Live access by the assistant remains read-only; the user runs the command.
