import { request as httpRequest } from "node:http";
import { test, mock } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { Resolver } from "node:dns/promises";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { configurationMigration } from "../dist/migration-configuration.js";
import { createApp } from "../dist/bootstrap.js";
import { createDemo, seedDemoLearners, removeDemo } from "../dist/demo.js";
import { hashPassword } from "../dist/security.js";

test("organisation setup, domains, module controls and reusable custom fields", async (t) => {
  const pg = new PGlite();
  await pg.exec(migration);
  await pg.exec(accessMigration);
  await pg.exec(learnerMigration);
  const db = {
    query: (s, p) => pg.query(s, p),
    transaction: (run) =>
      pg.transaction((c) =>
        run({
          query: (s, p) =>
            p?.length ? c.query(s, p) : c.exec(s).then((r) => r.at(-1)),
        }),
      ),
    onModuleDestroy: async () => {},
  };
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES ($1,$2,$3,$4,true)",
    [
      randomUUID(),
      "owner@config.test",
      "Synthetic owner",
      await hashPassword("Synthetic configuration password"),
    ],
  );
  const demo = await createDemo(db);
  await seedDemoLearners(db, demo.datasetId);
  const before = (
    await pg.query("SELECT id,key FROM learner_fields ORDER BY id")
  ).rows;
  await pg.exec(configurationMigration);
  assert.deepEqual(
    (await pg.query("SELECT id,key FROM learner_fields ORDER BY id")).rows,
    before,
  );
  const app = await createApp(undefined, db);
  app.useLogger(false);
  await app.listen(0, "127.0.0.1");
  const base = (await app.getUrl()) + "/api/v1";
  async function req(path, method = "GET", body, cookie, headers = {}) {
    const options = {
      method,
      headers: {
        Origin: "http://localhost:5173",
        ...(body === undefined
          ? {}
          : {
              "Content-Type": "application/json",
              "X-Tech4Learn-Request": "1",
            }),
        ...(cookie ? { Cookie: cookie } : {}),
        ...headers,
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    };
    if (headers.Host)
      return new Promise((resolve, reject) => {
        const r = httpRequest(
          base + path,
          { method, headers: options.headers },
          (res) => {
            const chunks = [];
            res.on("data", (c) => chunks.push(c));
            res.on("end", () =>
              resolve(
                new Response(Buffer.concat(chunks), { status: res.statusCode }),
              ),
            );
          },
        );
        r.on("error", reject);
        r.end(options.body);
      });
    return fetch(base + path, options);
  }
  async function ok(path, method = "GET", body, cookie, status = 200) {
    const r = await req(path, method, body, cookie);
    assert.equal(r.status, status, await r.clone().text());
    return r.json();
  }
  async function login(email, password) {
    const r = await req("/auth/login", "POST", { email, password });
    assert.equal(r.status, 200);
    return r.headers.get("set-cookie").split(";")[0];
  }
  const owner = await login(
    "owner@config.test",
    "Synthetic configuration password",
  );
  async function demoLogin(n) {
    const a = demo.accounts.find((a) => a.name === n);
    return login(a.email, a.password);
  }
  const admin = await demoLogin("DEMO admin"),
    teacher = await demoLogin("DEMO teacher"),
    otherAdmin = await demoLogin("DEMO academy-admin");
  const org = demo.organisations[0].id,
    other = demo.organisations[1].id,
    p = "/organisations/" + org;
  const groups = await ok(p + "/groups", "GET", undefined, admin),
    teacherGroup = (await ok(p + "/groups", "GET", undefined, teacher))[0],
    centre = teacherGroup.centre_id;
  let config;
  try {
    await t.test(
      "onboarding creates a branded address; private settings and module controls stay scoped",
      async () => {
        const created = await ok(
          "/organisations",
          "POST",
          {
            name: "Synthetic new NGO",
            slug: "synthetic-new-ngo",
            kind: "school",
            adminEmail: "new-admin@config.test",
          },
          owner,
          201,
        );
        assert.equal(
          (
            await ok(
              "/organisations/" + created.organisation.id + "/configuration",
              "GET",
              undefined,
              owner,
            )
          ).kind,
          "school",
        );
        config = await ok(p + "/configuration", "GET", undefined, admin);
        assert.equal(config.version, 0);
        assert.equal(
          (await req(p + "/configuration", "GET", undefined, teacher)).status,
          403,
        );
        assert.equal(
          (await req(p + "/configuration", "GET", undefined, otherAdmin))
            .status,
          404,
        );
        config = await ok(
          p + "/configuration",
          "PATCH",
          {
            ...config,
            kind: "school",
            template: "academy",
            welcome: "Synthetic welcome",
          },
          admin,
        );
        assert.equal(config.kind, "school");
        assert.equal(
          (
            await req(
              p + "/configuration",
              "PATCH",
              { ...config, kind: "unknown-type" },
              admin,
            )
          ).status,
          400,
        );
        const slug = demo.organisations[0].slug;
        const branding = await ok("/public/branding?slug=" + slug);
        assert.equal(branding.welcome, "Synthetic welcome");
        assert.equal(branding.template, "academy");
        assert.ok(!("enabled_modules" in branding));
        assert.ok(!("domain" in branding));
        assert.equal(
          (
            await req(
              p + "/configuration",
              "PATCH",
              { ...config, version: 0 },
              admin,
            )
          ).status,
          409,
        );
        assert.equal(
          (
            await req(
              p + "/configuration",
              "PATCH",
              { ...config, logo: "data:image/svg+xml;base64,AAAA" },
              admin,
            )
          ).status,
          400,
        );
        assert.equal(
          (
            await req(
              p + "/configuration",
              "PATCH",
              {
                ...config,
                enabled_modules: { ...config.enabled_modules, learners: false },
              },
              admin,
            )
          ).status,
          403,
        );
        config = await ok(
          p + "/configuration",
          "PATCH",
          {
            ...config,
            enabled_modules: { ...config.enabled_modules, learners: false },
          },
          owner,
        );
        assert.equal(
          (await req(p + "/learners", "GET", undefined, admin)).status,
          403,
        );
        assert.equal(
          (await req(p + "/learners", "GET", undefined, teacher)).status,
          403,
        );
        config = await ok(
          p + "/configuration",
          "PATCH",
          {
            ...config,
            enabled_modules: { ...config.enabled_modules, learners: true },
          },
          owner,
        );
        assert.ok(
          (await ok(p + "/learners", "GET", undefined, admin)).items.length,
        );
      },
    );
    await t.test(
      "custom fields are module-specific, typed, versioned and scoped to the record",
      async () => {
        const definition = {
          key: "local_code",
          label: "Local code",
          kind: "text",
          required: true,
          archived: false,
          options: [],
        };
        const field = await ok(
          p + "/fields/centres",
          "POST",
          definition,
          admin,
          201,
        );
        await ok(p + "/fields/groups", "POST", definition, admin, 201);
        await ok(
          p + "/fields/staff",
          "POST",
          { ...definition, required: false },
          admin,
          201,
        );
        await ok(
          p + "/fields/organisation",
          "POST",
          { ...definition, required: false },
          admin,
          201,
        );
        await ok(
          p + "/fields/attendance",
          "POST",
          { ...definition, required: false },
          admin,
          201,
        );
        assert.equal(
          (await req(p + "/fields/centres", "POST", definition, admin)).status,
          409,
        );
        assert.equal(
          (
            await req(
              p + "/fields/centres",
              "POST",
              { ...definition, key: "other" },
              teacher,
            )
          ).status,
          403,
        );
        assert.equal(
          (await req(p + "/fields/centres", "GET", undefined, otherAdmin))
            .status,
          404,
        );
        assert.equal(
          (await req(p + "/fields/unknown", "GET", undefined, admin)).status,
          404,
        );
        const route = p + "/field-values/centres/" + centre;
        assert.equal(
          (await req(route, "PATCH", { values: {}, version: 0 }, admin)).status,
          400,
        );
        const saved = await ok(
          route,
          "PATCH",
          { values: { local_code: "SYN-01" }, version: 0 },
          admin,
        );
        assert.equal(saved.version, 1);
        assert.equal(
          (await ok(route, "GET", undefined, teacher)).values.local_code,
          "SYN-01",
        );
        assert.equal(
          (
            await req(
              route,
              "PATCH",
              { values: { local_code: "change" }, version: 1 },
              teacher,
            )
          ).status,
          403,
        );
        assert.equal(
          (
            await req(
              route,
              "PATCH",
              { values: { local_code: "stale" }, version: 0 },
              admin,
            )
          ).status,
          409,
        );
        assert.equal(
          (await req(route, "GET", undefined, otherAdmin)).status,
          404,
        );
        const outside = groups.find((g) => g.centre_id !== centre);
        assert.equal(
          (
            await req(
              p + "/field-values/centres/" + outside.centre_id,
              "GET",
              undefined,
              teacher,
            )
          ).status,
          404,
        );
        await ok(
          p + "/fields/centres/" + field.id,
          "PATCH",
          { ...definition, archived: true },
          admin,
        );
        assert.equal(
          (
            await req(
              route,
              "PATCH",
              { values: { local_code: "overwrite" }, version: 1 },
              admin,
            )
          ).status,
          400,
        );
        assert.equal(
          (await ok(route, "PATCH", { values: {}, version: 1 }, admin)).values
            .local_code,
          "SYN-01",
        );
        assert.equal(
          (
            await req(
              p + "/field-values/attendance/" + randomUUID(),
              "GET",
              undefined,
              admin,
            )
          ).status,
          400,
        );
        const audit = (
          await pg.query(
            "SELECT details FROM audit_events WHERE action='custom_values.saved'",
          )
        ).rows;
        assert.ok(!JSON.stringify(audit).includes("SYN-01"));
      },
    );
    await t.test(
      "domains require unique ownership proof, superadmin activation and exact-origin writes",
      async () => {
        for (const hostname of [
          "https://learn.example.org",
          "127.0.0.1",
          "*.example.org",
          "localhost",
          "learn.example.org/path",
        ])
          assert.equal(
            (await req(p + "/domain", "POST", { hostname }, admin)).status,
            400,
          );
        const d = await ok(
          p + "/domain",
          "POST",
          { hostname: "learn.synthetic-example.org" },
          admin,
          201,
        );
        assert.equal(
          (
            await req(
              "/organisations/" + other + "/domain",
              "POST",
              { hostname: d.hostname },
              otherAdmin,
            )
          ).status,
          409,
        );
        const dns = mock.method(Resolver.prototype, "resolveTxt", async () => [
          ["wrong"],
        ]);
        assert.equal(
          (await req(p + "/domain/verify", "POST", {}, admin)).status,
          400,
        );
        dns.mock.mockImplementation(async (name) => {
          assert.equal(name, "_tech4learn." + d.hostname);
          return [["tech4learn-verification=", d.challenge]];
        });
        assert.equal(
          (await ok(p + "/domain/verify", "POST", {}, admin, 201)).active,
          false,
        );
        assert.equal(
          (
            await req(
              p + "/domain/activate",
              "POST",
              { httpsConfigured: true },
              admin,
            )
          ).status,
          403,
        );
        assert.equal(
          (await req(p + "/domain/activate", "POST", {}, owner)).status,
          400,
        );
        const headers = { Host: d.hostname, Origin: "https://" + d.hostname };
        assert.equal(
          (
            await req(
              "/auth/login",
              "POST",
              {
                email: "owner@config.test",
                password: "Synthetic configuration password",
              },
              undefined,
              headers,
            )
          ).status,
          403,
        );
        await ok(
          p + "/domain/activate",
          "POST",
          { httpsConfigured: true },
          owner,
          201,
        );
        assert.equal(
          (
            await req(
              "/auth/login",
              "POST",
              {
                email: "owner@config.test",
                password: "Synthetic configuration password",
              },
              undefined,
              headers,
            )
          ).status,
          200,
        );
        assert.equal(
          (
            await req("/auth/login", "POST", {}, undefined, {
              Host: d.hostname,
              Origin: "https://evil.example.org",
            })
          ).status,
          403,
        );
        const sessionOnHost = await req(
          "/auth/me",
          "GET",
          undefined,
          owner,
          headers,
        );
        assert.equal((await sessionOnHost.json()).organisations.length, 1);
        assert.equal(
          (
            await req(
              "/organisations/" + other + "/groups",
              "GET",
              undefined,
              owner,
              headers,
            )
          ).status,
          403,
        );
        assert.equal(
          (await req("/organisations", "POST", {}, owner, headers)).status,
          403,
        );
        const brand = await req(
          "/public/branding?slug=" + demo.organisations[1].slug,
          "GET",
          undefined,
          undefined,
          { Host: d.hostname },
        );
        assert.equal((await brand.json()).id, org);
        await ok(p + "/domain/remove", "POST", {}, admin, 201);
        assert.equal(
          (await req("/auth/login", "POST", {}, undefined, headers)).status,
          403,
        );
        dns.mock.restore();
      },
    );
    await t.test(
      "demo cleanup removes cross-module values without touching another organisation",
      async () => {
        await removeDemo(db, demo.datasetId);
        assert.equal(
          (await pg.query("SELECT * FROM record_field_values")).rows.length,
          0,
        );
        assert.equal(
          (await pg.query("SELECT * FROM custom_fields")).rows.length,
          0,
        );
        assert.equal(
          (
            await pg.query(
              "SELECT id FROM organisations WHERE slug='synthetic-new-ngo'",
            )
          ).rows.length,
          1,
        );
      },
    );
  } finally {
    mock.restoreAll();
    await app.close();
    await pg.close();
  }
});
