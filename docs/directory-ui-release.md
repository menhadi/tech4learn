# Directory table refresh

Centres, classes, sections/groups, academic years, students, staff and roles now use semantic tables with labelled columns, row headings, a dedicated Actions column, alternating row backgrounds and status badges. Table borders inherit the organisation brand colour. Tables have a focusable horizontal scroll region for narrow screens. Empty tables show an explicit message.

The class table and section table share centre/year filters. Selecting a year now filters unassigned groups out of the section table as well; select All years to see them. Existing authorisation and archive confirmations are retained. Section and centre edit actions scroll to the editor. The workspace header uses less vertical space.

No dependencies, database migration or demo data changes. Local validation: `npm run check` (60 tests), production admin build, and browser checks of centre/section tables and the section-to-student navigation using synthetic local records. Existing deployment script `deploy/virtualmin/update-academic.sh` can deploy this commit; its already-applied migration checks are idempotent. Production deployment remains user-run.
