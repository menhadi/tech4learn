import { GroupedMenu } from "./GroupedMenu";
import { DirectoryTable } from "./DirectoryTable";
import { DraftScope } from "./DraftForm";
import { clearDrafts } from "./form-drafts";
import { DraftForm } from "./DraftForm";
import { FaceEngine } from "./FaceEngine";
import { OrganisationTypeSelect } from "./OrganisationTypeSelect";
import {
  StrictMode,
  useEffect,
  useState,
  type FormEvent,
  type CSSProperties,
} from "react";
import { createRoot } from "react-dom/client";
import type {
  Invitation,
  InvitationPreview,
  Organisation,
  SessionResponse,
} from "@tech4learn/contracts";
import "./styles.css";

import { api, ApiError } from "./api";
import { OrganisationWorkspace } from "./OrganisationWorkspace";
const message = (error: unknown) =>
  error instanceof Error
    ? error.message
    : "Unable to connect. Please try again.";
function values(event: FormEvent<HTMLFormElement>) {
  event.preventDefault();
  return Object.fromEntries(new FormData(event.currentTarget));
}
function PasswordField({
  label = "Password",
  name = "password",
  isNew = false,
}: {
  label?: string;
  name?: string;
  isNew?: boolean;
}) {
  return (
    <label>
      {label}
      <input
        name={name}
        type="password"
        autoComplete={isNew ? "new-password" : "current-password"}
        minLength={isNew ? 15 : undefined}
        maxLength={128}
        required
      />
      {isNew && (
        <small>At least 15 characters. A memorable phrase works well.</small>
      )}
    </label>
  );
}

type Branding = {
  id: string;
  slug: string;
  name: string;
  colour: string;
  template: string;
  welcome: string;
  logo: string;
};
function App() {
  const [organisationMenuOpen, setOrganisationMenuOpen] = useState(false);
  const requestedSlug = new URLSearchParams(location.search).get("org") || "";
  const [branding, setBranding] = useState<Branding | null>(null);
  const [brandError, setBrandError] = useState("");
  useEffect(() => {
    let active = true;
    api<Branding | null>(
      "/public/branding" +
        (requestedSlug ? "?slug=" + encodeURIComponent(requestedSlug) : ""),
    )
      .then((b) => {
        if (active) setBranding(b);
      })
      .catch((e) => {
        if (active) setBrandError(e.message);
      });
    return () => {
      active = false;
    };
  }, []);
  const [session, setSession] = useState<SessionResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [selected, setSelected] = useState("");
  const [page, setPage] = useState<
    "organisations" | "password" | "face-engine"
  >("organisations");
  const [invitation, setInvitation] = useState<Invitation | null>(null);
  const [inviteToken, setInviteToken] = useState(
    () => new URLSearchParams(location.hash.slice(1)).get("invite") || "",
  );
  const [preview, setPreview] = useState<InvitationPreview | null>(null);
  const [query, setQuery] = useState("");
  const [creating, setCreating] = useState(false);

  async function refresh() {
    const result = await api<SessionResponse>("/auth/me");
    setSession(result);
    setSelected((previous) =>
      result.organisations.some((org) => org.id === previous)
        ? previous
        : result.organisations.find((o) => o.slug === requestedSlug)?.id ||
          result.organisations[0]?.id ||
          "",
    );
  }
  useEffect(() => {
    refresh()
      .catch((err) => {
        if (!(err instanceof ApiError && err.status === 401))
          setError(message(err));
      })
      .finally(() => setLoading(false));
  }, []);
  useEffect(() => {
    const update = () => {
      setPreview(null);
      setError("");
      setInviteToken(
        new URLSearchParams(location.hash.slice(1)).get("invite") || "",
      );
    };
    window.addEventListener("hashchange", update);
    return () => window.removeEventListener("hashchange", update);
  }, []);
  useEffect(() => {
    let active = true;
    if (inviteToken)
      api<InvitationPreview>("/invitations/preview", "POST", {
        token: inviteToken,
      })
        .then((value) => {
          if (active) setPreview(value);
        })
        .catch((err) => {
          if (active) setError(message(err));
        });
    return () => {
      active = false;
    };
  }, [inviteToken]);
  async function act(work: () => Promise<void>) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await work();
    } catch (err) {
      setError(message(err));
      if (err instanceof ApiError && err.status === 401 && session) {
        clearDrafts();
                setSession(null);
        setInvitation(null);
      }
    } finally {
      setBusy(false);
    }
  }
  function dismissInvite() {
    history.replaceState(null, "", location.pathname + location.search);
    setInviteToken("");
    setPreview(null);
    setError("");
  }
  const org = session?.organisations.find((item) => item.id === selected);
  const superadmin = !!session?.user.is_superadmin;
  const platformHome = superadmin && !session?.organisationHost;
  const [workspaceBrand, setWorkspaceBrand] = useState<Branding | null>(null);
  useEffect(() => {
    let active = true;
    const update = () => {
      if (org)
        api<Branding>("/public/branding?slug=" + encodeURIComponent(org.slug))
          .then((b) => {
            if (active) setWorkspaceBrand(b);
          })
          .catch(() => {});
    };
    update();
    window.addEventListener("branding-updated", update);
    return () => {
      active = false;
      window.removeEventListener("branding-updated", update);
    };
  }, [org?.id, org?.name, org?.colour]);
  useEffect(() => {
    if (branding && session?.organisations.some((o) => o.id === branding.id))
      setSelected(branding.id);
  }, [branding]);
  const visibleOrgs =
    session?.organisations.filter((item) =>
      `${item.name} ${item.slug}`.toLowerCase().includes(query.toLowerCase()),
    ) || [];
  const inviteUrl = invitation
    ? `${location.origin}${location.pathname}#invite=${invitation.token}`
    : "";
  const feedback = (
    <>
      {error && (
        <div className="alert error" role="alert">
          {error}
        </div>
      )}
      {notice && (
        <div className="alert success" role="status">
          {notice}
        </div>
      )}
    </>
  );
  const signIn = (
    <DraftForm title="Sign in" draftKey={"security"} draftEnabled={false}
      onSubmit={(event) => {
        const body = values(event);
        void act(async () => {
          await api("/auth/login", "POST", body);
          await refresh();
        });
      }}
    >
      <label>
        Email address
        <input
          name="email"
          type="email"
          autoComplete="username"
          required
          defaultValue={preview?.email}
        />
      </label>
      <PasswordField />
      <button disabled={busy}>{busy ? "Signing in…" : "Sign in"}</button>
      <p className="muted">
        Access is by invitation. Contact your platform administrator if you need
        access or help signing in.
      </p>
    </DraftForm>
  );

  if (loading)
    return (
      <main className="loading" role="status">
        Loading Tech4Learn…
      </main>
    );
  if (inviteToken)
    return (
      <main className="entry">
        <a className="brand" href="/">
          TECH4LEARN
        </a>
        <section className="entry-card">
          <p className="eyebrow">Organisation invitation</p>
          <h1>
            {preview ? `Join ${preview.organisationName}` : "Your invitation"}
          </h1>
          {feedback}
          {preview && (
            <>
              <p>
                {preview.roleName} access for <strong>{preview.email}</strong>.{" "}
                {preview.scopeType === "organisation"
                  ? "Whole organisation."
                  : `Assigned ${preview.scopeType} only.`}
              </p>
              {preview.existingAccount && !session ? (
                <>
                  <p>
                    Sign in to your existing account to accept this invitation.
                  </p>
                  {signIn}
                </>
              ) : (
                <DraftForm title="Accept invitation" draftKey={"signin"} draftEnabled={false}
                  onSubmit={(event) => {
                    const body = values(event);
                    void act(async () => {
                      await api("/invitations/accept", "POST", {
                        ...body,
                        token: inviteToken,
                      });
                      dismissInvite();
                      if (session) await refresh();
                      else
                        setNotice(
                          "Account created. Sign in with your email and new password.",
                        );
                    });
                  }}
                >
                  {!preview.existingAccount && (
                    <>
                      <label>
                        Your name
                        <input
                          name="name"
                          autoComplete="name"
                          maxLength={120}
                          required
                        />
                      </label>
                      <PasswordField isNew />
                    </>
                  )}
                  {preview.existingAccount && (
                    <p>Signed in as {session?.user.email}.</p>
                  )}
                  <button disabled={busy}>
                    {busy ? "Saving…" : "Accept invitation"}
                  </button>
                </DraftForm>
              )}
            </>
          )}
          <button className="text-button" onClick={dismissInvite}>
            Back to administration
          </button>
        </section>
      </main>
    );
  if (!session)
    return (
      <main
        className={`entry template-${branding?.template || "community"}`}
        style={
          { "--org-colour": branding?.colour || "#175d50" } as CSSProperties
        }
      >
        <a className="brand" href={location.pathname + location.search}>
          {branding?.logo && (
            <img className="organisation-logo" src={branding.logo} alt="" />
          )}
          {branding?.name || "TECH4LEARN"}
        </a>
        {brandError && (
          <p role="alert" className="error">
            {brandError}
          </p>
        )}
        <div className="entry-layout">
          <div className="welcome">
            <p className="eyebrow">More time for learning</p>
            <h1>
              {branding?.name || (
                <>
                  Your organisation.
                  <br />
                  Your way of working.
                </>
              )}
            </h1>
            <p>
              {branding?.welcome ||
                "One place to organise your education programmes and give your team the access they need."}
            </p>
            <div className="entry-note">
              Built for coaching, NGOs and community learning.
            </div>
          </div>
          <section className="entry-card">
            <h2>Welcome back</h2>
            <p className="muted">Sign in to your administration workspace.</p>
            {feedback}
            {signIn}
          </section>
        </div>
      </main>
    );

  return (
    <DraftScope user={session.user.id} org={selected||"platform"}><div
      className={`workspace template-${workspaceBrand?.template || "community"}`}
      style={{ "--org-colour": org?.colour || "#175d50" } as CSSProperties}
    >
      <aside>
        <a className="brand" href="/">
          {workspaceBrand?.logo && (
            <img
              className="organisation-logo"
              src={workspaceBrand.logo}
              alt=""
            />
          )}
          {superadmin ? "TECH4LEARN" : org?.name || "TECH4LEARN"}
        </a>
        <span className="role-badge">
          {superadmin
            ? "Platform administration"
            : "Organisation administration"}
        </span>
        <GroupedMenu label="Administration" active={page} groups={[
          {id:"platform-workspaces",label:superadmin?"Platform":"Workspace",icon:"▦",items:[{id:"organisations",label:superadmin?"Organisations":"My organisation"}]},
          {id:"platform-settings",label:"System & account",icon:"⚙",items:[...(superadmin?[{id:"face-engine",label:"Face engine"}]:[]),{id:"password",label:"Account security"}]},
        ]} onSelect={(next)=>{setPage(next as typeof page);setError("");}} />
        <button
          className="mobile-org-menu secondary"
          aria-expanded={organisationMenuOpen}
          aria-controls="organisation-menu-slot"
          onClick={() => setOrganisationMenuOpen(!organisationMenuOpen)}
        >
          Organisation menu
        </button>
        <div
          id="organisation-menu-slot"
          className={organisationMenuOpen ? "menu-open" : "menu-closed"}
          onClickCapture={(e) => {
            if ((e.target as HTMLElement).closest("button"))
              setOrganisationMenuOpen(false);
          }}
        />
        <div className="account">
          <strong>{session.user.name}</strong>
          <small>{session.user.email}</small>
          <button
            className="text-button"
            disabled={busy}
            onClick={() =>
              void act(async () => {
                await api("/auth/logout", "POST", {});
                clearDrafts();
                setSession(null);
                setInvitation(null);
              })
            }
          >
            Sign out
          </button>
        </div>
      </aside>
      <main className="content">
        <header>
          <div>
            <p className="eyebrow">
              {superadmin
                ? "Your learning network"
                : org?.name || "Your workspace"}
            </p>
            <h1>
              {page === "face-engine"
                ? "Face engine"
                : page === "password"
                  ? "Account security"
                  : superadmin
                    ? "Organisations"
                    : "Organisation workspace"}
            </h1>
          </div>
          {superadmin && page === "organisations" && (
            <button
              onClick={() => {
                setCreating(!creating);
                setError("");
              }}
            >
              {creating ? "Close form" : "+ Add organisation"}
            </button>
          )}
        </header>
        {feedback}
        {page === "face-engine" && superadmin ? (
          <FaceEngine />
        ) : page === "password" ? (
          <section className="panel narrow">
            <h2>Change your password</h2>
            <p className="muted">
              Changing your password signs out all your sessions.
            </p>
            <DraftForm title="Account security" draftKey={"account-security"} draftEnabled={false}
              onSubmit={(event) => {
                const body = values(event);
                void act(async () => {
                  await api("/auth/password", "POST", body);
                  clearDrafts();
                setSession(null);
                  setInvitation(null);
                  setNotice("Password updated. Please sign in again.");
                });
              }}
            >
              <PasswordField name="currentPassword" label="Current password" />
              <PasswordField label="New password" isNew />
              <button disabled={busy}>
                {busy ? "Saving…" : "Change password"}
              </button>
            </DraftForm>
          </section>
        ) : (
          <>
            {invitation && (
              <section className="panel invite-result" role="status">
                <h2>Invitation ready</h2>
                <p>
                  Share this link privately with{" "}
                  <strong>{invitation.email}</strong>. It expires{" "}
                  {new Date(invitation.expiresAt).toLocaleString()} and can be
                  used once. No email has been sent.
                </p>
                <label>
                  Invitation link
                  <input
                    readOnly
                    value={inviteUrl}
                    onFocus={(event) => event.target.select()}
                  />
                </label>
                <div className="actions">
                  <button
                    className="secondary"
                    onClick={() =>
                      void act(async () => {
                        await navigator.clipboard.writeText(inviteUrl);
                        setNotice("Invitation link copied.");
                      })
                    }
                  >
                    Copy link
                  </button>
                  <button
                    className="text-button"
                    onClick={() => setInvitation(null)}
                  >
                    Dismiss
                  </button>
                </div>
              </section>
            )}
            {creating && platformHome && (
              <section className="panel">
                <h2>Create an organisation</h2>
                <p className="muted">
                  Give the organisation its own workspace and invite its first
                  administrator.
                </p>
                <DraftForm title="Create organisation" draftKey={"new-organisation"}
                  className="form-grid"
                  onSubmit={(event) => {
                    const body = values(event);
                    void act(async () => {
                      const result = await api<{
                        organisation: Organisation;
                        invitation: Invitation;
                      }>("/organisations", "POST", body);
                      setInvitation(result.invitation);
                      await refresh();
                      setSelected(result.organisation.id);
                      setCreating(false);
                      setNotice(
                        "Organisation created. Share the invitation below.",
                      );
                    });
                  }}
                >
                  <label>
                    Organisation name
                    <input
                      name="name"
                      placeholder="e.g. Community Learning Trust"
                      maxLength={120}
                      required
                    />
                  </label>
                  <label>
                    Organisation address
                    <input
                      name="slug"
                      placeholder="community-learning"
                      pattern="[a-z0-9]+(-[a-z0-9]+)*"
                      maxLength={60}
                      required
                    />
                    <small>
                      Used in your sign-in link: /?org=your-address. Use
                      lowercase letters and hyphens.
                    </small>
                  </label>
                  <label>
                    Organisation type
                    <OrganisationTypeSelect
                      name="kind"
                      required
                      defaultValue=""
                    />
                    <small>
                      Choose the closest fit. Modules and permissions are
                      configured separately.
                    </small>
                  </label>
                  <label>
                    Administrator email
                    <input name="adminEmail" type="email" required />
                  </label>
                  <div className="form-submit">
                    <button disabled={busy}>
                      {busy ? "Creating…" : "Create & prepare invitation"}
                    </button>
                  </div>
                </DraftForm>
              </section>
            )}
            {!session.organisations.length ? (
              <section className="panel empty">
                <h2>
                  {superadmin
                    ? "Start with your first organisation"
                    : "No organisation access yet"}
                </h2>
                <p>
                  {superadmin
                    ? "Add an NGO, coaching centre or education programme to begin."
                    : "Accept an organisation invitation or contact your platform administrator."}
                </p>
              </section>
            ) : (
              <div className="org-layout">
                <details className="panel org-list">
                  <summary>
                    Switch organisation ·{" "}
                    {org?.name || "Choose an organisation"}
                  </summary>
                  <h2>
                    {superadmin
                      ? "Organisation directory"
                      : "Your organisations"}
                  </h2>
                  <DirectoryTable title="Organisation directory" columns={["Organisation","Address","Brand colour","Actions"]}>
                    {session.organisations.map(item=><tr key={item.id}><th scope="row">{item.name}</th><td>{item.slug}</td><td>{item.colour}</td><td><button type="button" className="secondary" onClick={()=>{setSelected(item.id);setError("");setNotice("");}}>Open organisation</button></td></tr>)}
                  </DirectoryTable>
                </details>
                {org && (
                  <div key={org.id}>
                    <OrganisationWorkspace
                      onNavigate={() => setOrganisationMenuOpen(false)}
                      organisation={org}
                      userId={session.user.id}
                      superadmin={superadmin}
                      onInvitation={setInvitation}
                      onProfile={(saved) =>
                        setSession((current) =>
                          current
                            ? {
                                ...current,
                                organisations: current.organisations.map(
                                  (item) =>
                                    item.id === saved.id ? saved : item,
                                ),
                              }
                            : null,
                        )
                      }
                    />
                    <section className="panel next">
                      <p className="eyebrow">Coming next</p>
                      <h2>Tools for your programme</h2>
                      <p>
                        Photo attendance is available when enabled by your
                        platform administrator. Interactive learning assessments
                        and ExamElite integration are planned.
                      </p>
                    </section>
                  </div>
                )}
              </div>
            )}
          </>
        )}
        <footer>Tech4Learn · Organisation administration</footer>
      </main>
    </div></DraftScope>
  );
}
createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
