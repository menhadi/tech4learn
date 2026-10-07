# Live API/admin release — 7 October 2026

The user's latest explicit request authorized this deployment after the earlier
read-only instruction. Unrelated services and the original ExamElite site were
not changed.

Deployed source: `32b9f679e9433558b005b307a42037202a3848ba` from
`codex/examelite-foundation`. Previous live source:
`01c19c48dbdd5fecc9c92ebcdd01146c88d2fed3`.

API/admin were installed and built separately under
`/home/tech4learn/releases/32b9f679e9433558b005b307a42037202a3848ba` with the
existing Node 24 runtime before switching output paths. A private PostgreSQL
custom-format backup and validated archive listing were taken before explicit
transactional migrations 15–17. Backup, previous compiled outputs and logs remain
under `/home/tech4learn/private-backups/foundation-20261007T093616Z`, outside Git.

The API/admin output paths link to the pinned release and are locally ignored in
server Git metadata. Previous hashed admin assets were retained for open browser
sessions. Only `tech4learn.service` was restarted. Local/public health returned
`ok`, public HTML referenced the new JS asset, and anonymous foundation management
access returned HTTP 401. Stored schema version is 17. The database has four
organisations, sixteen learners and ten attendance records. No demo seed or
identity merging was run; learner/contact/photo values were not copied to Git.

## Native website remains inactive

Laravel source and built attendance assets are on the server, but its production
environment/database and reviewed identity links are not configured. There are
zero foundation organisation links. Do not enable mapped native sign-in first.
Apache still proxies tech4learn.com to Node. Its old direct PHP handler is PHP
8.1; a compatible native runtime and routing need reviewed privileged setup.
The original `/home/examelite/public_html` and its isolated hosts are unchanged.

Deployment access permits application writes and restarting Tech4Learn; it cannot
modify root-owned Apache/PHP settings. Configured root SSH login was unavailable.
Native cutover, canonical learner-to-native-student integration, mobile and FLN
remain unfinished. Deploying source does not establish full module acceptance.

## Rollback

Verify current output paths are links to this exact release before unlinking.
The backup contains `previous-api-dist` and `previous-admin-dist`, plus independent
`api-files` and `admin-files` copies. Restore previous outputs, check out the
recorded previous source, restart only Tech4Learn and recheck local/public health.
Preserve attendance records and the additive schema. Database recovery is a
separate reviewed action; never drop tables or restore the backup automatically.
