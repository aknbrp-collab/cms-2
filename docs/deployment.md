# Deployment and operations

How to run the CMS in production on a single Linux VPS. One Laravel app serves three hosts: the admin panel (`ADMIN_DOMAIN`) and the two public sites (Pondok, Ma'had Aly).

```
             ┌──────────── nginx (HTTPS) ────────────┐
admin.pondok │  pondok.example  │  mahad.example     │
             └──────────────── PHP-FPM ──────────────┘
                        │             │
                 PostgreSQL       Redis ── Horizon (queue workers)
                        │                   cron → schedule:run
                 S3 bucket (media)     S3 bucket (off-site backups)
```

Files in [`deploy/`](../deploy): `nginx.conf`, `horizon.conf` (Supervisor), `deploy.sh`.

## 1. Server requirements

| Component | Version / notes |
|---|---|
| OS | Ubuntu 24.04 LTS (or Debian 12) |
| PHP | 8.4 (8.3 minimum), FPM + CLI, extensions: `pgsql`, `pdo_pgsql`, `redis`, `gd`, `intl`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `exif`, `pcntl`, `posix` |
| PostgreSQL | 16 or 17, plus `postgresql-client` (provides `pg_dump` for backups) |
| Redis | 7, bound to `127.0.0.1`, with a password |
| nginx, Supervisor, certbot | from the distribution |
| Composer | 2.x |
| Node.js | 22 LTS or newer (only needed at build time) |

Sizing: see [server-requirements.md](server-requirements.md) for minimum, ideal and maximum specs for a demo server and for production. In short, production starts at 2 vCPU / 4 GB RAM / 40 GB NVMe (3 queue workers), and 4 vCPU / 8 GB / 80 GB is recommended.

PHP settings (`/etc/php/8.4/fpm/conf.d/99-cms.ini`):

```ini
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
expose_php = Off
opcache.enable = 1
opcache.validate_timestamps = 0   ; deploy.sh reloads PHP-FPM
```

## 2. DNS

Point these A/AAAA records at the server: `admin.pondok.example`, `pondok.example`, `www.pondok.example`, `mahad.example`, `www.mahad.example`. If the media bucket gets a CDN or custom domain (needed for TikTok URL verification), add that record as well.

## 3. First-time setup

### 3.1 Database and Redis

```bash
sudo -u postgres psql -c "CREATE USER cms WITH PASSWORD 'long-random-password';"
sudo -u postgres psql -c "CREATE DATABASE cms OWNER cms;"
```

The `cms` user must own the database: a migration creates the trigger that makes `audit_logs` append-only.

In `/etc/redis/redis.conf`, set `bind 127.0.0.1 ::1` and `requirepass <password>`.

### 3.2 Directories

```bash
sudo mkdir -p /var/www/cms/{releases,shared/storage}
sudo chown -R www-data:www-data /var/www/cms
# Copy storage/ from the repository once to create the folder skeleton:
sudo -u www-data git clone --depth 1 <repo> /tmp/cms && sudo -u www-data cp -r /tmp/cms/storage/. /var/www/cms/shared/storage/
sudo -u www-data cp /tmp/cms/.env.example /var/www/cms/shared/.env
```

Deploys run as `www-data`. Give that user an SSH deploy key with read access to the repository, and let it run `sudo systemctl reload php8.4-fpm` without a password (via a `sudoers.d` entry).

### 3.3 Environment (`/var/www/cms/shared/.env`)

Start from `.env.example` and set at least the following:

| Key | Production value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_KEY` | generate once with `php artisan key:generate --show`. **Store a copy in the password manager**: social tokens are encrypted with it, and a restored database is useless without it. |
| `APP_URL` | `https://admin.pondok.example` |
| `ADMIN_DOMAIN`, `SITE_PONDOK_DOMAIN`, `SITE_MAHAD_DOMAIN` | the real hosts, set **before the first seed** (site domains are stored in the database) |
| `DB_*` | the database from 3.1 |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `redis` / `redis` / `redis` |
| `REDIS_PASSWORD` | from 3.1 |
| `SESSION_SECURE_COOKIE` | `true` |
| `MAIL_*` | a real SMTP service: editor notifications and password resets depend on it |
| `MEDIA_DISK` + `AWS_*` | `s3` and a public bucket (or CDN), required for Instagram/TikTok publishing |
| `CMS_ALERT_EMAIL` | who receives backup and queue alerts |
| `BACKUP_DISKS`, `BACKUP_ARCHIVE_PASSWORD`, `BACKUP_AWS_*` | `local,backups`, a long password (also in the password manager), and a private off-site bucket |
| `SIAKAD_*`, `SOCIAL_DRIVER`, `META_*`, `TIKTOK_*` | when the integrations go live (see [siakad-api-spec.md](siakad-api-spec.md), [social-review.md](social-review.md)) |
| `TRUSTED_PROXIES` | only when a load balancer or CDN (e.g. Cloudflare proxy) sits in front of nginx |

### 3.4 First deploy

```bash
sudo -u www-data bash deploy/deploy.sh main      # from a checkout of the repo
cd /var/www/cms/current
sudo -u www-data php artisan db:seed --force      # sites, roles, permissions (no demo data in production)
sudo -u www-data php artisan cms:create-superadmin
```

The first `cms:check` inside `deploy.sh` fails until the sites and a superadmin exist. In that case, run the two commands above from the new release directory, then run `deploy.sh` again.

### 3.5 nginx, HTTPS, Horizon and cron

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/cms   # edit domains first
sudo ln -s /etc/nginx/sites-available/cms /etc/nginx/sites-enabled/cms
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d admin.pondok.example -d pondok.example -d www.pondok.example -d mahad.example -d www.mahad.example

sudo cp deploy/horizon.conf /etc/supervisor/conf.d/cms-horizon.conf
sudo supervisorctl reread && sudo supervisorctl update

sudo crontab -u www-data -e
# * * * * * cd /var/www/cms/current && php artisan schedule:run >> /dev/null 2>&1
```

### 3.6 Firewall and access

```bash
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
```

PostgreSQL and Redis listen on localhost only. Use SSH keys and disable password login. Every superadmin should turn on two-factor authentication or a passkey (Settings → Security).

## 4. Every deploy

```bash
sudo -u www-data bash /path/to/deploy.sh main
```

`deploy.sh` works in this order:
1. Clones a fresh release.
2. Links the shared `.env` and `storage`.
3. Installs dependencies and builds the assets.
4. Runs the migrations and syncs roles and permissions.
5. Caches the config.
6. Runs `cms:check`.
7. Switches `current` to the new release.
8. Reloads PHP-FPM and restarts Horizon.
9. Keeps the last 5 releases.

If any step fails, `current` still points at the old release. Note that migrations have already run by that point, so keep migrations backward compatible.

**Rollback:**
```bash
ln -sfn /var/www/cms/releases/<previous> /var/www/cms/current
sudo systemctl reload php8.4-fpm
php /var/www/cms/current/artisan horizon:terminate
```
Roll back the database only if the release's migrations require it.

## 5. Readiness check

`php artisan cms:check` prints OK / WARN / FAIL for each item and exits non-zero on any FAIL. It checks:
- debug mode, app key, HTTPS URL and secure cookies
- the PostgreSQL driver and the audit-log triggers
- real site domains and that a superadmin exists
- Redis, mail and the alert email
- the social and SIAKAD settings
- the backup disks, encryption and `pg_dump`

Run it after any `.env` change (followed by `php artisan optimize`).

## 6. Queues (Horizon)

All jobs run on the `default` queue under one supervisor (`HORIZON_MAX_PROCESSES`, default 5). The jobs:
- social publishing (`PublishSocialTarget`, up to 300 s)
- TikTok status polling
- SIAKAD question forwarding (5 tries with backoff)
- image conversions
- notifications

The dashboard is at `https://admin.pondok.example/horizon` and only superadmins can open it. Failed jobs are listed there and pruned after 30 days.

Queue `retry_after` is 360 s, which must stay above the longest job timeout (300 s). Supervisor's `stopwaitsecs` (330 s) lets a running publish finish during restarts.

## 7. Scheduled tasks

| When | Command | Purpose |
|---|---|---|
| every minute | `cms:publish-scheduled` | publish content approved for a future date |
| every minute | `social:dispatch-due` | send scheduled social posts |
| hourly | `social:refresh-tokens` | refresh TikTok tokens before they expire |
| every 5 min | `horizon:snapshot` | queue metrics |
| 01:00 | `model:prune` | delete integration logs older than `CMS_RETAIN_INTEGRATION_LOGS_DAYS` (180) |
| 01:10 | `queue:prune-failed --hours=720` | drop failed jobs older than 30 days |
| 01:20 | `auth:clear-resets` | expired password-reset tokens |
| 01:30 | `backup:clean` | apply the retention policy |
| 02:00 | `backup:run` | database + uploads |
| 07:00 | `backup:monitor` | email if the newest backup is older than a day or storage is too large |

Audit logs are never pruned.

## 8. Backups and restore

**What is backed up:** a gzipped `pg_dump` of the database and `storage/app` (uploads on local disks, which includes the files on the `public` disk). With `MEDIA_DISK=s3`, protect the media bucket with **bucket versioning** (and lifecycle rules) instead.

Archives are encrypted with `BACKUP_ARCHIVE_PASSWORD` and written to every disk in `BACKUP_DISKS`. Use `local,backups` so one copy is off-site.

**Retention:**
- every backup for 7 days
- one per day for 16 days
- one per week for 8 weeks
- one per month for 4 months
- one per year for 2 years
- the total is capped at `BACKUP_MAX_MEGABYTES`

Failures and unhealthy backups are emailed to `CMS_ALERT_EMAIL`.

Useful commands:
```bash
php artisan backup:run            # now (add --only-db for just the database)
php artisan backup:list           # backups and their health per disk
```

The superadmin can also back up, download, delete and restore backups in the admin under **System → Data & backups**.

**Restore** (practise this once before going live). Either:

- **Admin:** Data & backups → **Restore** on a backup, re-enter the password and type the backup name. It runs in the background (Horizon must be running).
- **Command:** `php artisan cms:restore --disk=local --path=cms-pondok/2026-11-20-02-00-00.zip` (or the path to a downloaded `.zip`; add `--no-backup` to skip the safety backup).

Both check the server (`psql`, `BACKUP_ARCHIVE_PASSWORD`, zip), back up the current data, unpack and verify the archive, then put the sites in maintenance mode and load the dump with `psql` in **one transaction** after dropping and recreating the `public` schema: if anything fails, the database is left as it was. Uploaded files under `storage/app` are copied back afterwards (files not in the backup are kept), and the sites come back online. The database user (`DB_USERNAME`) must own the database, as created in section 3.1, so it can recreate the schema.

Manual restore, if the application itself cannot run:
1. `php artisan down`.
2. Download the archive (`storage/app/private/cms-pondok/*.zip` or from the off-site bucket) and unzip it with the archive password (`7z x` or `unzip`, AES-256).
3. Restore into an **empty** database. The audit-log trigger blocks deletes, so don't restore over existing data:
   ```bash
   sudo -u postgres dropdb cms && sudo -u postgres createdb -O cms cms
   gunzip -c db-dumps/postgresql-cms.sql.gz | psql -U cms -h 127.0.0.1 cms
   ```
4. Copy `storage/app/...` from the archive back into `/var/www/cms/shared/storage/app/`.
5. Make sure `.env` has the **same `APP_KEY`** as when the backup was taken.
6. `php artisan optimize && php artisan up`, then run `php artisan cms:check`.

## 9. Security measures in place

- **Headers on every response:**
  - `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` and `Cross-Origin-Opener-Policy`
  - a CSP limited to `frame-ancestors`, `base-uri` and `object-src`
  - HSTS over HTTPS (`HSTS_MAX_AGE`)
  - There is no full script CSP: Livewire/Alpine and the analytics snippets need inline scripts.
- **Uploads:**
  - The type allow-list is in `MediaAsset::ACCEPTED_EXTENSIONS`, with a 12 MB limit and randomised file names.
  - SVGs are sanitized on upload (scripts, event handlers and external references are removed).
  - nginx serves `/storage/` with a sandboxing CSP.
- **Rate limits:**

  | Endpoint | Limit |
  |---|---|
  | Login | 5/min per account + 20/min per IP |
  | Two-factor challenge | 5/min |
  | SIAKAD webhooks | 120/min per IP, HMAC-signed with a 5-minute replay window |
  | Meta callbacks | 60/min, `signed_request` verified |
  | Tanya Hukum questions | 2/min and 10/day per IP |
  | Tanya Hukum status lookup | 30/min |
  | PSB registration | 3/min and 20/day per IP |

  Both public forms also have a honeypot and a minimum fill time.
- **Access control:**
  - per-module permissions, with site-scoped roles and query scoping
  - the content workflow (site admins can't publish)
  - the Horizon dashboard is superadmin-only
- **Data:**
  - append-only audit log (enforced by a database trigger), with personal data and secrets excluded
  - social tokens are encrypted at rest
  - the Tanya Hukum asker's email is never sent to SIAKAD

## 10. Monitoring

- **Health:**
  - `https://admin.pondok.example/up` returns 200 when the app boots; point an uptime monitor at it and at both public home pages.
  - **Horizon** shows queue throughput, wait times and failed jobs. Long waits are emailed to `CMS_ALERT_EMAIL`.
- **Logs:**
  - `storage/logs/laravel.log`; set `LOG_STACK=daily` with `LOG_DAILY_DAYS=14` to rotate.
  - `storage/logs/horizon.log`.
- **Integration log** (admin → SIAKAD → Integration log): every SIAKAD call, with retry.
- **Social media → Posts**: failed targets with the platform's error message, plus retry.

## 11. Troubleshooting

| Symptom | Check |
|---|---|
| Scheduled posts or content never go out | Is cron running `schedule:run`? Try `php artisan schedule:list` |
| Jobs stay queued | `sudo supervisorctl status cms-horizon`, Redis reachable, Horizon dashboard |
| Changes to `.env` have no effect | Config is cached: run `php artisan optimize` |
| Wrong client IP / "not secure" behind a proxy | Set `TRUSTED_PROXIES` |
| Instagram/TikTok "media URL not accessible" | `MEDIA_DISK=s3`, the bucket objects are public, TikTok domain verified |
| Backup fails with "pg_dump not found" | Install `postgresql-client` or set `DB_DUMP_BINARY_PATH` |
| 419 Page Expired after login | `SESSION_SECURE_COOKIE=true` requires HTTPS; `SESSION_DOMAIN` must be empty or match the admin host |
