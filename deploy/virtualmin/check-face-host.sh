#!/usr/bin/env bash
# Read-only capacity snapshot. No package installation, restart or configuration edits.
set -euo pipefail
printf 'Operating system\n'
cat /etc/os-release
printf '\nCPU and virtualisation\n'
lscpu
printf '\nMemory availability\n'
free -h
printf '\nDisk space\n'
df -h / /var/lib
printf '\nCurrent load (snapshot, not peak capacity)\n'
uptime
vmstat 1 5
printf '\nExisting container runtime\n'
if command -v docker >/dev/null 2>&1; then
  docker --version
  docker compose version || true
  docker stats --no-stream --format 'table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}' || true
else
  printf 'Docker is not installed. No changes made.\n'
fi
printf '\nProposed loopback port 8001: existing listeners\n'
ss -ltn 'sport = :8001'
printf '\nTech4Learn health\n'
curl --fail --show-error --max-time 15 http://127.0.0.1:3101/api/v1/health
printf '\nCapacity review required before installing the engine.\n'
