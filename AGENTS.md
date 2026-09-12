# Tech4Learn development

Read docs/product-blueprint.md and docs/architecture.md before changing product behaviour.
Keep each organisation's records isolated. Do not trust a client-supplied organisation ID as authorisation.
ExamElite owns exam and question functionality; integrate it instead of duplicating its engine.
Never commit credentials, learner data, photos, recordings, or server environment files.
Development happens locally. The user initially deploys checked Git commits directly to tech4learn.com; staging is deferred and will be added later.
Live access for this assistant is read-only. Do not run live migrations or make live edits.
Keep planned features clearly distinguished from working functionality.
Run npm run check for changes affecting the scaffold. Native changes also need a device build/check.
Do not add infrastructure or dependencies without a concrete use case.
Use docs/ui-template.md for all admin forms and tables; reuse the shared components and preserve scoped draft recovery.
