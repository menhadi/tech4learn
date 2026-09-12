import { DraftForm } from "./DraftForm";
import { OrganisationTypeSelect } from "./OrganisationTypeSelect";
import { useEffect, useState, type FormEvent } from "react";
import { api } from "./api";
export type Setup = {
  kind: string;
  template: string;
  welcome: string;
  logo: string;
  version: number;
  enabled_modules: Record<string, boolean>;
  domain: {
    hostname: string;
    challenge: string;
    verified_at: string | null;
    active: boolean;
  } | null;
};
export function OrganisationSetup({
  org,
  slug,
  superadmin,
  editable,
  onSaved,
}: {
  org: string;
  slug: string;
  superadmin: boolean;
  editable: boolean;
  onSaved: () => void;
}) {
  const [data, setData] = useState<Setup | null>(null),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [busy, setBusy] = useState(false);
  const base = `/organisations/${org}`;
  async function load() {
    setData(await api<Setup>(base + "/configuration"));
  }
  useEffect(() => {
    load().catch((e) => setError(e.message));
  }, [org]);
  async function act(work: () => Promise<unknown>, message: string) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await work();
      await load();
      onSaved();
      setNotice(message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to save.");
    } finally {
      setBusy(false);
    }
  }
  if (!data)
    return <p role="status">{error || "Loading organisation setup…"}</p>;
  const address = `${location.origin}/?org=${encodeURIComponent(slug)}`;
  return (
    <div className="setup-stack">
      <h3>Organisation setup</h3>
      <p>
        1. Create the organisation → 2. Set branding and address → 3. Choose
        modules → 4. Invite your team.
      </p>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      <label>
        Organisation sign-in link
        <input readOnly value={address} />
      </label>
      <a href={address} target="_blank" rel="noreferrer">
        Preview organisation sign-in
      </a>
      <DraftForm title="Branding and modules" draftKey={"organisation-setup"}
        draftState={{kind:data.kind,template:data.template,welcome:data.welcome,logo:data.logo,enabled_modules:data.enabled_modules}}
        restoreState={v=>setData({...data,kind:v.kind,template:v.template,welcome:v.welcome,logo:v.logo,enabled_modules:v.enabled_modules})}
        onSubmit={(e: FormEvent) => {
          e.preventDefault();
          void act(
            () => api(base + "/configuration", "PATCH", data),
            "Organisation setup saved.",
          );
        }}
      >
        <fieldset disabled={busy || !editable}>
          <label>
            Organisation type
            <OrganisationTypeSelect
              required
              value={data.kind}
              onChange={(e) => setData({ ...data, kind: e.target.value })}
            />
          </label>
          <label>
            Presentation template
            <select
              value={data.template}
              onChange={(e) => setData({ ...data, template: e.target.value })}
            >
              {["community", "academy", "minimal"].map((k) => (
                <option key={k}>{k}</option>
              ))}
            </select>
          </label>
          <p>
            Templates change presentation. Your records, role permissions and
            field definitions stay intact.
          </p>
          <label>
            Welcome message
            <textarea
              maxLength={400}
              value={data.welcome}
              onChange={(e) => setData({ ...data, welcome: e.target.value })}
            />
          </label>
          <label>
            Organisation logo
            <input
              type="file"
              accept="image/png,image/jpeg,image/webp"
              onChange={(e) => {
                const f = e.target.files?.[0];
                if (!f) return;
                if (
                  f.size > 130000 ||
                  !["image/png", "image/jpeg", "image/webp"].includes(f.type)
                ) {
                  setError("Use a PNG, JPEG or WebP logo under 130 KB.");
                  return;
                }
                const r = new FileReader();
                r.onload = () => setData({ ...data, logo: String(r.result) });
                r.readAsDataURL(f);
              }}
            />
          </label>
          {data.logo && (
            <>
              <img
                className="organisation-logo"
                src={data.logo}
                alt="Organisation logo preview"
              />
              <button
                type="button"
                className="secondary"
                onClick={() => setData({ ...data, logo: "" })}
              >
                Remove logo
              </button>
            </>
          )}
          <h4>Modules</h4>
          <p>
            Platform superadmins choose availability. Organisation admins assign
            staff permissions in Roles and Team.
          </p>
          {Object.entries(data.enabled_modules).map(([key, enabled]) => (
            <label className="check" key={key}>
              <input
                type="checkbox"
                checked={enabled}
                disabled={!superadmin}
                onChange={(e) =>
                  setData({
                    ...data,
                    enabled_modules: {
                      ...data.enabled_modules,
                      [key]: e.target.checked,
                    },
                  })
                }
              />
              {key === "fln"
                ? "FLN assessment"
                : key === "exams"
                  ? "ExamElite exams"
                  : key}
              {["fln", "exams"].includes(key)
                ? " — planned; preference only"
                : ""}
            </label>
          ))}
          <button>Save setup</button>
        </fieldset>
      </DraftForm>
      <section className="subpanel">
        <h3>Custom domain</h3>
        <p>
          A custom hostname needs DNS ownership verification and HTTPS/proxy
          setup by your platform administrator. Saving a hostname does not make
          it live.
        </p>
        {!data.domain ? (
          <DraftForm title="Custom domain" draftKey={"custom-domain"}
            onSubmit={(e) => {
              e.preventDefault();
              const b = Object.fromEntries(new FormData(e.currentTarget));
              void act(
                () => api(base + "/domain", "POST", b),
                "Domain saved. Add the TXT record below.",
              );
            }}
          >
            <fieldset disabled={busy || !editable}>
              <label>
                Hostname
                <input
                  name="hostname"
                  placeholder="learn.example.org"
                  required
                  maxLength={253}
                />
              </label>
              <button>Set domain</button>
            </fieldset>
          </DraftForm>
        ) : (
          <>
            <p>
              <strong>{data.domain.hostname}</strong> ·{" "}
              {data.domain.active
                ? "Active"
                : data.domain.verified_at
                  ? "Ownership verified · hosting activation pending"
                  : "Verification pending"}
            </p>
            <label>
              DNS TXT name
              <input readOnly value={"_tech4learn." + data.domain.hostname} />
            </label>
            <label>
              DNS TXT value
              <input
                readOnly
                value={"tech4learn-verification=" + data.domain.challenge}
              />
            </label>
            <button
              disabled={busy || !editable}
              onClick={() =>
                void act(
                  () => api(base + "/domain/verify", "POST", {}),
                  "DNS ownership verified.",
                )
              }
            >
              Verify ownership
            </button>
            {superadmin && !data.domain.active && (
              <DraftForm title="Domain settings" draftKey={"domain-settings"}
                onSubmit={(e) => {
                  e.preventDefault();
                  void act(
                    () =>
                      api(base + "/domain/activate", "POST", {
                        httpsConfigured: true,
                      }),
                    "Domain activated. Test sign-in on its HTTPS address.",
                  );
                }}
              >
                <label className="check">
                  <input type="checkbox" required />I have configured and
                  checked this hostname’s DNS routing, HTTPS certificate and
                  proxy that preserves the Host header.
                </label>
                <button disabled={busy || !data.domain.verified_at}>
                  Activate domain
                </button>
              </DraftForm>
            )}
            <details>
              <summary>Remove domain</summary>
              <p>
                This immediately disables its application binding. Update its
                DNS and hosting separately.
              </p>
              <button
                disabled={busy || !editable}
                onClick={() =>
                  void act(
                    () => api(base + "/domain/remove", "POST", {}),
                    "Domain removed.",
                  )
                }
              >
                Confirm removal
              </button>
            </details>
          </>
        )}
      </section>
    </div>
  );
}
