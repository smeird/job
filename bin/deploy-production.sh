#!/usr/bin/env bash
# Builds and migrates a checked-out Job Tune release without reading or printing secret values.
set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
dry_run=false

if [[ "${1:-}" == "--dry-run" ]]; then
  dry_run=true
elif [[ $# -gt 0 ]]; then
  echo "Usage: $0 [--dry-run]" >&2
  exit 64
fi

cd "$project_dir"

# Runs a named project command or prints it when the operator requests a safe preview.
run_project_command() {
  if [[ "$dry_run" == true ]]; then
    printf 'Would run: %s\n' "$*"
    return
  fi
  "$@"
}

if [[ ! -f package-lock.json ]]; then
  echo "Refusing deployment: package-lock.json is required for npm ci." >&2
  exit 1
fi

run_project_command npm ci
run_project_command npm run check
run_project_command npm run build
run_project_command npm run db:migrate

# Restart commands are intentionally opt-in because host service names and privileges differ.
if [[ -n "${JOB_TUNE_RESTART_COMMAND:-}" ]]; then
  if [[ "$dry_run" == true ]]; then
    echo "Would run the configured Job Tune restart command."
  else
    sh -c "$JOB_TUNE_RESTART_COMMAND"
  fi
else
  echo "Build and migration complete. Restart the Node application using this host's supervisor."
fi

if [[ -n "${JOB_TUNE_APACHE_RELOAD_COMMAND:-}" ]]; then
  if [[ "$dry_run" == true ]]; then
    echo "Would run the configured Apache reload command."
  else
    sh -c "$JOB_TUNE_APACHE_RELOAD_COMMAND"
  fi
else
  echo "Reload Apache only if its virtual-host configuration changed."
fi
