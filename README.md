# job.smeird.com Deployment Guide

This application tailors CVs and cover letters via queued background jobs and integrates with OpenAI. The repository ships with a deterministic smoke suite that exercises the core features (database migration, authentication, document upload/extraction, generation workflow, downloads, and data retention purge).

## Prerequisites

* **PHP:** 8.3 or newer with CLI access.
* **Database:** MySQL 8.x (or compatible) configured with UTF-8 (`utf8mb4`).
* **Composer:** Latest stable release to install PHP dependencies.
* **Node.js:** Only required when rebuilding frontend assets (Tailwind CSS).

### Required PHP Extensions

Enable the following extensions in `php.ini` (most are compiled by default in modern PHP builds):

* `pdo_mysql`, `pdo_sqlite` (for local smoke tests)
* `curl`
* `mbstring`
* `openssl`
* `json`
* `zip`
* `dom`, `xml`
* `fileinfo`
* `iconv`
* `simplexml`
* `sodium`
* `pcntl` (needed for the queue worker)

## Installation Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/smeird/job.git /var/www/job
   cd /var/www/job
   ```

2. **Install dependencies**
   ```bash
   composer install --no-dev
   ```

3. **Copy and configure the environment file**
   ```bash
   cp .env.example .env
   ```
   Then populate the variables described below.

4. **Run database migrations**
   ```bash
   php bin/migrate.php
   ```

5. **Verify the installation with the smoke suite**
   The smoke script uses SQLite and lightweight stubs, so it can run on any workstation without external services.
   ```bash
   php bin/smoke.php
   ```
   Successful output confirms migrations, authentication, document workflows, OpenAI job handling (mocked), download rendering, and retention purge logic.

6. **Set correct permissions**
   Ensure the web server user can read the project directory and write to any configured storage paths (e.g., logs).

## Environment Configuration

The following variables are consumed by the application. Values shown are illustrative.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://job.smeird.com

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=job
DB_USERNAME=job
DB_PASSWORD=change-me

SMTP_HOST=smtp.mailprovider.com
SMTP_PORT=587
SMTP_USERNAME=apikey
SMTP_PASSWORD=change-me
SMTP_ENCRYPTION=tls

OPENAI_API_KEY=sk-your-key
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_MODEL_PLAN=gpt-4o-mini-plan
OPENAI_MODEL_DRAFT=gpt-4o-mini
OPENAI_TARIFF_JSON={"gpt-4o-mini":{"prompt":0.00025,"completion":0.001}}
OPENAI_MAX_TOKENS=4000
```

Additional optional variables:

* `APP_TIMEZONE` – override the default timezone.
* `SMTP_FROM_ADDRESS` / `SMTP_FROM_NAME` – customise outbound email sender details.

## Apache Virtual Host Example

```apache
<VirtualHost *:80>
    ServerName job.smeird.com
    DocumentRoot /var/www/job/public

    <Directory /var/www/job/public>
        AllowOverride All
        Options FollowSymLinks
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/job-error.log
    CustomLog ${APACHE_LOG_DIR}/job-access.log combined

    SetEnv APP_ENV production
    SetEnv APP_DEBUG false
</VirtualHost>
```

For HTTPS, wrap the virtual host in a `<VirtualHost *:443>` block with your TLS configuration and redirect HTTP to HTTPS.

## PHP Upload Limits

Documents are validated to 1 MiB (`DocumentValidator::MAX_FILE_SIZE`). Set matching limits in your `php.ini` or pool configuration:

```ini
upload_max_filesize = 1M
post_max_size = 1M
max_file_uploads = 5
file_uploads = On
```

Restart PHP-FPM or Apache after adjusting the configuration.

## Background Worker

The queue worker processes CV tailoring jobs and requires the `pcntl` extension. Run it under a supervisor such as systemd:

```ini
# /etc/systemd/system/job-worker.service
[Unit]
Description=job.smeird.com queue worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/job
ExecStart=/usr/bin/php /var/www/job/bin/worker.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
sudo systemctl enable --now job-worker.service
```

## Retention Purge

Configure a nightly cron job to enforce the retention policy configured in the admin UI (`/retention`):

```cron
0 2 * * * /usr/bin/php /var/www/job/bin/purge.php >> /var/log/job/purge.log 2>&1
```

## Smoke Suite Summary

The smoke suite (`php bin/smoke.php`) performs the following steps against an isolated SQLite database and lightweight mocks:

1. Creates the schema used by the application.
2. Drives the authentication flow (registration + login) with fake mail delivery.
3. Uploads and validates a Markdown document and extracts plain text.
4. Seeds and executes a generation job end-to-end using a mocked OpenAI provider and ensures download endpoints are available for Markdown, DOCX, and PDF artefacts.
5. Seeds retention data and runs the purge logic.

Use this script during CI or local development to confirm core behaviour without external dependencies.
