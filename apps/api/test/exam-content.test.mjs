import "reflect-metadata";
import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID, randomBytes } from "node:crypto";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { configurationMigration } from "../dist/migration-configuration.js";
import { examWorkspaceMigration } from "../dist/migration-exam-workspace.js";
import { createApp } from "../dist/bootstrap.js";
import { ExamEliteService } from "../dist/examelite.service.js";
import { FaceJobsService } from "../dist/face-jobs.service.js";
import { digest } from "../dist/security.js";
import { allPermissions } from "../dist/access-model.js";

test("central question sharing requires superadmin; organisation reads respect modules, scope and restrictions", async () => {
  const pg = new PGlite();
  for (const m of [
    migration,
    accessMigration,
    learnerMigration,
    configurationMigration,
    examWorkspaceMigration,
  ])
    await pg.exec(m);
  const org = randomUUID(),
    other = randomUUID(),
    admin = randomUUID(),
    member = randomUUID(),
    role = randomUUID();
  await pg.query(
    "INSERT INTO organisations(id,name,slug) VALUES($1,'Owned','owned'),($2,'Other','other')",
    [org, other],
  );
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'admin@example.test','Admin','unused',true),($2,'member@example.test','Member','unused',false)",
    [admin, member],
  );
  await pg.query(
    "INSERT INTO access_roles(id,organisation_id,name,permissions,protected) VALUES($1,$2,'Exam staff',$3,true)",
    [role, org, allPermissions],
  );
  await pg.query(
    "INSERT INTO memberships(user_id,organisation_id,role,role_id) VALUES($1,$2,'admin',$3)",
    [member, org, role],
  );
  const tokens = {};
  for (const u of [admin, member]) {
    tokens[u] = randomBytes(32).toString("hex");
    await pg.query(
      "INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour')",
      [digest(tokens[u]), u],
    );
  }
  const db = {
    query: (q, p) => pg.query(q, p),
    transaction: (fn) =>
      pg.transaction((tx) => fn({ query: (q, p) => tx.query(q, p) })),
    onModuleDestroy: async () => {},
  };
  const app = await createApp(undefined, db);
  app.get(FaceJobsService).onModuleInit = () => {};
  const remote = app.get(ExamEliteService),
    requests = [];
  remote.configuration = async () => ({
    central: true,
    organization_id: 1,
    token: "private",
  });
  let centralPaperMode = "ok";
  let saveOutcome = "success";
  let disableOutcome = "success";
  let deleteOutcome = "success";
  let mediaMode = "ok";
  let documentMode = "ok";
  let translationMode = "ok";
  const translatedWording = Object.fromEntries(
    [
      "question",
      "option1",
      "option2",
      "option3",
      "option4",
      "option5",
      "option6",
      "hint",
      "explanation",
      "fill_blank",
      "si_answer1",
    ].map((key) => [key, key === "question" ? "Synthetic wording" : null]),
  );
  let packageWriteMode = "ok";
  let centralMode = "ok";
  let centralWriteMode = "ok";
  let centralChoiceMode = "ok";
  let centralTaxMode = "ok";
  remote.request = async (c, o, path, body) => {
    requests.push({ o, path, body });
    if (
      /\/taxonomy\/(categories|subcategories|languages)\/9\/delete$/.test(path)
    ) {
      const central = path.startsWith("central/");
      if (deleteOutcome === "revoked") {
        if (central)
          await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
            admin,
          ]);
        else
          await pg.query(
            "UPDATE organisation_settings SET enabled_modules=$2::jsonb WHERE organisation_id=$1",
            [org, JSON.stringify({ exams: false })],
          );
      }
      if (deleteOutcome === "conflict") return { saved: false, conflict: true };
      if (deleteOutcome === "used")
        return { saved: false, errors: { record: ["In use"] } };
      return {
        saved: true,
        kind: path.split("/").at(-3),
        [central ? "record" : "question"]: {
          id: deleteOutcome === "wrong" ? 10 : 9,
          deleted: deleteOutcome !== "missing",
          secret: "discard",
        },
      };
    }
    const packageImage = /^central\/packages\/[1-9][0-9]*\/image$/.test(path);
    if (packageImage) path = `central/taxonomy/packages/${path.split("/")[2]}`;
    if (path.startsWith("central/packages/") && path.includes("/media/")) {
      if (centralMode === "revoked")
        await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
          admin,
        ]);
      return {
        package_id: centralMode === "wrong" ? 8 : 7,
        asset: "c".repeat(64),
        mime: centralMode === "mime" ? "image/svg+xml" : "image/png",
        base64:
          centralMode === "base64"
            ? "!"
            : Buffer.from("synthetic raster").toString("base64"),
      };
    }
    const centralDocument =
      /^central\/exams\/([1-9][0-9]*)\/documents\/(.*)$/.exec(path);
    if (centralDocument)
      path = `documents/${org}/exams/${centralDocument[1]}/${centralDocument[2]}`;
    const centralTranslation =
      /^central\/exams\/([1-9][0-9]*)\/translations\/([1-9][0-9]*)(.*)$/.exec(
        path,
      );
    if (centralTranslation)
      path = `translations/${org}/exams/${centralTranslation[1]}/languages/${centralTranslation[2]}${centralTranslation[3]}`;
    const centralExamAction = /^central\/exams\/([1-9][0-9]*)\/actions\//.exec(
      path,
    );
    if (centralExamAction)
      path = `central/taxonomy/exams/${centralExamAction[1]}`;
    if (path.startsWith("central/taxonomy/")) {
      if (centralTaxMode === "revoked")
        await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
          admin,
        ]);
      if (centralTaxMode === "conflict")
        return { saved: false, conflict: true };
      if (centralTaxMode === "validation")
        return {
          saved: false,
          errors: { name: ["A classification name is required."] },
        };
      if (centralTaxMode === "malformed") return {};
      const parts = path.split("/");
      const isNew = parts[3] === "new";
      return {
        saved: body ? true : undefined,
        kind: centralTaxMode === "kind" ? "other" : parts[2],
        record: {
          ...(parts[2] === "exams"
            ? {
                test_types: { full_length: "Full-Length Tests" },
                timezone: "Asia/Kolkata",
                ...(isNew && !body
                  ? {}
                  : {
                      status: "Inactive",
                      sections: [],
                      subject_durations: [],
                      paper_subjects: [],
                    }),
              }
            : {}),
          ...(parts[2] === "packages"
            ? {
                photo_asset:
                  centralTaxMode === "asset"
                    ? "bad"
                    : packageImage && !body?.fields.remove
                      ? "c".repeat(64)
                      : null,
              }
            : {}),
          id:
            centralTaxMode === "id"
              ? -1
              : isNew
                ? body
                  ? 77
                  : 0
                : Number(parts[3]),
          revision:
            centralTaxMode === "revision"
              ? "bad"
              : isNew && !body
                ? "new"
                : "a".repeat(64),
          fields:
            centralTaxMode === "fields"
              ? { organization_id: 20 }
              : parts[2] === "languages"
                ? {
                    name: "Synthetic language",
                    code: centralTaxMode === "language-type" ? 12 : "syn",
                    value1: null,
                    value2: "False",
                    ...body?.fields,
                  }
                : parts[2] === "packages"
                  ? {
                      package_type: centralTaxMode === "paid" ? "paid" : "free",
                      ...(packageImage
                        ? { name: "Synthetic central package" }
                        : (body?.fields ?? {})),
                    }
                  : centralExamAction
                    ? { name: "Synthetic central exam" }
                    : (body?.fields ?? {}),
        },
      };
    }
    if (path.startsWith("central/exams/")) {
      if (centralPaperMode === "revoked")
        await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
          admin,
        ]);
      let items = [
        { id: 7, question: "Synthetic attached question", secret: "excluded" },
      ];
      let next = null;
      if (centralPaperMode === "null") items = null;
      if (centralPaperMode === "id") items[0].id = "7";
      if (centralPaperMode === "duplicate") items.push({ ...items[0] });
      if (centralPaperMode === "wording") items[0].question = {};
      if (centralPaperMode === "long") items[0].question = "x".repeat(501);
      if (centralPaperMode === "unicode")
        items[0].question = "\u{1f600}".repeat(500);
      if (["page", "oversize"].includes(centralPaperMode)) {
        items = Array.from(
          { length: centralPaperMode === "page" ? 100 : 101 },
          (_, i) => ({ id: i + 1, question: "Synthetic" }),
        );
        next = items.length;
      }
      if (centralPaperMode === "cursor") next = 7;
      if (centralPaperMode === "missing-cursor") return { items };
      return { items, next };
    }
    if (path.startsWith("central/choices/")) {
      if (centralChoiceMode === "revoked")
        await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
          admin,
        ]);
      if (centralChoiceMode === "malformed") return { items: null, next: null };
      const items = [
        { id: 7, label: "Central group", secret: "must not escape" },
      ];
      if (centralChoiceMode === "wrong") items[0].id = -1;
      if (centralChoiceMode === "label") items[0].label = {};
      if (centralChoiceMode === "duplicate") items.push({ ...items[0] });
      if (centralChoiceMode === "oversize")
        return {
          items: Array.from({ length: 101 }, (_, i) => ({
            id: i + 1,
            label: "group",
          })),
          next: null,
        };
      return { items, next: centralChoiceMode === "cursor" ? 8 : null };
    }
    if (path === "central/questions" || path.startsWith("central/questions/")) {
      if (body) {
        if (centralWriteMode === "revoked")
          await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
            admin,
          ]);
        if (centralWriteMode === "conflict")
          return { saved: false, conflict: true };
        if (centralWriteMode === "validation")
          return {
            saved: false,
            errors: { question: ["A question is required."] },
          };
        if (centralWriteMode === "malformed") return {};
        return {
          saved: true,
          question: {
            id:
              centralWriteMode === "wrong"
                ? -1
                : path === "central/questions"
                  ? 77
                  : 7,
            revision:
              centralWriteMode === "revision" ? "invalid" : "a".repeat(64),
            fields: centralWriteMode === "fields" ? null : body.fields,
            preview_fields:
              centralWriteMode === "preview"
                ? null
                : { question: "Central question" },
          },
        };
      }
      if (centralMode === "revoked")
        await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
          admin,
        ]);
      if (path.includes("/media/"))
        return {
          question_id: centralMode === "wrong" ? 8 : 7,
          revision:
            centralMode === "revision" ? "b".repeat(64) : "a".repeat(64),
          asset: "c".repeat(64),
          mime: centralMode === "mime" ? "image/svg+xml" : "image/png",
          base64:
            centralMode === "base64"
              ? "!"
              : Buffer.from("synthetic raster").toString("base64"),
        };
      return {
        id: centralMode === "wrong" ? 8 : 7,
        revision: centralMode === "revision" ? "invalid" : "a".repeat(64),
        fields: { question: "Central original" },
        preview_fields: { question: "Central original" },
      };
    }
    if (path.startsWith("translations/")) {
      if (translationMode === "revoked" && centralTranslation)
        await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
          admin,
        ]);
      else if (translationMode === "revoked")
        await pg.query(
          "UPDATE memberships SET status='suspended' WHERE user_id=$1 AND organisation_id=$2",
          [member, org],
        );
      if (path.includes("/media/"))
        return {
          data: {
            exam_id: translationMode === "wrong" ? 10 : 9,
            language_id: 5,
            question_id: 7,
            revision:
              translationMode === "revision" ? "b".repeat(64) : "a".repeat(64),
            asset: "c".repeat(64),
            mime: translationMode === "mime" ? "image/svg+xml" : "image/png",
            base64:
              translationMode === "base64"
                ? "!!!"
                : Buffer.from("synthetic raster").toString("base64"),
          },
        };
      return {
        data: {
          exam_id: translationMode === "wrong" ? 10 : 9,
          language_id: 5,
          language_name: "Synthetic language",
          is_source_language: false,
          revision: "a".repeat(64),
          approved: false,
          progress: {
            status: "pending",
            translated: 0,
            remaining: 1,
            total: translationMode === "counts" ? 2 : 1,
            exam_content_ready: false,
          },
          source: {
            name: "Synthetic exam",
            instruction: null,
            syllabus: null,
            private_extra: "omitted",
          },
          translation: null,
          items: [
            {
              question_id: 7,
              source: translatedWording,
              translation: null,
              stale_fields: [
                translationMode === "field" ? "secret" : "question",
              ],
            },
          ],
          next: translationMode === "cursor" ? 99 : null,
          private_path: "/private/omitted",
        },
      };
    }
    if (path.startsWith("documents/")) {
      if (documentMode === "revoked" && centralDocument)
        await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
          admin,
        ]);
      else if (documentMode === "revoked")
        await pg.query(
          "UPDATE memberships SET status='suspended' WHERE user_id=$1 AND organisation_id=$2",
          [member, org],
        );
      if (path.includes("/status?"))
        return {
          data: {
            exam_id: documentMode === "wrong" ? 10 : 9,
            package_id: 4,
            language_id: null,
            document_type: "questions",
            build_id: 3,
            status: documentMode === "invalid" ? "unknown" : "processing",
            approved_available: true,
            current_path: "/private/omitted.pdf",
          },
        };
      return {
        data: {
          exam_id: documentMode === "wrong" ? 10 : 9,
          package_id: 4,
          language_id: null,
          document_type: "questions",
          build_id: 3,
          mime: documentMode === "mime" ? "text/html" : "application/pdf",
          base64: Buffer.from(
            documentMode === "invalid" ? "not PDF" : "%PDF-1.4 synthetic",
          ).toString("base64"),
        },
      };
    }
    if (path.endsWith("/disable"))
      return {
        saved: true,
        question: {
          id: disableOutcome === "wrong" ? 10 : 9,
          revision: "b".repeat(64),
          fields: { is_enabled: disableOutcome === "enabled" },
        },
      };
    if (path.includes("/media/")) {
      if (mediaMode === "revoked")
        await pg.query(
          "UPDATE memberships SET status='suspended' WHERE user_id=$1 AND organisation_id=$2",
          [member, org],
        );
      return {
        [path.includes("/packages/") ? "package_id" : "question_id"]:
          mediaMode === "wrong" ? 10 : 9,
        asset: "a".repeat(64),
        mime: mediaMode === "mime" ? "text/html" : "image/png",
        base64:
          mediaMode === "base64"
            ? "!!!"
            : Buffer.from("synthetic image bytes").toString("base64"),
      };
    }
    if (/packages\/9\/image$/.test(path)) {
      if (packageWriteMode === "revoked")
        await pg.query(
          "UPDATE memberships SET status='suspended' WHERE user_id=$1 AND organisation_id=$2",
          [member, org],
        );
      return {
        saved: true,
        question: {
          id: packageWriteMode === "wrong" ? 10 : 9,
          revision: packageWriteMode === "revision" ? "bad" : "b".repeat(64),
          fields: { name: "Saved package" },
          photo_asset:
            packageWriteMode === "asset"
              ? "not-a-hash"
              : body.fields.remove
                ? null
                : "c".repeat(64),
        },
      };
    }
    if (path.includes("/choices/"))
      return { items: [{ id: 3, label: "Own classification" }], next: null };
    if (path.startsWith("authoring/"))
      return body
        ? saveOutcome === "conflict"
          ? { saved: false, conflict: true }
          : saveOutcome === "invalid"
            ? {
                saved: false,
                errors: { nat_value: ["Numerical answer is required."] },
              }
            : {
                saved: true,
                question: {
                  id: saveOutcome === "wrong-image-record" ? 10 : 9,
                  revision: "b".repeat(64),
                  fields: { question: "Saved" },
                },
              }
        : {
            id: 9,
            revision: "a".repeat(64),
            fields: { question: "Original" },
            type: "NAT",
          };
    return path.endsWith("/launch")
      ? { ready: true }
      : path.endsWith("/transfer")
        ? { count: body.question_ids.length, items: [] }
        : { items: [], next: null };
  };
  await app.listen(0, "127.0.0.1");
  const base = (await app.getUrl()) + "/api/v1";
  const call = (path, body, actor = admin, origin = "http://localhost:5173") =>
    fetch(base + path, {
      method: body === undefined ? "GET" : "POST",
      headers: {
        Cookie: "t4l_session=" + tokens[actor],
        Origin: origin,
        "Content-Type": "application/json",
        "X-Tech4Learn-Request": "1",
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  const platform = `/platform/exam-content/${org}`,
    own = `/organisations/${org}/exam-content/questions`;
  try {
    assert.equal(
      (await call(platform + "/questions", undefined, member)).status,
      403,
    );
    assert.equal((await call(own, undefined, member)).status, 403);
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/questions`,
          undefined,
          member,
        )
      ).status,
      404,
    );
    assert.equal(requests.length, 0);
    assert.equal(
      (await call(platform + "/modules", { exams: true, version: 0 }, member))
        .status,
      403,
    );
    assert.equal(
      (
        await call(
          platform + "/modules",
          { exams: true, version: 0 },
          admin,
          "https://evil.example",
        )
      ).status,
      403,
    );
    const enabled = await call(platform + "/modules", {
      exams: true,
      version: 0,
    });
    assert.equal(enabled.status, 201);
    assert.equal((await enabled.json()).version, 1);
    assert.equal(
      (await call(platform + "/modules", { exams: false, version: 0 })).status,
      409,
    );
    assert.equal((await call(own, undefined, member)).status, 200);
    assert.match(requests.at(-1).path, /source=organisation/);
    assert.equal((await call(own + "/9", undefined, member)).status, 200);
    const mediaPath = own + "/9/media/" + "a".repeat(64);
    const authoringImage = await call(mediaPath, undefined, member);
    assert.equal(authoringImage.status, 200);
    assert.equal(authoringImage.headers.get("cache-control"), "no-store");
    assert.equal(
      authoringImage.headers.get("x-content-type-options"),
      "nosniff",
    );
    assert.equal(authoringImage.headers.get("content-type"), "image/png");
    assert.equal(await authoringImage.text(), "synthetic image bytes");
    assert.equal(
      (await call(mediaPath.replace(org, other), undefined, member)).status,
      404,
    );
    assert.equal(
      (await call(own + "/new/media/" + "a".repeat(64), undefined, member))
        .status,
      400,
    );
    for (const mode of ["wrong", "mime"]) {
      mediaMode = mode;
      assert.equal((await call(mediaPath, undefined, member)).status, 503);
    }
    mediaMode = "revoked";
    assert.equal((await call(mediaPath, undefined, member)).status, 404);
    await pg.query(
      "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    mediaMode = "ok";
    const packageImagePath = `/organisations/${org}/exam-content/packages/9/media/${"a".repeat(64)}`;
    const packageImage = await call(packageImagePath, undefined, member);
    assert.equal(packageImage.status, 200);
    assert.equal(packageImage.headers.get("content-type"), "image/png");
    assert.equal(packageImage.headers.get("cache-control"), "no-store");
    assert.equal(packageImage.headers.get("x-content-type-options"), "nosniff");
    assert.equal(await packageImage.text(), "synthetic image bytes");
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/packages/9/media/${"a".repeat(64)}`,
    );
    assert.equal(
      (await call(packageImagePath.replace(org, other), undefined, member))
        .status,
      404,
    );
    for (const suffix of ["?path=secret", "?actor_id=" + admin]) {
      const before = requests.length;
      assert.equal(
        (await call(packageImagePath + suffix, undefined, member)).status,
        400,
      );
      assert.equal(requests.length, before);
    }
    for (const mode of ["wrong", "mime", "base64"]) {
      mediaMode = mode;
      assert.equal(
        (await call(packageImagePath, undefined, member)).status,
        503,
      );
    }
    mediaMode = "revoked";
    assert.equal((await call(packageImagePath, undefined, member)).status, 404);
    await pg.query(
      "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    mediaMode = "ok";
    const documentPath = `/organisations/${org}/exam-content/exams/9/documents/questions?package_id=4`;
    const pdf = await call(documentPath, undefined, member);
    assert.equal(pdf.status, 200);
    assert.equal(pdf.headers.get("content-type"), "application/pdf");
    assert.equal(pdf.headers.get("cache-control"), "no-store");
    assert.match(
      pdf.headers.get("content-disposition"),
      /attachment; filename="exam-9-questions.pdf"/,
    );
    assert.equal(await pdf.text(), "%PDF-1.4 synthetic");
    assert.ok(requests.at(-1).path.includes(`actor_id=${member}`));
    assert.equal(
      (await call(documentPath.replace(org, other), undefined, member)).status,
      404,
    );
    assert.equal(
      (await call(documentPath + "&actor_id=" + admin, undefined, member))
        .status,
      400,
    );
    assert.equal(
      (await call(documentPath + "&language_id=-1", undefined, member)).status,
      400,
    );
    for (const mode of ["wrong", "mime", "invalid"]) {
      documentMode = mode;
      assert.equal((await call(documentPath, undefined, member)).status, 503);
    }
    documentMode = "revoked";
    assert.equal((await call(documentPath, undefined, member)).status, 404);
    await pg.query(
      "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    documentMode = "ok";
    const statusPath = documentPath.replace("?", "/status?");
    const state = await call(statusPath, undefined, member);
    assert.equal(state.status, 200);
    assert.equal(state.headers.get("cache-control"), "no-store");
    assert.deepEqual(await state.json(), {
      document_type: "questions",
      status: "processing",
      approved_available: true,
    });
    assert.equal(
      (await call(statusPath.replace(org, other), undefined, member)).status,
      404,
    );
    for (const mode of ["wrong", "invalid"]) {
      documentMode = mode;
      assert.equal((await call(statusPath, undefined, member)).status, 503);
    }
    documentMode = "revoked";
    assert.equal((await call(statusPath, undefined, member)).status, 404);
    await pg.query(
      "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    documentMode = "ok";
    const centralPdfPath = `/platform/exam-content/${org}/central/exams/9/documents/questions?package_id=4`;
    for (const path of [
      centralPdfPath,
      centralPdfPath.replace("?", "/status?"),
    ]) {
      const before = requests.length;
      assert.equal((await call(path, undefined, member)).status, 403);
      assert.equal(requests.length, before);
      const result = await call(path);
      assert.equal(result.status, 200);
      assert.equal(result.headers.get("cache-control"), "no-store");
      assert.ok(
        requests.at(-1).path.startsWith("central/exams/9/documents/questions"),
      );
      assert.ok(!requests.at(-1).path.includes("actor_id"));
      if (path.includes("/status?"))
        assert.deepEqual(await result.json(), {
          document_type: "questions",
          status: "processing",
          approved_available: true,
        });
      else {
        assert.equal(result.headers.get("x-content-type-options"), "nosniff");
        assert.match(
          result.headers.get("content-disposition"),
          /attachment; filename="exam-9-questions.pdf"/,
        );
        assert.equal(await result.text(), "%PDF-1.4 synthetic");
      }
      for (const extra of [
        "&actor_id=" + admin,
        "&language_id=-1",
        "&path=private",
      ]) {
        const before = requests.length;
        assert.equal((await call(path + extra)).status, 400);
        assert.equal(requests.length, before);
      }
      for (const mode of ["wrong", "invalid", "revoked"]) {
        documentMode = mode;
        assert.equal((await call(path)).status, mode === "revoked" ? 403 : 503);
        await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
          admin,
        ]);
      }
      documentMode = "ok";
    }
    const translationPath = `/organisations/${org}/exam-content/exams/9/translations/5`;
    const reviewResponse = await call(translationPath, undefined, member);
    assert.equal(reviewResponse.status, 200);
    assert.equal(reviewResponse.headers.get("cache-control"), "no-store");
    const translationResult = await reviewResponse.json();
    assert.equal(
      translationResult.items[0].source.question,
      "Synthetic wording",
    );
    assert.equal(translationResult.private_path, undefined);
    assert.equal(translationResult.source.private_extra, undefined);
    assert.match(requests.at(-1).path, new RegExp(`actor_id=${member}`));
    assert.equal(
      (await call(translationPath.replace(org, other), undefined, member))
        .status,
      404,
    );
    for (const suffix of [
      `?actor_id=${admin}`,
      "?after=-1",
      "?after=7",
      "?revision=bad",
      "?language_id=6",
    ]) {
      const before = requests.length;
      assert.equal(
        (await call(translationPath + suffix, undefined, member)).status,
        400,
      );
      assert.equal(requests.length, before);
    }
    for (const mode of ["wrong", "counts", "field", "cursor"]) {
      translationMode = mode;
      assert.equal(
        (await call(translationPath, undefined, member)).status,
        503,
      );
    }
    translationMode = "ok";
    assert.equal(
      (
        await call(
          translationPath + "?revision=" + "b".repeat(64),
          undefined,
          member,
        )
      ).status,
      503,
    );
    translationMode = "revoked";
    assert.equal((await call(translationPath, undefined, member)).status, 404);
    await pg.query(
      "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    translationMode = "ok";
    const translationImagePath =
      translationPath + "/media/7/" + "a".repeat(64) + "/" + "c".repeat(64);
    const translatedImage = await call(translationImagePath, undefined, member);
    assert.equal(translatedImage.status, 200);
    assert.equal(translatedImage.headers.get("content-type"), "image/png");
    assert.equal(translatedImage.headers.get("cache-control"), "no-store");
    assert.equal(
      translatedImage.headers.get("x-content-type-options"),
      "nosniff",
    );
    assert.equal(await translatedImage.text(), "synthetic raster");
    assert.match(requests.at(-1).path, new RegExp(`actor_id=${member}`));
    assert.equal(
      (await call(translationImagePath.replace(org, other), undefined, member))
        .status,
      404,
    );
    assert.equal(
      (
        await call(
          translationImagePath + "?actor_id=" + admin,
          undefined,
          member,
        )
      ).status,
      400,
    );
    for (const mode of ["wrong", "revision", "mime", "base64"]) {
      translationMode = mode;
      assert.equal(
        (await call(translationImagePath, undefined, member)).status,
        503,
      );
    }
    translationMode = "revoked";
    assert.equal(
      (await call(translationImagePath, undefined, member)).status,
      404,
    );
    await pg.query(
      "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    translationMode = "ok";
    const imageWrite = {
      revision: "a".repeat(64),
      request_id: randomUUID(),
      fields: {
        field: "question",
        image: Buffer.from("synthetic upload").toString("base64"),
      },
    };
    assert.equal(
      (await call(own + "/9/image", imageWrite, member)).status,
      201,
    );
    assert.match(requests.at(-1).path, /questions\/9\/image$/);
    const packageWritePath = `/organisations/${org}/exam-content/packages/9/image`;
    const packageWrite = {
      ...imageWrite,
      fields: { image: imageWrite.fields.image },
    };
    assert.equal(
      (await call(packageWritePath, packageWrite, member)).status,
      201,
    );
    assert.equal(requests.at(-1).body.actor_id, member);
    assert.equal(requests.at(-1).path, `authoring/${org}/packages/9/image`);
    assert.equal("field" in requests.at(-1).body.fields, false);
    const packageRemove = {
      ...packageWrite,
      fields: { remove: true, asset: "c".repeat(64) },
    };
    assert.equal(
      (await call(packageWritePath, packageRemove, member)).status,
      201,
    );
    assert.equal(requests.at(-1).body.fields.remove, true);
    assert.equal(
      (await call(packageWritePath.replace(org, other), packageWrite, member))
        .status,
      404,
    );
    for (const body of [
      { ...packageWrite, actor_id: admin },
      { ...packageWrite, fields: { ...packageWrite.fields, field: "photo" } },
      { ...packageRemove, fields: { ...packageRemove.fields, image: "AAAA" } },
      { ...packageWrite, fields: { remove: true } },
      { ...packageWrite, fields: { image: "" } },
      { ...packageWrite, fields: { image: "AAAA", asset: "old" } },
      { ...packageWrite, fields: { photo: "/some/path" } },
    ]) {
      const before = requests.length;
      assert.equal((await call(packageWritePath, body, member)).status, 400);
      assert.equal(requests.length, before);
    }
    for (const mode of ["wrong", "revision", "asset"]) {
      packageWriteMode = mode;
      assert.equal(
        (await call(packageWritePath, packageWrite, member)).status,
        503,
      );
    }
    packageWriteMode = "revoked";
    assert.equal(
      (await call(packageWritePath, packageWrite, member)).status,
      404,
    );
    await pg.query(
      "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    packageWriteMode = "ok";
    const removeWrite = {
      ...imageWrite,
      request_id: randomUUID(),
      fields: { field: "question", asset: "a".repeat(64), remove: true },
    };
    assert.equal(
      (await call(own + "/9/image", removeWrite, member)).status,
      201,
    );
    assert.equal(requests.at(-1).body.fields.remove, true);
    assert.equal("image" in requests.at(-1).body.fields, false);
    assert.equal(
      (
        await call(
          own + "/9/image",
          { ...removeWrite, fields: { ...removeWrite.fields, image: "AAAA" } },
          member,
        )
      ).status,
      400,
    );
    assert.equal(
      (
        await call(
          own + "/9/image",
          { ...removeWrite, fields: { field: "question", remove: true } },
          member,
        )
      ).status,
      400,
    );
    assert.equal(requests.at(-1).body.actor_id, member);
    assert.equal(
      (await call(own + "/9/image", { ...imageWrite, actor_id: admin }, member))
        .status,
      400,
    );
    assert.equal(
      (
        await call(
          own + "/9/image",
          {
            ...imageWrite,
            fields: { ...imageWrite.fields, field: "organization_id" },
          },
          member,
        )
      ).status,
      400,
    );
    assert.equal(
      (
        await call(
          own + "/9/image",
          {
            ...imageWrite,
            fields: { ...imageWrite.fields, image: "A".repeat(699056) },
          },
          member,
        )
      ).status,
      400,
    );
    assert.equal(
      (await call(own + "/new/image", imageWrite, member)).status,
      400,
    );
    assert.equal(
      (await call((own + "/9/image").replace(org, other), imageWrite, member))
        .status,
      404,
    );
    const edit = {
      revision: "a".repeat(64),
      request_id: randomUUID(),
      fields: { question: "Saved" },
    };
    assert.equal((await call(own + "/9", edit, member)).status, 201);
    assert.equal(requests.at(-1).body.actor_id, member);
    for (const [action, fields] of Object.entries({
      "create-section": { name: "Part A", duration: 20 },
      "update-section": { section_id: 3, name: "Part A", duration: 25 },
      "remove-section": { section_id: 3 },
      "assign-section": { question_ids: [9], question_section_id: 3 },
      "subject-timers": { subject_ids: [2], durations: [30] },
      "set-status": { status: "Active" },
      "save-exam-translation": {
        language_id: 5,
        translation_revision: "a".repeat(64),
        wording: { name: "Translated exam", instruction: "<p>Read first</p>" },
      },
      "set-translation-image": {
        language_id: 5,
        question_id: 7,
        translation_revision: "a".repeat(64),
        field: "si_answer1",
        image: "aW1hZ2U=",
      },
      "save-question-translation": {
        language_id: 5,
        translation_revision: "a".repeat(64),
        question_id: 7,
        wording: {
          question: "<p>Translated wording</p>",
          si_answer1: "Translated model answer",
        },
      },
      "approve-translation": {
        language_id: 5,
        translation_revision: "a".repeat(64),
      },
      "refresh-translation": {
        language_id: 5,
        translation_revision: "a".repeat(64),
      },
      "generate-document": {
        package_id: 4,
        language_id: 5,
        document_type: "questions",
      },
    })) {
      assert.equal(
        (
          await call(
            `/organisations/${org}/exam-content/exams/9/actions/${action}`,
            {
              ...edit,
              fields,
              ...(action === "set-translation-image"
                ? {}
                : { actor_id: admin }),
            },
            member,
          )
        ).status,
        201,
      );
      assert.equal(requests.at(-1).body.actor_id, member);
      assert.equal(
        requests.at(-1).path,
        `authoring/${org}/exams/9/actions/${action}`,
      );
    }
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/questions/9`,
          edit,
          member,
        )
      ).status,
      404,
    );
    for (const wording of [
      {},
      { question: "Wrong kind" },
      { name: 42 },
      { organization_id: 30 },
    ]) {
      const before = requests.length;
      assert.equal(
        (
          await call(
            `/organisations/${org}/exam-content/exams/9/actions/save-exam-translation`,
            {
              ...edit,
              fields: {
                language_id: 5,
                translation_revision: "a".repeat(64),
                wording,
              },
            },
            member,
          )
        ).status,
        400,
      );
      assert.equal(requests.length, before);
    }
    const translationImageWrite = {
      language_id: 5,
      question_id: 7,
      translation_revision: "a".repeat(64),
      field: "question",
      image: "aW1hZ2U=",
    };
    for (const bad of [
      { field: "fill_blank" },
      { language_id: 0 },
      { image: "aW1hZ2U=\n" },
      { remove: true },
      { organization_id: 2 },
      { wording: { question: "override" } },
    ]) {
      const before = requests.length;
      assert.equal(
        (
          await call(
            `/organisations/${org}/exam-content/exams/9/actions/set-translation-image`,
            { ...edit, fields: { ...translationImageWrite, ...bad } },
            member,
          )
        ).status,
        400,
      );
      assert.equal(requests.length, before);
    }
    const translationImageWritePath = `/organisations/${org}/exam-content/exams/9/actions/set-translation-image`;
    const examImage = {
      ...translationImageWrite,
      question_id: 0,
      field: "instruction",
    };
    assert.equal(
      (
        await call(
          translationImageWritePath,
          { ...edit, fields: examImage },
          member,
        )
      ).status,
      201,
    );
    assert.equal(requests.at(-1).body.fields.question_id, 0);
    assert.equal(
      (
        await call(
          translationImageWritePath,
          { ...edit, fields: { ...examImage, field: "name" } },
          member,
        )
      ).status,
      400,
    );
    const translationImageRemove = {
      ...translationImageWrite,
      remove: true,
      asset: "c".repeat(64),
    };
    delete translationImageRemove.image;
    const translationRemovedImage = await call(
      translationImageWritePath,
      { ...edit, fields: translationImageRemove },
      member,
    );
    assert.equal(translationRemovedImage.status, 201);
    assert.deepEqual(await translationRemovedImage.json(), {
      id: 9,
      revision: "b".repeat(64),
    });
    assert.equal(
      (
        await call(
          translationImageWritePath,
          { ...edit, fields: translationImageWrite, actor_id: admin },
          member,
        )
      ).status,
      400,
    );
    saveOutcome = "wrong-image-record";
    assert.equal(
      (
        await call(
          translationImageWritePath,
          { ...edit, fields: translationImageWrite },
          member,
        )
      ).status,
      503,
    );
    saveOutcome = "conflict";
    const validTranslationEdit = {
      language_id: 5,
      translation_revision: "a".repeat(64),
      question_id: 7,
      wording: {
        question: "Translated wording",
        si_answer1: "Translated model answer",
      },
    };
    for (const fields of [
      { ...validTranslationEdit, question_id: 0 },
      { ...validTranslationEdit, translation_revision: "old" },
      { ...validTranslationEdit, wording: {} },
      {
        ...validTranslationEdit,
        wording: { organization_id: "Unsupported field" },
      },
      { ...validTranslationEdit, wording: { question: 42 } },
      { ...validTranslationEdit, source_question: "Override" },
    ]) {
      const before = requests.length;
      assert.equal(
        (
          await call(
            `/organisations/${org}/exam-content/exams/9/actions/save-question-translation`,
            { ...edit, fields },
            member,
          )
        ).status,
        400,
      );
      assert.equal(requests.length, before);
    }
    for (const fields of [
      { language_id: 0, translation_revision: "a".repeat(64) },
      { language_id: 5, translation_revision: "old" },
      { language_id: "5", translation_revision: "a".repeat(64) },
      {
        language_id: 5,
        translation_revision: "a".repeat(64),
        auto_translate: true,
      },
    ]) {
      const before = requests.length;
      assert.equal(
        (
          await call(
            `/organisations/${org}/exam-content/exams/9/actions/refresh-translation`,
            { ...edit, fields },
            member,
          )
        ).status,
        400,
      );
      assert.equal(
        (
          await call(
            `/organisations/${org}/exam-content/exams/9/actions/approve-translation`,
            { ...edit, fields },
            member,
          )
        ).status,
        400,
      );
      assert.equal(requests.length, before);
    }
    for (const fields of [
      { package_id: -1, language_id: 5, document_type: "questions" },
      { package_id: 4, language_id: 5, document_type: ["questions"] },
      {
        package_id: 4,
        language_id: 5,
        document_type: "questions",
        force: true,
      },
    ]) {
      const before = requests.length;
      assert.equal(
        (
          await call(
            `/organisations/${org}/exam-content/exams/9/actions/generate-document`,
            { ...edit, fields },
            member,
          )
        ).status,
        400,
      );
      assert.equal(requests.length, before);
    }
    assert.equal((await call(own + "/9", edit, member)).status, 409);
    saveOutcome = "invalid";
    const invalid = await call(own + "/9", edit, member);
    assert.equal(invalid.status, 400);
    assert.equal(
      (await invalid.json()).message,
      "Numerical answer is required.",
    );
    saveOutcome = "success";
    const create = {
      fields: { question: "Created", qtype_id: 2 },
      revision: "new",
      request_id: randomUUID(),
      actor_id: admin,
    };
    assert.equal((await call(own + "/new", undefined, member)).status, 200);
    assert.equal((await call(own, create, member)).status, 201);
    assert.equal(requests.at(-1).body.actor_id, member);
    assert.equal(requests.at(-1).path, `authoring/${org}/questions`);
    assert.equal(
      (await call(own, { ...create, revision: "a".repeat(64) }, member)).status,
      400,
    );
    const taxonomy = `/organisations/${org}/exam-content/taxonomy/subjects`;
    const packages = `/organisations/${org}/exam-content/taxonomy/packages`;
    assert.equal(
      (await call(packages + "/new", undefined, member)).status,
      200,
    );
    assert.equal(
      (
        await call(
          packages + "/new",
          {
            ...create,
            fields: {
              name: "Own package",
              package_type: "free",
              status: true,
              group_ids: [3],
            },
          },
          member,
        )
      ).status,
      201,
    );
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/taxonomy/packages/new`,
    );
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/taxonomy/packages/new`,
          create,
          member,
        )
      ).status,
      404,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/package-tags`,
          undefined,
          member,
        )
      ).status,
      200,
    );
    const languages = `/organisations/${org}/exam-content/taxonomy/languages`;
    for (const central of [false, true]) {
      const actor = central ? admin : member;
      const root = central
        ? `${platform}/central/taxonomy`
        : `/organisations/${org}/exam-content/taxonomy`;
      const payload = { revision: "a".repeat(64), request_id: randomUUID() };
      const target = `${root}/${central ? "languages" : "categories"}/9/delete`;
      for (const kind of [
        "categories",
        "subcategories",
        ...(central ? ["languages"] : []),
      ]) {
        const result = await call(`${root}/${kind}/9/delete`, payload, actor);
        assert.equal(result.status, 201);
        assert.deepEqual(await result.json(), { id: 9, deleted: true });
        const first = requests.at(-1);
        assert.equal(first.body.actor_id, actor);
        assert.deepEqual(first.body.fields, []);
        assert.equal(
          (await call(`${root}/${kind}/9/delete`, payload, actor)).status,
          201,
        );
        assert.deepEqual(requests.at(-1).body, first.body);
      }
      for (const [mode, status] of [
        ["conflict", 409],
        ["used", 400],
        ["wrong", 503],
        ["missing", 503],
      ]) {
        deleteOutcome = mode;
        assert.equal((await call(target, payload, actor)).status, status);
      }
      deleteOutcome = "success";
      for (const changed of [
        { ...payload, actor_id: other },
        { ...payload, fields: {} },
        { ...payload, revision: "new" },
      ])
        assert.equal((await call(target, changed, actor)).status, 400);
      assert.equal(
        (await call(target + "?organization_id=" + other, payload, actor))
          .status,
        400,
      );
      assert.equal(
        (
          await call(
            `${root}/${central ? "subjects" : "languages"}/9/delete`,
            payload,
            actor,
          )
        ).status,
        400,
      );
      assert.equal(
        (await call(`${root}/categories/new/delete`, payload, actor)).status,
        400,
      );
      if (central)
        assert.equal((await call(target, payload, member)).status, 403);
      else
        assert.equal(
          (await call(target.replace(org, other), payload, member)).status,
          404,
        );
      deleteOutcome = "revoked";
      assert.equal((await call(target, payload, actor)).status, 403);
      await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
        admin,
      ]);
      await pg.query(
        "UPDATE organisation_settings SET enabled_modules=$2::jsonb WHERE organisation_id=$1",
        [org, JSON.stringify({ exams: true })],
      );
      deleteOutcome = "success";
    }
    const disableLanguage = {
      fields: {},
      revision: "a".repeat(64),
      request_id: randomUUID(),
    };
    assert.equal(
      (await call(languages + "/9/disable", disableLanguage, member)).status,
      201,
    );
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/taxonomy/languages/9/disable`,
    );
    assert.equal(requests.at(-1).body.actor_id, member);
    for (const outcome of ["wrong", "enabled"]) {
      disableOutcome = outcome;
      assert.equal(
        (await call(languages + "/9/disable", disableLanguage, member)).status,
        503,
      );
    }
    disableOutcome = "success";
    assert.equal(
      (
        await call(
          languages + "/9/disable",
          { ...disableLanguage, fields: { value1: "unexpected" } },
          member,
        )
      ).status,
      400,
    );
    assert.equal(
      (await call(languages + "/new/disable", disableLanguage, member)).status,
      400,
    );
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/taxonomy/languages/9/disable`,
          disableLanguage,
          member,
        )
      ).status,
      404,
    );
    assert.equal(
      (await call(languages + "/new", undefined, member)).status,
      200,
    );
    assert.equal(
      (
        await call(
          languages + "/new",
          { ...create, fields: { master_language_id: 3 } },
          member,
        )
      ).status,
      201,
    );
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/taxonomy/languages/new`,
    );
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/taxonomy/languages/new`,
          create,
          member,
        )
      ).status,
      404,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/platform-languages`,
          undefined,
          member,
        )
      ).status,
      200,
    );
    const categories = `/organisations/${org}/exam-content/taxonomy/categories`;
    const subcategories = `/organisations/${org}/exam-content/taxonomy/subcategories`;
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/subcategories?parent_id=3`,
          undefined,
          member,
        )
      ).status,
      200,
    );
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/choices/subcategories?search=&after=0&parent_id=3`,
    );
    for (const query of [
      "subcategories?parent_id=0",
      "subcategories?parent_id=-1",
      "categories?parent_id=3",
      "subcategories?parent_id=3&parent_id=4",
    ]) {
      assert.equal(
        (
          await call(
            `/organisations/${org}/exam-content/choices/${query}`,
            undefined,
            member,
          )
        ).status,
        400,
      );
    }
    assert.equal(
      (await call(subcategories + "/new", undefined, member)).status,
      200,
    );
    assert.equal(
      (
        await call(
          subcategories + "/new",
          {
            ...create,
            fields: { title: "Own subcategory", parent_id: 3, status: true },
          },
          member,
        )
      ).status,
      201,
    );
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/taxonomy/subcategories/new`,
    );
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/taxonomy/subcategories/new`,
          create,
          member,
        )
      ).status,
      404,
    );
    assert.equal(
      (await call(categories + "/new", undefined, member)).status,
      200,
    );
    assert.equal(
      (
        await call(
          categories + "/new",
          {
            ...create,
            fields: { title: "Own category", status: true, group_ids: [] },
          },
          member,
        )
      ).status,
      201,
    );
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/taxonomy/categories/new`,
    );
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/taxonomy/categories/new`,
          create,
          member,
        )
      ).status,
      404,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/categories`,
          undefined,
          member,
        )
      ).status,
      200,
    );
    assert.equal(
      (await call(taxonomy + "/new", undefined, member)).status,
      200,
    );
    assert.equal(
      (
        await call(
          taxonomy + "/new",
          {
            ...create,
            fields: { subject_name: "Own subject", group_ids: [3] },
          },
          member,
        )
      ).status,
      201,
    );
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/taxonomy/subjects/new`,
    );
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/taxonomy/subjects/new`,
          create,
          member,
        )
      ).status,
      404,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/subjects?search=own&after=0`,
          undefined,
          member,
        )
      ).status,
      200,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/users`,
          undefined,
          member,
        )
      ).status,
      400,
    );
    const exams = `/organisations/${org}/exam-content/taxonomy/exams`;
    assert.equal((await call(exams + "/new", undefined, member)).status, 200);
    assert.equal(
      (
        await call(
          exams + "/new",
          { ...create, fields: { name: "Synthetic exam" } },
          member,
        )
      ).status,
      201,
    );
    assert.equal(requests.at(-1).path, `authoring/${org}/taxonomy/exams/new`);
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/exams/9/actions/add-questions`,
          { ...edit, fields: { question_ids: [9] } },
          member,
        )
      ).status,
      201,
    );
    assert.equal(requests.at(-1).body.actor_id, member);
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/exams/9/actions/destroy`,
          edit,
          member,
        )
      ).status,
      400,
    );
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/exams/9/questions`,
          undefined,
          member,
        )
      ).status,
      404,
    );
    const publication = {
      fields: { result_after_finish: false },
      revision: "a".repeat(64),
      request_id: randomUUID(),
    };
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/exams/9/actions/set-result-status`,
          publication,
          member,
        )
      ).status,
      201,
    );
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/exams/9/actions/set-result-status`,
    );
    assert.deepEqual(requests.at(-1).body.fields, {
      result_after_finish: false,
    });
    assert.equal(requests.at(-1).body.actor_id, member);
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/exams/9/actions/set-result-status`,
          publication,
          member,
        )
      ).status,
      404,
    );
    const nativeRequest = remote.request;
    const learner = randomUUID(),
      capture = randomUUID();
    const reviewPath = `/organisations/${org}/exam-proctor/${learner}/attempts`;
    const imagePath = reviewPath + `/19/captures/${capture}`;
    let invalidImage = false,
      expiredImage = false,
      revokeReview = false;
    remote.request = async (c, o, path) => {
      assert.equal(o, org);
      assert.ok(path.startsWith(`review/${org}/learners/${learner}/attempts`));
      if (revokeReview)
        await pg.query(
          "UPDATE memberships SET status='suspended' WHERE user_id=$1 AND organisation_id=$2",
          [member, org],
        );
      const expires_at = new Date(
        Date.now() + (expiredImage ? -60000 : 60000),
      ).toISOString();
      if (path.endsWith(capture))
        return {
          attempt_id: invalidImage ? 20 : 19,
          capture_id: capture,
          mime: "image/jpeg",
          base64: "/9j/",
          expires_at,
        };
      if (path.endsWith("/captures"))
        return {
          attempt_id: 19,
          items: [
            {
              attempt_id: 19,
              capture_id: capture,
              received_at: new Date().toISOString(),
              expires_at,
              base64: "PRIVATE",
            },
          ],
        };
      return {
        items: [
          {
            attempt_id: 19,
            exam_id: 2,
            exam_name: "Synthetic paper",
            started_at: null,
            finished_at: null,
            base64: "PRIVATE",
          },
        ],
        next: null,
      };
    };
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-proctor/${learner}/attempts`,
          undefined,
          member,
        )
      ).status,
      404,
    );
    assert.equal((await fetch(base + reviewPath)).status, 401);
    assert.equal(
      (
        await fetch(base + reviewPath, {
          headers: { Cookie: "t4l_exam=" + tokens[member] },
        })
      ).status,
      401,
    );
    const history = await call(reviewPath, undefined, member);
    assert.equal(history.status, 200);
    assert.equal(
      JSON.stringify(await history.json()).includes("PRIVATE"),
      false,
    );
    const metadata = await call(reviewPath + "/19/captures", undefined, member);
    assert.equal(metadata.status, 200);
    assert.equal(
      JSON.stringify(await metadata.json()).includes("PRIVATE"),
      false,
    );
    const image = await call(imagePath, undefined, member);
    assert.equal(image.status, 200);
    assert.equal(image.headers.get("content-type"), "image/jpeg");
    assert.equal(image.headers.get("cache-control"), "no-store");
    assert.equal(image.headers.get("x-content-type-options"), "nosniff");
    assert.equal(
      Buffer.from(await image.arrayBuffer()).toString("base64"),
      "/9j/",
    );
    invalidImage = true;
    assert.equal((await call(imagePath, undefined, member)).status, 503);
    invalidImage = false;
    expiredImage = true;
    assert.equal((await call(imagePath, undefined, member)).status, 503);
    expiredImage = false;
    revokeReview = true;
    assert.equal((await call(imagePath, undefined, member)).status, 404);
    revokeReview = false;
    await pg.query(
      "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    await pg.query(
      "UPDATE memberships SET scope_type='centres',scope_ids=$3 WHERE user_id=$1 AND organisation_id=$2",
      [member, org, [randomUUID()]],
    );
    assert.equal((await call(reviewPath, undefined, member)).status, 403);
    await pg.query(
      "UPDATE memberships SET scope_type='organisation',scope_ids='{}' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    const audits = await pg.query(
      "SELECT actor_id,details FROM audit_events WHERE organisation_id=$1 AND action='exams.camera.viewed'",
      [org],
    );
    assert.equal(audits.rows.length, 1);
    assert.equal(audits.rows[0].actor_id, member);
    assert.deepEqual(audits.rows[0].details, {
      learnerId: learner,
      attemptId: 19,
      captureId: capture,
    });
    await pg.query(
      "INSERT INTO examelite_workspaces(organisation_id,restrictions) VALUES($1,$2)",
      [org, ["results"]],
    );
    assert.equal((await call(imagePath, undefined, member)).status, 403);
    await pg.query(
      "DELETE FROM examelite_workspaces WHERE organisation_id=$1",
      [org],
    );
    remote.request = nativeRequest;
    const resultPath = `/organisations/${org}/exam-results/${learner}/attempts`;
    const resultSummary = {
      attempt_id: 19,
      result: "Pending",
      score_percent: 0,
      obtained_marks: 0,
      total_marks: 10,
    };
    let resultMode = "success";
    remote.request = async (c, o, path, body) => {
      if (!path.startsWith("results/")) return nativeRequest(c, o, path, body);
      requests.push({ o, path, body });
      if (resultMode === "revoke")
        await pg.query(
          "UPDATE memberships SET status='suspended' WHERE user_id=$1 AND organisation_id=$2",
          [member, org],
        );
      if (path.includes("/media/"))
        return {
          data: {
            attempt_id: resultMode === "wrong" ? 99 : 19,
            stat_id: 3,
            asset: "d".repeat(64),
            mime: "image/png",
            base64:
              "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=",
          },
        };
      if (body) {
        if (resultMode === "conflict") return { saved: false, conflict: true };
        if (resultMode === "invalid") return { saved: false };
        return {
          saved: true,
          result: {
            ...resultSummary,
            attempt_id: resultMode === "wrong" ? 99 : 19,
            private_extra: "omit",
          },
        };
      }
      if (path.includes("/19?"))
        return {
          revision: "a".repeat(64),
          summary: resultSummary,
          questions: [
            {
              stat_id: 3,
              question_id: 8,
              question_html: "Explain",
              answer_html: "Synthetic answer",
              reference_html: "Reference",
              review_supported: true,
              passage: {
                name: "Synthetic passage",
                html: "<p>Read this passage.</p>",
                private_extra: "omit",
              },
              maximum_marks: 10,
              attachment_asset: "e".repeat(64),
              exam_id: 8,
              correct_answer: "omit",
            },
          ],
        };
      return {
        next: null,
        items: [
          {
            ...resultSummary,
            exam_id: 8,
            exam_name: "Synthetic paper",
            finished_at: "2026-09-14T00:00:00Z",
            pending_count: 1,
            student_email: "omit",
          },
        ],
      };
    };
    assert.equal((await call(resultPath, undefined, member)).status, 200);
    assert.match(requests.at(-1).path, new RegExp(`actor_id=${member}`));
    const markingReview = await (
      await call(resultPath + "/19", undefined, member)
    ).json();
    const resultImagePath = resultPath + "/19/media/3/" + "d".repeat(64);
    const resultImage = await call(resultImagePath, undefined, member);
    assert.equal(resultImage.status, 200);
    assert.equal(resultImage.headers.get("content-type"), "image/png");
    assert.match(resultImage.headers.get("cache-control"), /no-store/);
    assert.equal(resultImage.headers.get("x-content-type-options"), "nosniff");
    assert.equal((await resultImage.arrayBuffer()).byteLength, 68);
    resultMode = "wrong";
    assert.equal((await call(resultImagePath, undefined, member)).status, 503);
    resultMode = "success";
    assert.equal(
      (await call(resultImagePath.replace(org, other), undefined, member))
        .status,
      404,
    );
    assert.equal(markingReview.questions[0].answer_html, "Synthetic answer");
    assert.equal(markingReview.questions[0].attachment_asset, "e".repeat(64));
    assert.equal(markingReview.questions[0].exam_id, 8);
    assert.equal(JSON.stringify(markingReview).includes("omit"), false);
    const markingBody = {
      marks: { 3: 5 },
      revision: "a".repeat(64),
      request_id: randomUUID(),
    };
    const marked = await call(resultPath + "/19", markingBody, member);
    assert.equal(marked.status, 201);
    assert.equal(requests.at(-1).body.actor_id, member);
    assert.equal(JSON.stringify(await marked.json()).includes("omit"), false);
    for (const changes of [
      { marks: {} },
      { marks: { 3: -1 } },
      { marks: { 3: "5" } },
      { actor_id: admin },
      { revision: "old" },
    ])
      assert.equal(
        (await call(resultPath + "/19", { ...markingBody, ...changes }, member))
          .status,
        400,
      );
    assert.equal(
      (await call(resultPath.replace(org, other), undefined, member)).status,
      404,
    );
    assert.equal((await fetch(base + resultPath)).status, 401);
    resultMode = "wrong";
    assert.equal(
      (await call(resultPath + "/19", markingBody, member)).status,
      503,
    );
    resultMode = "conflict";
    assert.equal(
      (await call(resultPath + "/19", markingBody, member)).status,
      409,
    );
    resultMode = "invalid";
    assert.equal(
      (await call(resultPath + "/19", markingBody, member)).status,
      400,
    );
    resultMode = "revoke";
    assert.equal((await call(resultPath, undefined, member)).status, 404);
    await pg.query(
      "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    await pg.query(
      "INSERT INTO examelite_workspaces(organisation_id,restrictions) VALUES($1,$2)",
      [org, ["results"]],
    );
    assert.equal((await call(resultPath, undefined, member)).status, 403);
    await pg.query(
      "DELETE FROM examelite_workspaces WHERE organisation_id=$1",
      [org],
    );
    const attachmentPath = `/organisations/${org}/exam-results/${learner}/exams/8/attempts/19/attachments/4/${"e".repeat(64)}`;
    const attachmentData = { exam_id: 8, attempt_id: 19, question_id: 4, asset: "e".repeat(64),
      mime: "text/plain", base64: Buffer.from("Synthetic submitted answer").toString("base64") };
    remote.request = async (c, o, path, payload, limit, timeout) => {
      assert.equal(o, org); assert.equal(path, `attachments/${org}/review`);
      assert.deepEqual(payload, { actor_id: member, learner_id: learner, exam_id: 8,
        attempt_id: 19, question_id: 4, asset: attachmentData.asset });
      assert.equal(limit, 14000000); assert.equal(timeout, 30000);
      return { data: attachmentData };
    };
    const attachmentReply = await call(attachmentPath, undefined, member);
    assert.equal(attachmentReply.status, 200);
    assert.equal(await attachmentReply.text(), "Synthetic submitted answer");
    assert.match(attachmentReply.headers.get("content-disposition"), /^attachment;/);
    assert.equal(attachmentReply.headers.get("cache-control"), "no-store");
    assert.equal(attachmentReply.headers.get("x-content-type-options"), "nosniff");
    assert.equal((await fetch(base + attachmentPath)).status, 401);
    assert.equal((await call(attachmentPath.replace(org, other), undefined, member)).status, 404);
    assert.equal((await call(attachmentPath + `?actor_id=${admin}`, undefined, member)).status, 400);
    for (const invalid of [{ exam_id: 9 }, { question_id: 5 }, { attempt_id: 20 },
      { asset: "f".repeat(64) }, { mime: "text/html" }, { base64: "YQ=" }]) {
      remote.request = async () => ({ data: { ...attachmentData, ...invalid } });
      assert.equal((await call(attachmentPath, undefined, member)).status, 503);
    }
    remote.request = async () => {
      await pg.query("UPDATE memberships SET status='suspended' WHERE user_id=$1 AND organisation_id=$2", [member, org]);
      return { data: attachmentData };
    };
    assert.equal((await call(attachmentPath, undefined, member)).status, 404);
    await pg.query("UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2", [member, org]);
    await pg.query("INSERT INTO examelite_workspaces(organisation_id,restrictions) VALUES($1,$2)", [org, ["results"]]);
    assert.equal((await call(attachmentPath, undefined, member)).status, 403);
    await pg.query("DELETE FROM examelite_workspaces WHERE organisation_id=$1", [org]);
    const attachmentAudit = await pg.query("SELECT details FROM audit_events WHERE organisation_id=$1 AND action='exams.result.attachment.viewed'", [org]);
    assert.deepEqual(attachmentAudit.rows.map(row => row.details), [{ learnerId: learner, examId: 8,
      attemptId: 19, questionId: 4, asset: attachmentData.asset }]);
    const markingAudit = await pg.query(
      "SELECT details FROM audit_events WHERE organisation_id=$1 AND action='exams.result.marked'",
      [org],
    );
    assert.deepEqual(
      markingAudit.rows.map((row) => row.details),
      [
        {
          learnerId: learner,
          attemptId: 19,
          requestId: markingBody.request_id,
        },
      ],
    );
    remote.request = nativeRequest;
    const body = {
      direction: "share",
      question_ids: [9, 2, 9],
      request_id: randomUUID(),
    };
    assert.equal(
      (await call(platform + "/transfer", body, member)).status,
      403,
    );
    assert.equal(
      (await call(platform + "/transfer", { ...body, question_ids: [-1] }))
        .status,
      400,
    );
    assert.equal((await call(platform + "/transfer", body)).status, 201);
    assert.equal(requests.at(-2).body.provision_only, true);
    assert.deepEqual(requests.at(-1).body.question_ids, [2, 9]);
    assert.equal(requests.at(-1).body.actor_id, admin);
    assert.equal(JSON.stringify(requests).includes("example.test"), false);
    await pg.query(
      "INSERT INTO examelite_workspaces(organisation_id,restrictions) VALUES($1,$2)",
      [org, ["questions"]],
    );
    assert.equal((await call(own, undefined, member)).status, 403);
    assert.equal((await call(own + "/9", edit, member)).status, 403);
    assert.equal((await call(own, create, member)).status, 403);
    assert.equal(
      (await call(taxonomy + "/new", undefined, member)).status,
      200,
    );
    await pg.query(
      "UPDATE examelite_workspaces SET restrictions=$2 WHERE organisation_id=$1",
      [org, ["questions", "subjects", "exams"]],
    );
    assert.equal((await call(documentPath, undefined, member)).status, 403);
    assert.equal((await call(packageImagePath, undefined, member)).status, 403);
    assert.equal(
      (await call(packageWritePath, packageWrite, member)).status,
      403,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/exams/9/actions/save-question-translation`,
          { ...edit, fields: validTranslationEdit },
          member,
        )
      ).status,
      403,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/exams/9/actions/approve-translation`,
          {
            ...edit,
            fields: { language_id: 5, translation_revision: "a".repeat(64) },
          },
          member,
        )
      ).status,
      403,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/exams/9/actions/generate-document`,
          {
            ...edit,
            fields: {
              package_id: 4,
              language_id: 5,
              document_type: "questions",
            },
          },
          member,
        )
      ).status,
      403,
    );
    assert.equal(
      (await call(taxonomy + "/new", undefined, member)).status,
      403,
    );
    assert.equal((await call(taxonomy + "/new", create, member)).status, 403);
    assert.equal((await call(packages + "/new", create, member)).status, 403);
    assert.equal((await call(languages + "/new", create, member)).status, 403);
    assert.equal(
      (await call(languages + "/9/disable", disableLanguage, member)).status,
      403,
    );
    assert.equal((await call(categories + "/new", create, member)).status, 403);
    assert.equal(
      (await call(subcategories + "/new", create, member)).status,
      403,
    );
    assert.equal((await call(exams + "/new", create, member)).status, 403);
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/exams`,
          undefined,
          member,
        )
      ).status,
      403,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/subjects`,
          undefined,
          member,
        )
      ).status,
      403,
    );

    assert.equal((await call(platform + "/transfer", body)).status, 403);
    assert.equal(
      (
        await call(platform + "/transfer", {
          ...body,
          direction: "pull",
          request_id: randomUUID(),
        })
      ).status,
      201,
    );
    assert.equal(
      (await call(platform + "/modules", { exams: false, version: 1 })).status,
      201,
    );
    assert.equal((await call(own, undefined, member)).status, 403);
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-workspace/launch`,
          { feature: "exams" },
          member,
        )
      ).status,
      403,
    );
    assert.equal(
      (await call(platform + "/questions?source=central")).status,
      200,
    );
    const centralPath = platform + "/central/questions/7";
    const centralImage =
      centralPath + "/" + "a".repeat(64) + "/media/" + "c".repeat(64);
    assert.equal((await call(centralPath, undefined, member)).status, 403);
    assert.equal((await call(centralImage, undefined, member)).status, 403);
    const centralResult = await call(centralPath);
    assert.equal(centralResult.status, 200);
    assert.equal(
      (await centralResult.json()).fields.question,
      "Central original",
    );
    assert.equal(requests.at(-1).path, "central/questions/7");
    const centralRaster = await call(centralImage);
    assert.equal(centralRaster.status, 200);
    assert.equal(centralRaster.headers.get("content-type"), "image/png");
    assert.equal(centralRaster.headers.get("cache-control"), "no-store");
    assert.equal(
      centralRaster.headers.get("x-content-type-options"),
      "nosniff",
    );
    assert.equal(await centralRaster.text(), "synthetic raster");
    assert.equal(
      requests.at(-1).path,
      "central/questions/7/media/" +
        "c".repeat(64) +
        "?revision=" +
        "a".repeat(64),
    );
    for (const path of [
      centralPath + "?organization_id=2",
      centralImage + "?path=secret",
      platform + "/central/questions/0",
    ]) {
      const before = requests.length;
      assert.equal((await call(path)).status, 400);
      assert.equal(requests.length, before);
    }
    for (const mode of ["wrong", "revision"]) {
      centralMode = mode;
      assert.equal((await call(centralPath)).status, 503);
    }
    for (const mode of ["wrong", "revision", "mime", "base64"]) {
      centralMode = mode;
      assert.equal((await call(centralImage)).status, 503);
    }
    for (const path of [centralPath, centralImage]) {
      centralMode = "revoked";
      assert.equal((await call(path)).status, 403);
      await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
        admin,
      ]);
    }
    {
      const paperPath = platform + "/central/exams/9/questions";
      let before = requests.length;
      assert.equal((await call(paperPath, undefined, member)).status, 403);
      assert.equal(requests.length, before);
      const response = await call(paperPath);
      assert.equal(response.status, 200);
      assert.deepEqual(await response.json(), {
        items: [{ id: 7, question: "Synthetic attached question" }],
        next: null,
      });
      assert.equal(requests.at(-1).path, "central/exams/9/questions?after=0");
      for (const suffix of [
        "?after=-1",
        "?after=1000000000000000",
        "?owner=1",
        "?after=0&after=2",
      ]) {
        before = requests.length;
        assert.equal((await call(paperPath + suffix)).status, 400);
        assert.equal(requests.length, before);
      }
      for (const id of ["0", "new", "1000000000000000"]) {
        before = requests.length;
        assert.equal(
          (await call(platform + `/central/exams/${id}/questions`)).status,
          400,
        );
        assert.equal(requests.length, before);
      }
      for (const mode of [
        "null",
        "id",
        "duplicate",
        "wording",
        "long",
        "oversize",
        "cursor",
        "missing-cursor",
      ]) {
        centralPaperMode = mode;
        assert.equal((await call(paperPath)).status, 503, mode);
      }
      centralPaperMode = "ok";
      assert.equal((await call(paperPath + "?after=7")).status, 503);
      centralPaperMode = "page";
      const page = await call(paperPath);
      assert.equal(page.status, 200);
      assert.equal((await page.json()).next, 100);
      centralPaperMode = "unicode";
      assert.equal((await call(paperPath)).status, 200);
      centralPaperMode = "revoked";
      assert.equal((await call(paperPath)).status, 403);
      await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
        admin,
      ]);
      centralPaperMode = "ok";
    }
    {
      const path = platform + "/central/exams/9/translations/5";
      const image = path + "/media/7/" + "a".repeat(64) + "/" + "c".repeat(64);
      for (const selected of [path, image]) {
        const before = requests.length;
        assert.equal((await call(selected, undefined, member)).status, 403);
        assert.equal(requests.length, before);
      }
      const response = await call(path);
      assert.equal(response.status, 200);
      assert.equal((await response.json()).exam_id, 9);
      assert.equal(
        requests.at(-1).path,
        "central/exams/9/translations/5?after=0",
      );
      const bytes = await call(image);
      assert.equal(bytes.status, 200);
      assert.equal(bytes.headers.get("cache-control"), "no-store");
      assert.equal(bytes.headers.get("x-content-type-options"), "nosniff");
      assert.equal(await bytes.text(), "synthetic raster");
      assert.equal(
        requests.at(-1).path,
        "central/exams/9/translations/5/media/7/" +
          "c".repeat(64) +
          "?revision=" +
          "a".repeat(64),
      );
      for (const selected of [
        path + "?actor_id=override",
        path + "?after=7",
        path + "?after=-1",
        path + "?revision=bad",
        image + "?path=outside",
        image.replace("/media/7/", "/media/-1/"),
      ]) {
        const before = requests.length;
        assert.equal((await call(selected)).status, 400);
        assert.equal(requests.length, before);
      }
      for (const mode of ["wrong", "counts", "field", "cursor"]) {
        translationMode = mode;
        assert.equal((await call(path)).status, 503);
      }
      for (const mode of ["wrong", "revision", "mime", "base64"]) {
        translationMode = mode;
        assert.equal((await call(image)).status, 503);
      }
      for (const selected of [path, image]) {
        translationMode = "revoked";
        assert.equal((await call(selected)).status, 403);
        await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
          admin,
        ]);
      }
      translationMode = "ok";
    }
    const choicesPath = platform + "/central/choices/groups";
    assert.equal((await call(choicesPath, undefined, member)).status, 403);
    const choicesResult = await call(choicesPath + "?search=a%26b&after=0");
    assert.equal(choicesResult.status, 200);
    assert.deepEqual(await choicesResult.json(), {
      items: [{ id: 7, label: "Central group" }],
      next: null,
    });
    assert.equal(
      requests.at(-1).path,
      "central/choices/groups?search=a%26b&after=0",
    );
    for (const path of [
      choicesPath + "?organization_id=20",
      choicesPath + "?after=-1",
      choicesPath + "?search=" + "x".repeat(121),
      platform + "/central/choices/unsupported",
    ]) {
      const before = requests.length;
      assert.equal((await call(path)).status, 400);
      assert.equal(requests.length, before);
    }
    for (const mode of [
      "malformed",
      "wrong",
      "label",
      "duplicate",
      "oversize",
      "cursor",
    ]) {
      centralChoiceMode = mode;
      assert.equal((await call(choicesPath)).status, 503);
    }
    centralChoiceMode = "ok";
    assert.equal((await call(choicesPath + "?after=7")).status, 503);
    centralChoiceMode = "revoked";
    assert.equal((await call(choicesPath)).status, 403);
    await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [admin]);
    centralChoiceMode = "ok";
    for (const kind of [
      "exams",
      "categories",
      "subcategories",
      "packages",
      "package-tags",
    ]) {
      const suffix = kind === "subcategories" ? "?parent_id=9" : "";
      assert.equal(
        (await call(platform + `/central/choices/${kind}` + suffix)).status,
        200,
      );
      assert.equal(
        requests.at(-1).path,
        `central/choices/${kind}?search=&after=0` +
          (suffix ? "&parent_id=9" : ""),
      );
    }
    for (const path of [
      "categories?parent_id=9",
      "subcategories?parent_id=0",
      "subcategories?parent_id=bad",
      "subcategories?parent_id=9&owner=20",
    ]) {
      const before = requests.length;
      assert.equal(
        (await call(platform + "/central/choices/" + path)).status,
        400,
      );
      assert.equal(requests.length, before);
    }
    const centralCreate = platform + "/central/questions";
    const taxPath = platform + "/central/taxonomy/groups/7";
    const taxNew = platform + "/central/taxonomy/groups/new";
    const taxBody = {
      fields: { group_name: "Central group" },
      revision: "a".repeat(64),
      request_id: randomUUID(),
    };
    assert.equal((await call(taxPath, undefined, member)).status, 403);
    assert.equal((await call(taxPath, taxBody, member)).status, 403);
    assert.equal((await call(taxPath)).status, 200);
    assert.equal(requests.at(-1).path, "central/taxonomy/groups/7");
    assert.equal((await (await call(taxNew)).json()).revision, "new");
    assert.equal(
      (await call(taxNew, { ...taxBody, revision: "new" })).status,
      201,
    );
    assert.deepEqual(requests.at(-1).body, {
      ...taxBody,
      revision: "new",
      actor_id: admin,
    });
    assert.equal((await call(taxPath, taxBody)).status, 201);
    assert.equal((await call(taxPath, taxBody)).status, 201);
    assert.deepEqual(requests.at(-1).body, { ...taxBody, actor_id: admin });
    for (const [kind, fields] of Object.entries({
      packages: {
        name: "Central package",
        package_type: "free",
        group_ids: [1],
        tag_ids: ["New tag"],
      },
      categories: { title: "Central category", status: true, group_ids: [1] },
      subcategories: { title: "Central child", status: true, parent_id: 1 },
      subjects: { subject_name: "Subject", group_ids: [1] },
      topics: { name: "Topic", group_id: 1, subject_id: 2 },
      subtopics: { name: "Subtopic", group_id: 1, subject_id: 2, topic_id: 3 },
      sections: { name: "Section", group_ids: [1], status: true },
    }))
      assert.equal(
        (
          await call(platform + `/central/taxonomy/${kind}/new`, {
            ...taxBody,
            fields,
            revision: "new",
          })
        ).status,
        201,
      );
    centralTaxMode = "ok";
    {
      const examPath = platform + "/central/taxonomy/exams/7";
      const newExamPath = platform + "/central/taxonomy/exams/new";
      const settings = {
        ...taxBody,
        fields: {
          name: "Synthetic exam",
          passing_percentage: 37.5,
          groups: [2],
          language_ids: [1],
        },
      };
      assert.equal((await call(examPath, undefined, member)).status, 403);
      assert.equal((await call(examPath, settings, member)).status, 403);
      const draft = await call(newExamPath);
      assert.equal(draft.status, 200);
      assert.equal((await draft.json()).timezone, "Asia/Kolkata");
      assert.equal(
        (await call(newExamPath, { ...settings, revision: "new" })).status,
        201,
      );
      assert.equal((await call(examPath, settings)).status, 201);
      assert.deepEqual(requests.at(-1).body, { ...settings, actor_id: admin });
      for (const [action, fields] of Object.entries({
        "generate-document": {
          package_id: 4,
          language_id: 5,
          document_type: "questions",
        },
        "add-questions": { question_ids: [7] },
        "remove-questions": { question_ids: [7] },
        "create-section": { name: "Paper section", duration: 10 },
        "update-section": { section_id: 2, name: "Edited" },
        "remove-section": { section_id: 2 },
        "assign-section": { question_ids: [7], question_section_id: 3 },
        "subject-timers": { subject_ids: [1], durations: [10] },
        "set-status": { status: "Active" },
        "set-result-status": { result_after_finish: true },
        "approve-translation": {
          language_id: 5,
          translation_revision: "b".repeat(64),
        },
        "refresh-translation": {
          language_id: 5,
          translation_revision: "b".repeat(64),
        },
        "set-translation-image": {
          language_id: 5,
          question_id: 7,
          translation_revision: "b".repeat(64),
          field: "si_answer1",
          image: "aW1hZ2U=",
        },
        "save-question-translation": {
          language_id: 5,
          translation_revision: "b".repeat(64),
          question_id: 7,
          wording: {
            question: "Translated wording",
            si_answer1: "Translated model answer",
          },
        },
        "save-exam-translation": {
          language_id: 5,
          translation_revision: "b".repeat(64),
          wording: { name: "Translated exam" },
        },
      })) {
        const path = platform + `/central/exams/7/actions/${action}`;
        const body = { ...taxBody, fields };
        assert.equal((await call(path, body, member)).status, 403);
        assert.equal((await call(path, body)).status, 201, action);
        assert.equal(requests.at(-1).path, `central/exams/7/actions/${action}`);
        assert.deepEqual(requests.at(-1).body, { ...body, actor_id: admin });
      }
      // Start the invalid-input/provider scenarios in a fresh rate-limit window.
      const centralExamImage = {
        ...taxBody,
        fields: {
          language_id: 5,
          question_id: 0,
          translation_revision: "b".repeat(64),
          field: "syllabus",
          image: "aW1hZ2U=",
        },
      };
      assert.equal(
        (
          await call(
            platform + "/central/exams/7/actions/set-translation-image",
            centralExamImage,
          )
        ).status,
        201,
      );
      assert.deepEqual(requests.at(-1).body.fields, centralExamImage.fields);
      await pg.query("UPDATE auth_limits SET expires_at=now() WHERE key=$1", [
        digest(`exam-central-paper-write:${admin}`),
      ]);
      const actionPath = platform + "/central/exams/7/actions/set-status";
      const actionBody = { ...taxBody, fields: { status: "Active" } };
      assert.equal((await call(actionPath, actionBody)).status, 201);
      assert.equal((await call(actionPath, actionBody)).status, 201);
      for (const [path, body] of [
        [actionPath, { ...actionBody, actor_id: member }],
        [
          platform + "/central/exams/7/actions/save-question-translation",
          {
            ...taxBody,
            fields: {
              language_id: 5,
              translation_revision: "b".repeat(64),
              question_id: 7,
              wording: { organization_id: 20 },
            },
          },
        ],
        [actionPath, { ...actionBody, fields: { organization_id: 2 } }],
        [actionPath + "?owner=2", actionBody],
        [platform + "/central/exams/new/actions/set-status", actionBody],
        [platform + "/central/exams/7/actions/generate-document", actionBody],
        ...[
          { package_id: "4", language_id: 5, document_type: "questions" },
          { package_id: 4, language_id: 0, document_type: "questions" },
          { package_id: 4, language_id: 5, document_type: ["questions"] },
          { package_id: 4, language_id: 5, document_type: "other" },
        ].map((fields) => [
          platform + "/central/exams/7/actions/generate-document",
          { ...taxBody, fields },
        ]),
        [
          platform + "/central/exams/7/actions/save-exam-translation",
          actionBody,
        ],
      ]) {
        const before = requests.length;
        assert.equal((await call(path, body)).status, 400);
        assert.equal(requests.length, before);
      }
      for (const [mode, status] of [
        ["conflict", 409],
        ["validation", 400],
        ["malformed", 503],
        ["kind", 503],
        ["id", 503],
        ["revision", 503],
        ["fields", 503],
        ["revoked", 403],
      ]) {
        centralTaxMode = mode;
        assert.equal((await call(actionPath, actionBody)).status, status, mode);
      }
      await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
        admin,
      ]);
      centralTaxMode = "ok";
    }
    const packagePath = platform + "/central/taxonomy/packages/7";
    const packageBody = { ...taxBody, fields: { name: "Central package" } };
    assert.equal((await call(packagePath, undefined, member)).status, 403);
    assert.equal((await call(packagePath, packageBody, member)).status, 403);
    assert.equal(
      (await call(platform + "/central/taxonomy/packages/new")).status,
      200,
    );
    assert.equal((await call(packagePath, packageBody)).status, 201);
    assert.equal((await (await call(packagePath)).json()).photo_asset, null);
    for (const fields of [
      { package_type: "paid" },
      { photo: "/etc/passwd" },
      { organization_id: 20 },
    ]) {
      const count = requests.length;
      assert.equal(
        (await call(packagePath, { ...taxBody, fields })).status,
        400,
      );
      assert.equal(requests.length, count);
    }
    for (const mode of ["asset", "paid"]) {
      centralTaxMode = mode;
      assert.equal((await call(packagePath)).status, 503);
      assert.equal(
        (await call(platform + "/central/taxonomy/packages/new")).status,
        503,
      );
    }
    centralTaxMode = "ok";
    centralMode = "ok";
    {
      const packageImagePath = platform + "/central/packages/7/image";
      const packagePreview =
        platform + "/central/packages/7/media/" + "c".repeat(64);
      const upload = {
        ...taxBody,
        fields: { image: Buffer.from("synthetic").toString("base64") },
      };
      assert.equal((await call(packageImagePath, upload, member)).status, 403);
      assert.equal((await call(packagePreview, undefined, member)).status, 403);
      assert.equal((await call(packageImagePath, upload)).status, 201);
      assert.equal(requests.at(-1).path, "central/packages/7/image");
      assert.deepEqual(requests.at(-1).body, { ...upload, actor_id: admin });
      assert.equal((await call(packageImagePath, upload)).status, 201);
      assert.equal(
        (
          await call(packageImagePath, {
            ...upload,
            fields: { remove: true, asset: "c".repeat(64) },
          })
        ).status,
        201,
      );
      const preview = await call(packagePreview);
      assert.equal(preview.status, 200);
      assert.equal(preview.headers.get("content-type"), "image/png");
      assert.equal(preview.headers.get("cache-control"), "no-store");
      assert.equal(await preview.text(), "synthetic raster");
      for (const fields of [
        { field: "question", image: "abc" },
        { photo: "outside" },
        { image: "x".repeat(699053) },
        { remove: true },
        { remove: true, asset: "c".repeat(64), image: "abc" },
        { image: "abc", asset: "bad" },
      ]) {
        const count = requests.length;
        assert.equal(
          (await call(packageImagePath, { ...upload, fields })).status,
          400,
        );
        assert.equal(requests.length, count);
      }
      assert.equal(
        (await call(platform + "/central/packages/new/image", upload)).status,
        400,
      );
      assert.equal((await call(packagePreview + "?path=outside")).status, 400);
      for (const mode of ["wrong", "mime", "base64"]) {
        centralMode = mode;
        assert.equal((await call(packagePreview)).status, 503);
      }
      centralMode = "revoked";
      assert.equal((await call(packagePreview)).status, 403);
      await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
        admin,
      ]);
      centralMode = "ok";
      centralTaxMode = "revoked";
      assert.equal((await call(packageImagePath, upload)).status, 403);
      await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
        admin,
      ]);
      centralTaxMode = "ok";
    }
    for (const invalid of [
      { ...taxBody, actor_id: member },
      { ...taxBody, fields: { organization_id: 20 } },
      { ...taxBody, fields: {} },
      { ...taxBody, fields: [] },
      { ...taxBody, fields: { group_name: "छ".repeat(90000) } },
      { ...taxBody, revision: "new" },
      { ...taxBody, request_id: "bad" },
    ]) {
      const before = requests.length;
      assert.equal((await call(taxPath, invalid)).status, 400);
      assert.equal(requests.length, before);
    }
    for (const path of [
      taxPath + "?owner=20",
      platform + "/central/taxonomy/unsupported/new",
    ]) {
      const before = requests.length;
      assert.equal((await call(path)).status, 400);
      assert.equal(requests.length, before);
    }
    for (const mode of ["kind", "id", "revision", "fields", "malformed"]) {
      centralTaxMode = mode;
      assert.equal((await call(taxPath)).status, 503);
    }
    centralTaxMode = "conflict";
    assert.equal((await call(taxPath, taxBody)).status, 409);
    centralTaxMode = "validation";
    assert.equal((await call(taxPath, taxBody)).status, 400);
    centralTaxMode = "malformed";
    assert.equal((await call(taxPath, taxBody)).status, 503);
    for (const body of [undefined, taxBody]) {
      centralTaxMode = "revoked";
      assert.equal((await call(taxPath, body)).status, 403);
      await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
        admin,
      ]);
    }
    await pg.query("UPDATE auth_limits SET expires_at=now() WHERE key=$1", [
      digest(`exam-central-classification-write:${admin}`),
    ]);
    centralTaxMode = "ok";
    const languagePath = platform + "/central/taxonomy/languages";
    const centralLanguage = {
      fields: {
        name: "New language",
        code: "new",
        value1: "True",
        value2: "False",
      },
      revision: "new",
      request_id: randomUUID(),
    };
    assert.equal(
      (await call(languagePath + "/new", undefined, member)).status,
      403,
    );
    assert.equal((await call(languagePath + "/new")).status, 200);
    assert.equal(
      (await call(languagePath + "/new", centralLanguage)).status,
      201,
    );
    const languageRequest = requests.at(-1);
    assert.equal(languageRequest.path, "central/taxonomy/languages/new");
    assert.equal(languageRequest.body.actor_id, admin);
    assert.equal(
      (await call(languagePath + "/new", centralLanguage)).status,
      201,
    );
    assert.deepEqual(requests.at(-1).body, languageRequest.body);
    assert.equal(
      (
        await call(languagePath + "/7", {
          ...centralLanguage,
          fields: { value1: null },
          revision: "a".repeat(64),
        })
      ).status,
      201,
    );
    for (const fields of [
      { master_language_id: 1 },
      { name: ["array"] },
      { code: "x".repeat(21) },
      { value2: "<b>False</b>" },
      { name: null },
    ])
      assert.equal(
        (await call(languagePath + "/new", { ...centralLanguage, fields }))
          .status,
        400,
      );
    centralTaxMode = "language-type";
    assert.equal((await call(languagePath + "/7")).status, 503);
    centralTaxMode = "revoked";
    assert.equal(
      (await call(languagePath + "/new", centralLanguage)).status,
      403,
    );
    await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [admin]);
    centralTaxMode = "ok";
    const imageWritePath = centralPath + "/image";
    const centralUpload = {
      fields: {
        field: "explanation",
        image: Buffer.from("synthetic image").toString("base64"),
      },
      revision: "a".repeat(64),
      request_id: randomUUID(),
    };
    assert.equal(
      (await call(imageWritePath, centralUpload, member)).status,
      403,
    );
    assert.equal((await call(imageWritePath, centralUpload)).status, 201);
    assert.equal(requests.at(-1).path, "central/questions/7/image");
    assert.deepEqual(requests.at(-1).body, {
      ...centralUpload,
      actor_id: admin,
    });
    assert.equal((await call(imageWritePath, centralUpload)).status, 201);
    assert.deepEqual(requests.at(-1).body, {
      ...centralUpload,
      actor_id: admin,
    });
    const centralRemoval = {
      ...centralUpload,
      fields: { field: "explanation", asset: "c".repeat(64), remove: true },
      request_id: randomUUID(),
    };
    assert.equal((await call(imageWritePath, centralRemoval)).status, 201);
    for (const fields of [
      { field: "organization_id", image: "abc" },
      { ...centralUpload.fields, organization_id: 20 },
      { field: "question", remove: true },
      { field: "question", remove: true, asset: "c".repeat(64), image: "abc" },
      { field: "question", image: "x".repeat(699053) },
      { field: "question", image: "abc", asset: "invalid" },
    ]) {
      const before = requests.length;
      assert.equal(
        (await call(imageWritePath, { ...centralUpload, fields })).status,
        400,
      );
      assert.equal(requests.length, before);
    }
    assert.equal(
      (await call(imageWritePath + "?actor_id=other", centralUpload)).status,
      400,
    );
    assert.equal(
      (
        await call(platform + "/central/questions/new/image", {
          ...centralUpload,
          revision: "new",
        })
      ).status,
      400,
    );
    for (const mode of [
      "wrong",
      "revision",
      "fields",
      "preview",
      "malformed",
    ]) {
      centralWriteMode = mode;
      assert.equal((await call(imageWritePath, centralUpload)).status, 503);
    }
    centralWriteMode = "revoked";
    assert.equal((await call(imageWritePath, centralUpload)).status, 403);
    await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [admin]);
    centralWriteMode = "ok";
    const beforeNew = requests.length;
    assert.equal(
      (await call(centralCreate + "/new", undefined, member)).status,
      403,
    );
    assert.equal(
      (await call(centralCreate + "/new?organization_id=20")).status,
      400,
    );
    const emptyCentral = await call(centralCreate + "/new");
    assert.equal(emptyCentral.status, 200);
    const emptyCentralBody = await emptyCentral.json();
    assert.equal(emptyCentralBody.id, 0);
    assert.equal(emptyCentralBody.revision, "new");
    assert.deepEqual(emptyCentralBody.fields.group_ids, []);
    assert.equal(
      requests.length,
      beforeNew,
      "Opening a central draft does not provision an organisation workspace",
    );
    const newCentral = {
      fields: {
        question: "Created central original",
        qtype_id: 2,
        nat_value: 12,
      },
      revision: "new",
      request_id: randomUUID(),
    };
    const editCentral = {
      fields: { question: "Edited central original" },
      revision: "a".repeat(64),
      request_id: randomUUID(),
    };
    assert.equal((await call(centralCreate, newCentral, member)).status, 403);
    assert.equal((await call(centralPath, editCentral, member)).status, 403);
    const createdCentral = await call(centralCreate, newCentral);
    assert.equal(createdCentral.status, 201);
    assert.equal((await createdCentral.json()).id, 77);
    assert.equal(requests.at(-1).path, "central/questions");
    assert.deepEqual(requests.at(-1).body, { ...newCentral, actor_id: admin });
    assert.equal((await call(centralCreate, newCentral)).status, 201);
    assert.deepEqual(
      requests.at(-1).body,
      { ...newCentral, actor_id: admin },
      "Retry preserves actor, revision and request identity",
    );
    assert.equal((await call(centralPath, editCentral)).status, 201);
    assert.equal(requests.at(-1).path, "central/questions/7");
    for (const invalid of [
      { ...editCentral, actor_id: member },
      { ...editCentral, organization_id: 2 },
      { ...editCentral, fields: {} },
      { ...editCentral, fields: [] },
      { ...editCentral, fields: { organization_id: 2 } },
      { ...editCentral, fields: { is_platform_admin: true } },
      { ...editCentral, fields: { question: "छ".repeat(90000) } },
      { ...editCentral, revision: "new" },
      { ...editCentral, request_id: "invalid" },
    ]) {
      const before = requests.length;
      assert.equal((await call(centralPath, invalid)).status, 400);
      assert.equal(requests.length, before);
    }
    assert.equal((await call(centralCreate, editCentral)).status, 400);
    assert.equal(
      (await call(centralPath + "?actor_id=" + member, editCentral)).status,
      400,
    );
    centralWriteMode = "conflict";
    assert.equal((await call(centralPath, editCentral)).status, 409);
    centralWriteMode = "validation";
    const invalidCentral = await call(centralPath, editCentral);
    assert.equal(invalidCentral.status, 400);
    assert.match(
      (await invalidCentral.json()).message,
      /A question is required/,
    );
    for (const mode of [
      "wrong",
      "revision",
      "fields",
      "preview",
      "malformed",
    ]) {
      centralWriteMode = mode;
      assert.equal((await call(centralPath, editCentral)).status, 503);
    }
    centralWriteMode = "revoked";
    assert.equal((await call(centralPath, editCentral)).status, 403);
    await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [admin]);
  } finally {
    await app.close();
    await pg.close();
  }
});
