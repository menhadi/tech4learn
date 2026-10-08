# Tech4Learn development

Read docs/product-blueprint.md and docs/architecture.md before changing product behaviour.
Keep each organisation's records isolated. Do not trust a client-supplied organisation ID as authorisation.
ExamElite owns exam and question functionality; integrate it instead of duplicating its engine.
Never commit credentials, learner data, photos, recordings, or server environment files.
Development happens locally. The user initially deploys checked Git commits directly to tech4learn.com; staging is deferred and will be added later.
The assistant may deploy checked releases, edit Tech4Learn-owned application files and configuration, run required Tech4Learn migrations, and restart Tech4Learn services using existing deployment access. Prioritise a usable administrator login, exams and attendance. Preserve the administrator identity/password. Legacy data deletion is deferred. Do not modify the original ExamElite installation or other sites. Apache/root changes remain narrowly scoped user-run steps.
Keep planned features clearly distinguished from working functionality.
Run npm run check for changes affecting the scaffold. Native changes also need a device build/check.
Do not add infrastructure or dependencies without a concrete use case.
Use docs/ui-template.md for all admin forms and tables; reuse the shared components and preserve scoped draft recovery.

