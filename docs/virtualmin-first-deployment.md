# First deployment on Virtualmin

Historical first-scaffold deployment guide (9f35c83). The user deploys from the root terminal; assistant access stays read-only. This installation is now complete. For the identity release use [identity deployment](identity-deployment.md); do not rerun the first-install script.

## Confirmed inventory

Ubuntu 22.04.5; system Node 20.20.2; npm 10.8.2; PostgreSQL client 14.24; Redis executable 6.0.16; Apache and systemd installed. Database/server readiness has not been established. No database is needed by this release.

## 1. Clone the approved release

Run as root. If the destination already exists, stop and inspect it instead of overwriting it.

```bash
runuser -u tech4learn -- git clone https://github.com/menhadi/tech4learn.git /home/tech4learn/tech4learn-app
```

Check out the exact release commit supplied in the release message before executing the installer:

```bash
runuser -u tech4learn -- git -C /home/tech4learn/tech4learn-app checkout --detach APPROVED_COMMIT
bash /home/tech4learn/tech4learn-app/deploy/virtualmin/prepare-first-deploy.sh
```

The installer verifies the official Node 24.19.0 archive checksum and installs under /opt/tech4learn only. It builds as the domain user, creates a dedicated systemd service on 127.0.0.1:3101, and verifies local health. System Node and Apache are not changed. Failure stops the script. An existing service/environment or occupied port requires review instead of overwrite. Native/mobile dependencies are excluded from the server workspace install.

Checksums were retrieved from https://nodejs.org/dist/v24.19.0/SHASUMS256.txt . Retest installer execution on the server; local validation covers shell syntax and application behaviour, not actual systemd/Virtualmin execution.

## 2. Connect the domain after local health passes

In Virtualmin select tech4learn.com. Record its existing website/proxy settings and take a domain configuration backup through Virtualmin before changing routing. Configure the website proxy destination to http://127.0.0.1:3101/ for this domain. Virtualmin exposes a supported CLI alternative:

```bash
virtualmin create-proxy --domain tech4learn.com --path / --url http://127.0.0.1:3101/
apache2ctl configtest
```

Do not apply this to other domains. The command changes the public route for tech4learn.com; existing public_html files remain on disk. Virtualmin may apply/reload changes immediately, so use its managed command and recorded previous settings. Stop if it reports failure. Verify both the HTTP and HTTPS domain behaviour and preserve the existing certificate/renewal configuration. Do not bypass certificate errors or open port 3101 publicly.

This shell temporarily occupies the main domain. The one/two-page public website and final admin routing can replace that arrangement later without separate subdomains being required now.

## 3. Verify

```bash
curl --fail --show-error https://tech4learn.com/api/v1/health
systemctl status tech4learn --no-pager
```

Open https://tech4learn.com and click Check API connection. Expect the scaffold and a successful API connection, not an operational admin login. If HTTPS has not been configured, configure the domain certificate through Virtualmin before accepting a successful release. Share errors/output without secrets.

## Updates and rollback

Do not rerun the first-install script. For this scaffold's next update: record the previous commit, fetch and check out the supplied commit as tech4learn, install the same targeted workspaces, build with VITE_API_URL=/api/v1, run API tests, then restart only tech4learn.service and verify health. A running service shares the checkout in this initial setup; use a maintenance window, and introduce separate release directories before serving operational workloads.

If setup fails before domain routing, the existing website remains routed as before. If the domain proxy fails after switching, restore the previous website/proxy settings from the recorded configuration/Virtualmin backup. Where the previous mode was ordinary local-file hosting with no proxy, the documented undo is:

```bash
virtualmin delete-proxy --domain tech4learn.com --path /
apache2ctl configtest
```

Do not delete the proxy if the previous site already used a proxy; restore its exact prior target instead. Restore the prior commit/build to roll back application code. No database migrations existed in the initial scaffold release.

The installed Virtualmin rejected modify-web --proxy. The dedicated create-proxy command was confirmed working on this server. References: https://www.virtualmin.com/docs/development/api-programs/create-proxy/ and https://www.virtualmin.com/docs/development/api-programs/delete-proxy/ .

HTTPS uses a Cloudflare Origin certificate installed only for tech4learn.com. Keep the DNS record proxied and SSL Full (strict). Webmin's global ACME client and system Python packages were not changed; do not use the broken shared Certbot invocation for this domain. Track the Origin certificate expiration separately.
