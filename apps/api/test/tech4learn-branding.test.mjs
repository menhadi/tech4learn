import assert from "node:assert/strict";
import test from "node:test";
import { readFile } from "node:fs/promises";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = resolve(fileURLToPath(new URL(".", import.meta.url)));
const root = resolve(here, "..", "..", "..");

test("public exam surfaces keep the engine brand private", async () => {
  const publicFiles = [
    "apps/admin/src/ExamAiSettings.tsx",
    "apps/admin/src/CentralExamPlatform.tsx",
    "apps/admin/src/ExamWorkspace.tsx",
    "apps/admin/src/ExamCapabilities.tsx",
    "apps/admin/src/ExamPlanAssignment.tsx",
    "apps/admin/src/ExamPlanCreate.tsx",
    "apps/admin/src/ExamPlanEdit.tsx",
    "apps/admin/src/ExamBuilder.tsx",
    "apps/admin/src/ExamQuestionEditor.tsx",
    "apps/admin/src/ExamResults.tsx",
    "apps/admin/src/ExamTaxonomy.tsx",
    "apps/admin/src/ExamTranslationReview.tsx",
    "apps/admin/src/GroupedMenu.tsx",
    "apps/admin/src/OrganisationWorkspace.tsx",
    "apps/admin/src/main.tsx",
    "apps/api/src/access-model.ts",
    "apps/api/src/exam-content.service.ts",
    "apps/api/src/exam-workspace.service.ts",
    "apps/api/src/examelite.service.ts",
  ];
  for (const file of publicFiles) {
    const source = await readFile(resolve(root, file), "utf8");
    const displayLines = source
      .replace(/^import .*$/gm, "")
      .split(/\r?\n/)
      .filter((line) => /["'`].*ExamElite/.test(line));
    assert.doesNotMatch(
      displayLines.join("\n"),
      /ExamElite/,
      `${file} exposes the private engine name in a user-facing string`,
    );
  }
});
