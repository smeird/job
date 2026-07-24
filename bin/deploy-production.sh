#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
APP_DIR="$(cd -- "${SCRIPT_DIR}/.." && pwd -P)"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
DEPLOY_MODE="${DEPLOY_MODE:-cutover}"
ENV_SOURCE="${ENV_SOURCE:-${APP_DIR}/.env}"
ENV_TARGET="${ENV_TARGET:-/etc/job/job.env}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/job-tune}"
APACHE_VHOST="${APACHE_VHOST:-}"
PHP_WORKER_SERVICE="${PHP_WORKER_SERVICE:-}"
SKIP_TESTS="${SKIP_TESTS:-0}"
SKIP_DB_DUMP="${SKIP_DB_DUMP:-0}"
CURRENT_STEP="initialisation"
TEMP_DIR=""
APACHE_PROXY_PATH="/etc/job/job-typescript-proxy.conf"
APACHE_PROXY_BACKUP=""
APACHE_VHOST_BACKUP=""
APACHE_PROXY_EXISTED=0
APACHE_VHOST_CHANGED=0

# Print a timestamped deployment message that is easy to locate in terminal logs.
log() {
  printf '[%s] %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*"
}

# Stop deployment with a concise reason while preserving the failing exit status.
fail() {
  printf 'Deployment stopped during %s: %s\n' "${CURRENT_STEP}" "$*" >&2
  exit 1
}

# Report unexpected command failures with the step that was active at the time.
report_error() {
  local status=$?
  printf 'Deployment failed during %s (exit %s).\n' "${CURRENT_STEP}" "${status}" >&2
  exit "${status}"
}

# Remove only the private temporary directory created by this deployment run.
cleanup() {
  if [[ -n "${TEMP_DIR}" && -d "${TEMP_DIR}" ]]; then
    rm -rf -- "${TEMP_DIR}"
  fi
}

trap report_error ERR
trap cleanup EXIT

# Explain the supported deployment modes and intentional emergency overrides.
usage() {
  cat <<'EOF'
Usage: ./bin/deploy-production.sh [--cutover|--phased] [--apache-vhost PATH]

Runs a complete production deployment from git pull through health checks.

Options:
  --cutover              Proxy the complete public site to TypeScript (default).
  --phased               Deploy Node but proxy only /_next and /__ts health routes.
  --apache-vhost PATH    Existing HTTPS vhost containing ServerName job.smeird.com.
  --skip-tests           Skip lint, typecheck, and unit tests; the build still runs.
  --skip-db-dump         Skip mysqldump; a database characterisation is still saved.
  --help                 Show this help.

Environment overrides:
  DEPLOY_BRANCH, ENV_SOURCE, ENV_TARGET, BACKUP_DIR, APACHE_VHOST,
  PHP_WORKER_SERVICE, SKIP_TESTS=1, SKIP_DB_DUMP=1.

Run this as the normal deployment user, not root. The script invokes sudo only for
systemd, Apache, protected environment files, and the backup directory.
EOF
}

# Parse command-line switches without accepting ambiguous positional arguments.
parse_arguments() {
  while (($# > 0)); do
    case "$1" in
      --cutover)
        DEPLOY_MODE="cutover"
        ;;
      --phased)
        DEPLOY_MODE="phased"
        ;;
      --apache-vhost)
        (($# >= 2)) || fail "--apache-vhost requires a path."
        APACHE_VHOST="$2"
        shift
        ;;
      --skip-tests)
        SKIP_TESTS=1
        ;;
      --skip-db-dump)
        SKIP_DB_DUMP=1
        ;;
      --help|-h)
        usage
        exit 0
        ;;
      *)
        usage >&2
        fail "Unknown argument: $1"
        ;;
    esac
    shift
  done
}

# Confirm that a required executable is available before changing production state.
require_command() {
  local command_name="$1"
  command -v "${command_name}" >/dev/null 2>&1 || fail "Required command is missing: ${command_name}"
}

# Wait briefly for an HTTP endpoint and return failure without hiding curl diagnostics.
wait_for_url() {
  local url="$1"
  local attempts="${2:-30}"
  local attempt
  for ((attempt = 1; attempt <= attempts; attempt += 1)); do
    if curl --fail --silent --show-error --max-time 10 "${url}" >/dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  return 1
}

# Validate required production variables without printing credentials to the terminal.
validate_environment() {
  node --env-file="${ENV_SOURCE}" <<'NODE'
const required = ["APP_KEY", "APP_URL", "DB_DATABASE", "DB_USERNAME", "OPENAI_API_KEY"];
const missing = required.filter((name) => (process.env[name] ?? "").trim() === "");
if (missing.length > 0) {
  throw new Error(`Missing production variables: ${missing.join(", ")}`);
}
if (process.env.APP_ENV !== "production") {
  throw new Error("APP_ENV must be production.");
}
if ((process.env.APP_KEY ?? "").length < 32) {
  throw new Error("APP_KEY must contain at least 32 characters.");
}
const url = new URL(process.env.APP_URL);
if (url.protocol !== "https:") {
  throw new Error("APP_URL must use HTTPS in production.");
}
NODE
}

# Write a restrictive temporary MySQL client file for a consistent pre-migration dump.
prepare_mysql_dump_config() {
  local client_file="$1"
  local database_file="$2"
  node --env-file="${ENV_SOURCE}" - "${client_file}" "${database_file}" <<'NODE'
const fs = require("node:fs");
const clientFile = process.argv[2];
const databaseFile = process.argv[3];
const dsn = {};
if ((process.env.DB_DSN ?? "").startsWith("mysql:")) {
  for (const entry of process.env.DB_DSN.slice(6).split(";")) {
    const separator = entry.indexOf("=");
    if (separator > 0) dsn[entry.slice(0, separator)] = entry.slice(separator + 1);
  }
}
const quote = (value) => `"${String(value).replaceAll("\\", "\\\\").replaceAll('"', '\\"').replaceAll("\n", "\\n")}"`;
const socket = dsn.unix_socket || process.env.DB_SOCKET || "";
const lines = [
  "[client]",
  `user=${quote(process.env.DB_USERNAME ?? "job")}`,
  `password=${quote(process.env.DB_PASSWORD ?? "")}`,
];
if (socket) {
  lines.push(`socket=${quote(socket)}`);
} else {
  lines.push(`host=${quote(dsn.host || process.env.DB_HOST || "127.0.0.1")}`);
  lines.push(`port=${quote(dsn.port || process.env.DB_PORT || "3306")}`);
}
fs.writeFileSync(clientFile, `${lines.join("\n")}\n`, { mode: 0o600 });
fs.writeFileSync(databaseFile, dsn.dbname || process.env.DB_DATABASE || "job", { mode: 0o600 });
NODE
}

# Render a supplied systemd unit with the actual checkout and Node binary paths.
render_systemd_unit() {
  local source_file="$1"
  local output_file="$2"
  local node_binary="$3"
  node - "${source_file}" "${output_file}" "${APP_DIR}" "${node_binary}" <<'NODE'
const fs = require("node:fs");
const [source, output, appDirectory, nodeBinary] = process.argv.slice(2);
const rendered = fs.readFileSync(source, "utf8")
  .replaceAll("/srv/job/current", appDirectory)
  .replaceAll("/usr/bin/node", nodeBinary);
fs.writeFileSync(output, rendered);
NODE
}

# Find the single enabled Apache vhost whose ServerName matches APP_URL.
discover_apache_vhost() {
  local host="$1"
  local file
  local -a matches=()
  shopt -s nullglob
  for file in /etc/apache2/sites-enabled/*.conf; do
    if sudo grep -Eq '<VirtualHost[[:space:]][^>]*:443>' "${file}" \
      && sudo awk -v expected="${host}" '$1 == "ServerName" && ($2 == expected || $2 == expected ":443") { found = 1 } END { exit found ? 0 : 1 }' "${file}"; then
      matches+=("$(readlink -f -- "${file}")")
    fi
  done
  shopt -u nullglob
  if ((${#matches[@]} != 1)); then
    fail "Could not identify one Apache vhost for ${host}; pass --apache-vhost PATH."
  fi
  APACHE_VHOST="${matches[0]}"
}

# Add the stable proxy include to a simple one-vhost Apache site file.
ensure_apache_include() {
  local include_line="IncludeOptional ${APACHE_PROXY_PATH}"
  local closing_count
  closing_count="$(sudo grep -c '</VirtualHost>' "${APACHE_VHOST}" || true)"
  [[ "${closing_count}" == "1" ]] || fail "Apache vhost must contain exactly one </VirtualHost>: ${APACHE_VHOST}"

  APACHE_VHOST_BACKUP="${BACKUP_DIR}/$(basename "${APACHE_VHOST}").${DEPLOY_TIMESTAMP}.bak"
  sudo cp --preserve=mode,ownership,timestamps -- "${APACHE_VHOST}" "${APACHE_VHOST_BACKUP}"
  if sudo grep -Fq "${include_line}" "${APACHE_VHOST}"; then
    return
  fi

  awk -v include_line="    ${include_line}" '
    /<\/VirtualHost>/ && !inserted { print include_line; inserted = 1 }
    { print }
    END { if (!inserted) exit 2 }
  ' "${APACHE_VHOST}" > "${TEMP_DIR}/apache-vhost.conf"
  sudo install -m 0644 -o root -g root "${TEMP_DIR}/apache-vhost.conf" "${APACHE_VHOST}"
  APACHE_VHOST_CHANGED=1
}

# Restore the prior Apache proxy and vhost files when validation or public health fails.
restore_apache_configuration() {
  log "Restoring the previous Apache configuration"
  if [[ "${APACHE_VHOST_CHANGED}" == "1" && -f "${APACHE_VHOST_BACKUP}" ]]; then
    sudo cp --preserve=mode,ownership,timestamps -- "${APACHE_VHOST_BACKUP}" "${APACHE_VHOST}"
  fi
  if [[ "${APACHE_PROXY_EXISTED}" == "1" && -f "${APACHE_PROXY_BACKUP}" ]]; then
    sudo cp --preserve=mode,ownership,timestamps -- "${APACHE_PROXY_BACKUP}" "${APACHE_PROXY_PATH}"
  elif [[ "${APACHE_PROXY_EXISTED}" == "0" ]]; then
    sudo rm -f -- "${APACHE_PROXY_PATH}"
  fi
  sudo apache2ctl configtest >/dev/null
  sudo systemctl reload apache2
}

# Locate a legacy job service whose command still runs the PHP queue worker.
discover_php_worker_service() {
  local unit
  local -a matches=()
  if [[ -n "${PHP_WORKER_SERVICE}" ]]; then
    systemctl cat "${PHP_WORKER_SERVICE}" >/dev/null 2>&1 || fail "PHP worker unit was not found: ${PHP_WORKER_SERVICE}"
    return
  fi
  while IFS= read -r unit; do
    [[ -n "${unit}" ]] || continue
    if systemctl cat "${unit}" 2>/dev/null | grep -Fq '/bin/worker.php'; then
      matches+=("${unit}")
    fi
  done < <(systemctl list-unit-files 'job*.service' --type=service --no-legend 2>/dev/null | awk '{print $1}')
  if ((${#matches[@]} > 1)); then
    fail "Multiple PHP workers were found; set PHP_WORKER_SERVICE explicitly."
  fi
  PHP_WORKER_SERVICE="${matches[0]:-}"
}

parse_arguments "$@"

if [[ "${EUID}" == "0" ]]; then
  fail "Run this script as the normal deployment user, not with sudo."
fi
[[ "${DEPLOY_MODE}" == "cutover" || "${DEPLOY_MODE}" == "phased" ]] || fail "DEPLOY_MODE must be cutover or phased."
[[ -f "${ENV_SOURCE}" ]] || fail "Environment file not found: ${ENV_SOURCE}"

for command_name in git node npm curl sudo flock gzip readlink install awk grep systemctl apache2ctl a2enmod; do
  require_command "${command_name}"
done
if [[ "${SKIP_DB_DUMP}" != "1" ]]; then
  require_command mysqldump
fi

CURRENT_STEP="acquiring the deployment lock"
exec 9>/tmp/job-tune-production-deploy.lock
flock -n 9 || fail "Another deployment is already running."
sudo -v
TEMP_DIR="$(mktemp -d -t job-tune-deploy.XXXXXX)"

cd -- "${APP_DIR}"
CURRENT_STEP="checking the Git checkout"
[[ "$(git branch --show-current)" == "${DEPLOY_BRANCH}" ]] || fail "Checkout must be on ${DEPLOY_BRANCH}."
git diff --quiet && git diff --cached --quiet || fail "Tracked files have local changes; refusing to pull."

CURRENT_STEP="pulling ${DEPLOY_BRANCH}"
log "Pulling origin/${DEPLOY_BRANCH}"
git pull --ff-only origin "${DEPLOY_BRANCH}"
DEPLOY_COMMIT="$(git rev-parse HEAD)"
DEPLOY_TIMESTAMP="$(date -u +'%Y%m%dT%H%M%SZ')"

CURRENT_STEP="checking Node and npm"
NODE_MAJOR="$(node -p 'Number(process.versions.node.split(".")[0])')"
NPM_MAJOR="$(npm --version | cut -d. -f1)"
((NODE_MAJOR >= 24)) || fail "Node.js 24 or newer is required; found $(node --version)."
((NPM_MAJOR >= 11)) || fail "npm 11 or newer is required; found $(npm --version)."
NODE_BINARY="$(command -v node)"
sudo -u www-data test -x "${NODE_BINARY}" || fail "www-data cannot execute ${NODE_BINARY}; install Node 24 system-wide."

CURRENT_STEP="validating production configuration"
validate_environment
APP_URL="$(node --env-file="${ENV_SOURCE}" -p 'process.env.APP_URL.replace(/\/$/, "")')"
APP_HOST="$(node --env-file="${ENV_SOURCE}" -p 'new URL(process.env.APP_URL).hostname')"

CURRENT_STEP="installing locked dependencies"
log "Installing dependencies"
npm ci

if [[ "${SKIP_TESTS}" == "1" ]]; then
  CURRENT_STEP="building the TypeScript applications"
  log "Skipping tests by explicit request; building production applications"
  npm run build
else
  CURRENT_STEP="testing and building the TypeScript applications"
  log "Running lint, type checks, unit tests, and production builds"
  npm run test:all
fi

CURRENT_STEP="auditing production dependencies"
npm run audit:dependencies

CURRENT_STEP="creating the backup directory"
sudo install -d -m 0750 -o "$(id -un)" -g "$(id -gn)" "${BACKUP_DIR}"

if [[ "${SKIP_DB_DUMP}" != "1" ]]; then
  CURRENT_STEP="backing up MySQL"
  log "Creating a consistent MySQL backup"
  prepare_mysql_dump_config "${TEMP_DIR}/mysql-client.cnf" "${TEMP_DIR}/database-name"
  DATABASE_NAME="$(<"${TEMP_DIR}/database-name")"
  mysqldump --defaults-extra-file="${TEMP_DIR}/mysql-client.cnf" \
    --single-transaction --quick --triggers --no-tablespaces --set-gtid-purged=OFF \
    "${DATABASE_NAME}" | gzip -9 > "${TEMP_DIR}/database.sql.gz"
  install -m 0600 "${TEMP_DIR}/database.sql.gz" "${BACKUP_DIR}/database-${DEPLOY_TIMESTAMP}-${DEPLOY_COMMIT:0:12}.sql.gz"
fi

CURRENT_STEP="capturing the pre-migration database state"
log "Capturing schema, row counts, and document checksums"
npm --silent run db:characterize > "${TEMP_DIR}/characterization.json"
install -m 0600 "${TEMP_DIR}/characterization.json" "${BACKUP_DIR}/characterization-${DEPLOY_TIMESTAMP}-${DEPLOY_COMMIT:0:12}.json"

CURRENT_STEP="applying database migrations"
npm --silent run db:migrate
npm --silent run db:verify

CURRENT_STEP="installing the production environment"
sudo install -d -m 0750 -o root -g www-data "$(dirname "${ENV_TARGET}")"
sudo install -m 0640 -o root -g www-data "${ENV_SOURCE}" "${ENV_TARGET}"
sudo install -d -m 0750 -o www-data -g www-data /var/log/job

CURRENT_STEP="installing systemd services"
render_systemd_unit "${APP_DIR}/deploy/systemd/job-web.service" "${TEMP_DIR}/job-web.service" "${NODE_BINARY}"
render_systemd_unit "${APP_DIR}/deploy/systemd/job-worker.service" "${TEMP_DIR}/job-typescript-worker.service" "${NODE_BINARY}"
render_systemd_unit "${APP_DIR}/deploy/systemd/job-retention.service" "${TEMP_DIR}/job-retention.service" "${NODE_BINARY}"
cp "${APP_DIR}/deploy/systemd/job-retention.timer" "${TEMP_DIR}/job-retention.timer"
sudo install -m 0644 -o root -g root "${TEMP_DIR}/job-web.service" /etc/systemd/system/job-web.service
sudo install -m 0644 -o root -g root "${TEMP_DIR}/job-typescript-worker.service" /etc/systemd/system/job-typescript-worker.service
sudo install -m 0644 -o root -g root "${TEMP_DIR}/job-retention.service" /etc/systemd/system/job-retention.service
sudo install -m 0644 -o root -g root "${TEMP_DIR}/job-retention.timer" /etc/systemd/system/job-retention.timer
sudo systemctl daemon-reload
sudo systemctl enable job-web.service job-typescript-worker.service job-retention.timer
sudo systemctl restart job-web.service job-typescript-worker.service
sudo systemctl start job-retention.timer

CURRENT_STEP="checking the local TypeScript web service"
if ! wait_for_url "http://127.0.0.1:3000/__ts/healthz"; then
  sudo systemctl status job-web.service job-typescript-worker.service --no-pager || true
  sudo journalctl -u job-web.service -u job-typescript-worker.service -n 100 --no-pager || true
  fail "The local TypeScript health endpoint did not become ready."
fi

CURRENT_STEP="checking runtime queues"
QUEUE_STATUS="$(npm --silent run db:queue-status)"
printf '%s\n' "${QUEUE_STATUS}"
PHP_PENDING="$(node -e 'let input=""; process.stdin.on("data", c => input += c); process.stdin.on("end", () => process.stdout.write(String(JSON.parse(input).php)));' <<<"${QUEUE_STATUS}")"
if [[ "${DEPLOY_MODE}" == "cutover" && "${PHP_PENDING}" != "0" ]]; then
  fail "${PHP_PENDING} PHP job(s) are still pending. Rerun after the PHP queue drains, or use --phased."
fi

CURRENT_STEP="preparing Apache"
require_command apache2ctl
if [[ -z "${APACHE_VHOST}" ]]; then
  discover_apache_vhost "${APP_HOST}"
fi
[[ -f "${APACHE_VHOST}" ]] || fail "Apache vhost not found: ${APACHE_VHOST}"
APACHE_VHOST="$(readlink -f -- "${APACHE_VHOST}")"
sudo grep -Eq '<VirtualHost[[:space:]][^>]*:443>' "${APACHE_VHOST}" \
  || fail "Apache vhost is not an HTTPS :443 site: ${APACHE_VHOST}"
sudo awk -v expected="${APP_HOST}" '$1 == "ServerName" && ($2 == expected || $2 == expected ":443") { found = 1 } END { exit found ? 0 : 1 }' "${APACHE_VHOST}" \
  || fail "Apache vhost does not declare ServerName ${APP_HOST}: ${APACHE_VHOST}"

if [[ -f "${APACHE_PROXY_PATH}" ]]; then
  APACHE_PROXY_EXISTED=1
  APACHE_PROXY_BACKUP="${BACKUP_DIR}/job-typescript-proxy.${DEPLOY_TIMESTAMP}.bak"
  sudo cp --preserve=mode,ownership,timestamps -- "${APACHE_PROXY_PATH}" "${APACHE_PROXY_BACKUP}"
fi
ensure_apache_include
if [[ "${DEPLOY_MODE}" == "cutover" ]]; then
  sudo install -m 0644 -o root -g root "${APP_DIR}/deploy/apache/job-typescript-cutover.conf" "${APACHE_PROXY_PATH}"
else
  sudo install -m 0644 -o root -g root "${APP_DIR}/deploy/apache/job-typescript-phased.conf" "${APACHE_PROXY_PATH}"
fi
sudo a2enmod proxy proxy_http headers rewrite >/dev/null
if ! sudo apache2ctl configtest; then
  restore_apache_configuration
  fail "Apache rejected the new configuration."
fi
sudo systemctl reload apache2

CURRENT_STEP="checking the public site"
PUBLIC_HEALTH_URL="${APP_URL}/__ts/healthz"
if [[ "${DEPLOY_MODE}" == "cutover" ]]; then
  PUBLIC_HEALTH_URL="${APP_URL}/healthz"
fi
if ! wait_for_url "${PUBLIC_HEALTH_URL}"; then
  restore_apache_configuration
  fail "Public health check failed: ${PUBLIC_HEALTH_URL}"
fi

if [[ "${DEPLOY_MODE}" == "cutover" ]]; then
  CURRENT_STEP="stopping the legacy PHP worker"
  discover_php_worker_service
  if [[ -n "${PHP_WORKER_SERVICE}" ]]; then
    log "Stopping legacy PHP worker ${PHP_WORKER_SERVICE}"
    sudo systemctl disable --now "${PHP_WORKER_SERVICE}"
  else
    log "No active legacy PHP worker unit was found"
  fi
fi

CURRENT_STEP="final service verification"
sudo systemctl is-active --quiet job-web.service
sudo systemctl is-active --quiet job-typescript-worker.service
sudo systemctl is-active --quiet job-retention.timer
log "Deployment complete"
printf 'Commit:       %s\n' "${DEPLOY_COMMIT}"
printf 'Mode:         %s\n' "${DEPLOY_MODE}"
printf 'Application:  %s\n' "${APP_URL}"
printf 'Health check: %s\n' "${PUBLIC_HEALTH_URL}"
printf 'Backups:      %s\n' "${BACKUP_DIR}"
