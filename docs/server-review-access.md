# Read-only server review account

Run `install-review-access.sh` as root alongside `review-access.py`. It installs the dedicated `t4l-review` SSH account using the review workstation's public key. The private key remains on the workstation.

The key has a forced command and disables forwarding and PTY access. A root-owned Python dispatcher accepts only `status`, `files`, and `read <relative source path>`. It excludes environment files, database/media files and paths outside approved source/documentation directories. It does not execute client commands. The narrow sudo rule permits only the root-owned entry point without arguments. No application services are restarted by installation.

Examples: `ssh t4l-review@HOST status`, `ssh t4l-review@HOST 'read apps/api/src/face-verification.ts'`.

Changes continue locally and are deployed separately by the owner. This account cannot upload patches or inspect student photos. Remove `/etc/sudoers.d/t4l-review` and its authorized key to revoke access.
