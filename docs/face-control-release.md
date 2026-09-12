# Superadmin face-engine controls

Implemented locally; deployment and real-server control checks remain user-run. The supplied server screenshot shows all five CompreFace services running and HTTP 200. This establishes startup, not API-key integration or recognition accuracy.

## User flow

Superadmin → Face engine → Refresh status. The page reads host CPU count, RAM, available RAM and load averages, plus container limits and individual service states. Suggested profiles are 2 CPU/4 GiB, 4 CPU/8 GiB and 6 CPU/12 GiB; profiles exceeding half the host are disabled. Custom limits use half-core CPU steps and whole GiB RAM. Server-side checks reserve half the host and require two GiB of currently available RAM headroom when raising the limit. These are conservative guardrails, not measured workload capacity.

After upgrading the same server, refresh, select a profile or custom limits, then review and confirm. Applying limits stops this container and uses `docker update`; it does not recreate it, change its image, delete its volume or modify other applications. Start / resume afterwards, wait for all services to show RUNNING, and select Start / resume again to release the queue. Restart also leaves the queue paused during warm-up. Refresh alone never resumes work.

Limits are Docker resource ceilings. Model worker count, Java heaps, recognition thresholds and the single attendance job concurrency remain unchanged. This release does not edit API keys, move the engine to another host, upgrade its image, or guarantee that larger resource ceilings improve throughput. Those changes require their own validated rollout. A private remote controller is future work.

## Permission and process boundary

Authenticated `GET` and `POST /api/v1/platform/face-engine` require current stored superadmin status. Browser writes retain exact-origin/JSON/custom-header protection. The UI sends only an allowed action and numeric limits; the controller rejects all additional arguments. It cannot accept command text, target containers, volumes, images, paths or ports.

The API keeps its existing unprivileged systemd service and `NoNewPrivileges=true`. It does not join the Docker group, receive the Docker socket, or gain sudo. A separate root-owned Python controller listens only on `/run/tech4learn-face-control/control.sock`, owned root:tech4learn, mode 0660, inside a 0750 directory. The controller serializes requests, bounds request size and execution time, invokes `/usr/bin/docker` without a shell and targets only the verified `tech4learn-face` container ID. Its root-owned installed file cannot be edited by the app. Its privileged Docker access remains a security boundary requiring review when changed.

Before acting it validates the pinned CompreFace image reference, loopback-only port 8001, dedicated database volume and non-privileged/non-host-network container configuration. It exposes only selected operational fields, never inspect output, environment variables, keys, photos or arbitrary logs. The helper has no public TCP listener.

## Queue and persistence

Migration 10 adds a durable `paused` flag to the existing worker singleton. Controls lock the same row the worker uses to claim jobs. A live worker lease blocks controls. The lock is held across the bounded helper request, preventing new job claims; queued work is retained. Stop, incomplete startup or control failure leave the queue paused. Start / resume releases it only if the helper reports all five services RUNNING. API keys and real inference still need separate validation. A standalone reference check in progress can fail during maintenance and must be retried.

Requested actions, numeric limits, completion and failure are recorded in audit_events. Docker actions and database commits cannot form a single atomic transaction. If connectivity or a database commit fails, refresh actual state before retrying. Start is idempotent when the container already runs. Expired attendance processing leases follow the existing interrupted-job recovery path.

## User-run deployment

Pull the checked commit into `/home/tech4learn/tech4learn-app`, then run `bash deploy/virtualmin/update-face-control.sh` from that checkout as root. The release script backs up only the Tech4Learn database, builds the app, applies migrations, installs the restricted helper and unit, restarts the Tech4Learn app and checks its health. It does not restart Docker, Apache, PostgreSQL or ExamElite, and installing the helper does not stop the face container.

The helper installation is a one-time server setup; subsequent capacity changes use the superadmin page. The install script can also be rerun to update the helper from a later reviewed commit. It installs `/usr/local/lib/tech4learn-face-control/controller.py` and `/etc/systemd/system/tech4learn-face-control.service` as root-owned files.

Verify on the server with a quiet queue: Refresh → inspect limits → Stop → confirm queue paused → Start → wait → Refresh → Start / resume. Check the existing application health after the test. To disable management, `systemctl disable --now tech4learn-face-control`; this does not stop or delete the engine. Operator fallback remains `docker stop tech4learn-face` / `docker start tech4learn-face`. No volume deletion is part of this release.

## Validation

Automated HTTP/database tests cover unauthenticated access, hostile origins, stored-role revocation, operation validation, active-worker exclusion, failure pause and warm-up/resume. Python tests use synthetic Docker metadata to check resource bounds, wrong-target rejection, fixed container commands, volume preservation and idempotent start. These tests do not exercise a real Docker daemon or Linux systemd from the Windows development environment. Live installation and operational confirmation are still required.

Reference: [Docker container update](https://docs.docker.com/reference/cli/docker/container/update/).
