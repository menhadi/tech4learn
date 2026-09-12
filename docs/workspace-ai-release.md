# Workspace and vision pilot

This release adds grouped left navigation, a daily overview, a clearer add-centre entry point, a catalogue-driven module/action permission table and a repeat-safe dummy student action. The clarified target hierarchy and outstanding work are recorded in [registration workflow](registration-workflow.md).

Migration 6 stores bounded, tenant-scoped photo analysis history. Authorised users explicitly select one of OpenAI, Claude, Gemini or DeepSeek for a saved photo. Scene mode returns quality and an approximate count only. Register mode transcribes visible marks and proposes exact code/unique-name matches against the captured roster. Results never confirm attendance automatically; a reviewer must check the date, names and marks. Face recognition is not implemented; see [evaluation](self-hosted-recognition.md).

## Current provider setup

The UI exposes readiness and model names, never credentials. A key/model pair means configured, not connection-tested. Set only supported image models in the private server environment file. These are temporary operator settings until the central editable API settings area is implemented:

| Provider | Key variable | Model variable |
| --- | --- | --- |
| OpenAI | T4L_OPENAI_API_KEY | T4L_OPENAI_VISION_MODEL |
| Claude | T4L_ANTHROPIC_API_KEY | T4L_ANTHROPIC_VISION_MODEL |
| Gemini | T4L_GEMINI_API_KEY | T4L_GEMINI_VISION_MODEL |
| DeepSeek | T4L_DEEPSEEK_API_KEY | T4L_DEEPSEEK_VISION_MODEL |

Do not put credentials in Git or screenshots. Requests send the selected private photo to that provider only after the user chooses Analyse. Check the provider account's data handling before using real student images. `attendance.analyse` requires photo access; all operations recheck tenant membership and record scope. The pilot allows one concurrent analysis and 20 attempts per organisation per UTC day, with bounded responses and a 40-second provider timeout. Completed results for the same photo, provider, mode and model are reused. No billing ledger or configurable plan quotas yet.

## Verification and deployment

Run `npm run check`. Tests use synthetic data and mocked provider responses: they verify adapters, scope denial, response validation, no automatic mark changes and repeat-safe demo creation. Real provider credentials and engine accuracy were not tested.

User deployment: check out the exact verified commit and run `bash /home/tech4learn/tech4learn-app/deploy/virtualmin/update-workspace-ai.sh`. The script backs up the Tech4Learn database, builds, applies migrations and restarts only `tech4learn`. It does not install recognition infrastructure or change ExamElite/shared web server settings.
