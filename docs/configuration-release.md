# Organisation setup and reusable fields

This release adds migration 4. Development and verification happen locally; the user deploys the checked commit. It does not configure live DNS, certificates, Apache, or any other website.

## Working flows

Organisation type choices are School, NGO / Non-profit, Coaching centre / Academy, CSR programme / Foundation, Government department / Programme and Other organisation. Creation and Setup use the same labelled selector; existing stored keys keep their meaning. New organisations require an explicit choice in the web form. The type describes the organisation and does not grant permissions or enable modules.

Superadmins create an organisation with its name, unique slug, type and administrator invitation. The organisation's Setup tab supplies its shared sign-in address (`https://tech4learn.com/?org=SLUG`), editable welcome message, PNG/JPEG/WebP logo and community/academy/minimal presentation templates. Templates change layout treatment without changing records or roles. Existing Profile settings control name, colour and centre terminology. Logo input is limited to 130 KB in the browser and stored with configuration, not committed to Git. Public branding responses contain only deliberately published identity and presentation fields. Slugs are stable after creation.

`configuration.view` and `configuration.manage` are organisation-wide permissions. Protected organisation admins receive them; other roles retain their earlier permissions. The Setup tab keeps module availability separate from staff permissions. Only superadmins can change the module switches. Disabling learners denies learner API actions and hides the tab after reloading access; data is retained. Attendance, FLN and ExamElite switches record future availability preferences and are explicitly labelled planned. They do not activate an engine that has not been implemented.

## Fields across modules

The Custom fields tab supports organisation, centres, groups, staff and learners, plus draft definitions for future attendance, FLN and ExamElite records. Module keys are a server-owned registry; a future module must add its own record authorisation before collecting values. Arbitrary module names cannot bypass record checks. ExamElite definitions are local integration metadata, not a replacement for its question/exam engine.

Existing learner field IDs, definitions, values and import behaviour are preserved. A shared typed validator handles text, number, date, choice and boolean. Keys are unique per organisation and module, with 30 definitions per module. Stable keys, types and existing choices cannot change; labels/required status can change, choices can be appended, and archived values remain readable.

Learner values stay in their existing profile/import workflow. For other working modules, choose an existing record in Custom fields and save its additional details. Required checks apply when saving that additional-details form; they do not yet prevent creation of a base centre/group/staff record before details are filled. Version checks reject stale saves. Record reads/writes use the existing organisation/centre/group scope and corresponding record permissions; staff details require membership permissions. Archived centres/groups cannot be edited. Custom fields share their parent record's access; per-field secrecy and approval workflows are not included.

The migration generalises `learner_fields` into `custom_fields` and retains a filtered compatibility view for learner fixtures. Values for other modules use an organisation/module/record key, with record existence and scope checked in service code. Audits store IDs/actions rather than custom values. Demo removal includes all module definitions and values within the recorded demo organisations.

## Custom domains

Each organisation may reserve one unique hostname. Organisation admins add the displayed `_tech4learn.HOSTNAME` TXT record with the generated proof, then select Verify ownership. Verification uses DNS TXT lookup with bounded resolver timeouts. DNS failure cannot activate a hostname. Verification does not install certificates or change routing. Removal deletes the binding immediately; re-adding generates fresh proof. Keep the verification TXT record while the domain is assigned.

Activation is restricted to superadmins and rechecks DNS proof. The superadmin must first configure and independently check:

1. DNS A/AAAA or CNAME routing for that hostname to the intended host.
2. A valid HTTPS certificate for the hostname.
3. A dedicated virtual host proxying `/` to the existing Tech4Learn loopback service, preserving the original Host header. Do not modify unrelated virtual hosts.
4. The HTTPS page and assets load correctly before confirming hosting readiness in Setup.

The assistant must not perform these live writes. There is no automated certificate provisioning or renewal manager in this release. For a real hostname, inspect the current domain-specific server configuration before preparing exact Virtualmin commands; do not apply generic shared-server changes blindly. Unused DNS/virtual-host configuration should be removed separately when unbinding a hostname.

The application accepts custom-origin writes only when the exact HTTPS Origin matches the request Host and an active, verified database binding exists. Forwarded host headers do not grant trust. Sessions remain host-only; there is no cross-domain SSO. On a bound hostname, the session directory is filtered to the assigned organisation and other organisation routes are blocked. Database membership checks remain authoritative. Public branding on a bound host ignores a conflicting slug parameter. Normal platform access remains available on the configured ADMIN_ORIGIN. Revoking a binding stops accepting its custom-origin writes.

DNS verification checks ownership, not certificate validity or all future DNS changes. Activation relies on the explicit hosting confirmation and fresh TXT check. Automated ownership rechecks, certificate expiry monitoring, automated domain provisioning, a freeform page designer and mobile branding remain future work.

## Deploy and verify

Fetch and check out the supplied commit, then run `bash deploy/virtualmin/update-configuration.sh` as root. The script backs up only `tech4learn_app`, stops only `tech4learn`, builds locked dependencies, applies migration 4 and restarts that service. On failure it leaves the service stopped for diagnosis. Do not run bootstrap or recreate the demo dataset. Do not revert to pre-migration code without a reviewed recovery procedure.

After deployment, refresh the administration page. Select a demo organisation, open Setup, save a welcome message/template, and preview its shared organisation link in a private window. Configure a centre field and save a value against a demo centre; verify a group-scoped staff member cannot edit the centre or read an out-of-scope group. Check learner data survived. Use Roles/Team to grant new setup/field permissions deliberately. Do not use real learner information for acceptance testing.

Primary implementation reference: [Node DNS resolver documentation](https://nodejs.org/docs/latest-v24.x/api/dns.html). Unit/integration tests stub DNS responses; they do not prove any live custom domain or certificate is configured.
