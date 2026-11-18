# Server requirements

Sizing for two separate servers: a **demo** server (showing the CMS to the institution, trying features) and the **production** server. Each comes in three tiers:

- **Minimum:** runs reliably, but with little headroom.
- **Ideal:** recommended.
- **Maximum:** the most that is still worth paying for. Anything bigger is wasted at this scale.

Setup steps are in [deployment.md](deployment.md).

## Assumptions

| | Demo | Production |
|---|---|---|
| CMS users (admin panel) | a few at a time | **10 accounts**, typically 2–5 active at once |
| Website visitors | a handful | **~50 visitors at the same time at peak** (e.g. the day PSB/PMB opens). This also covers "50 visitors a day" with a lot of room to spare. |
| Integrations | `SOCIAL_DRIVER=fake`, `SIAKAD_DRIVER=fake` | Meta/TikTok and SIAKAD live |
| Media files | on the server disk (`MEDIA_DISK=public`) | in an S3-compatible bucket (`MEDIA_DISK=s3`), required for Instagram/TikTok |

50 concurrent visitors produce roughly 2–5 page requests per second. A Laravel page here costs about 50–150 ms of CPU. Load is light, so memory decides the size more than CPU does.

## What uses the resources

| Component | Memory | Notes |
|---|---|---|
| Ubuntu, nginx, Supervisor, cron | ~400 MB | |
| PHP-FPM workers (website + admin) | 50–80 MB each | `pm.max_children` sets how many requests run at once |
| Horizon queue workers | 80–150 MB each, peaks of up to 256 MB | Image conversions (thumbnails, WEBP), social publishing, SIAKAD calls, emails |
| PostgreSQL | `shared_buffers` + ~200 MB | The database stays small (well under 1 GB in the first years) |
| Redis (queue, cache, sessions) | 50–150 MB | Use `maxmemory-policy noeviction` so queued jobs are never dropped |
| Asset build during deploy (`npm ci && npm run build`) | 1–1.5 GB for ~1 minute | Needs swap on 2 GB machines, or build in CI and upload `public/build` |
| Nightly backup (02:00) | ~200 MB and a CPU core for a few minutes | `pg_dump` + zip of `storage/app` |

Disk usage over time:

| Item | Size |
|---|---|
| OS, packages | ~8 GB |
| App releases (5 kept by `deploy.sh`) | ~1 GB |
| Database | < 1 GB |
| Logs (daily rotation, 14 days) | < 1 GB |
| **Admission documents**: always on the server's private disk, also in production | about applicants × documents × 1.5 MB per period, e.g. 300 × 4 × 1.5 MB ≈ **2 GB per period**, until the retention setting removes them |
| Local backup copies | capped by `BACKUP_MAX_MEGABYTES` (default 10 GB) |
| Media library (demo only; production uses S3) | ~3 MB per photo including conversions |

---

## Demo server

| | **Minimum** | **Ideal** | **Maximum** |
|---|---|---|---|
| vCPU | 1 | 2 | 4 |
| RAM | 2 GB **+ 2 GB swap** | 4 GB | 8 GB |
| Disk (SSD/NVMe) | 25 GB | 40 GB | 80 GB |
| Queue | `QUEUE_CONNECTION=database` with one `queue:work` process in Supervisor; no Redis needed | Redis + Horizon, same setup as production | Redis + Horizon |
| PHP-FPM `pm.max_children` | 5 | 10 | 20 |
| Queue workers (`HORIZON_MAX_PROCESSES`) | 1 | 2 | 3 |
| PostgreSQL `shared_buffers` | 256 MB | 512 MB | 1 GB |
| Media | server disk | server disk | server disk, or S3 to rehearse production |
| `BACKUP_MAX_MEGABYTES` | 3000 (or no backups) | 5000 | 10000 |
| Good for | showing the CMS to a few people; slow first image conversions are acceptable | demos plus a **staging** copy of production (try updates before releasing them) | demos for many reviewers at once, or acceptance testing with real media and video |

Notes:
- On the minimum tier, the asset build during deploy needs the swap file. Without it, build the assets elsewhere and copy `public/build`.
- A demo uses the fake social and SIAKAD drivers, so no Meta/TikTok/SIAKAD keys are needed. HTTPS is still recommended (free with certbot) so login cookies are secure.

## Production server (10 users, ~50 concurrent visitors)

| | **Minimum** | **Ideal** | **Maximum** |
|---|---|---|---|
| vCPU | 2 | 4 | 8, or split: app 4 vCPU + managed PostgreSQL 2 vCPU |
| RAM | 4 GB | 8 GB | 16 GB, or split: app 8 GB + database 4 GB |
| Disk (NVMe) | 40 GB | 80 GB | 160 GB |
| PHP-FPM `pm.max_children` | 10 | 20 | 40 |
| Queue workers (`HORIZON_MAX_PROCESSES`) | 3 | 5 (default) | 8 |
| PostgreSQL `shared_buffers` / `effective_cache_size` | 1 GB / 2 GB | 2 GB / 5 GB | 4 GB / 10 GB |
| Redis `maxmemory` | 256 MB | 512 MB | 1 GB |
| `BACKUP_MAX_MEGABYTES` (local copies) | 5000 | 10000 | 20000 |
| Media | S3 bucket (required) | S3 bucket + CDN | S3 bucket + CDN |
| Off-site backup bucket | required | required | required |
| Handles | the expected load. Slows down when several big video posts or many image uploads run during a busy moment | the expected load with 3–5× headroom: PSB opening day, social video publishing, nightly backups without visitors noticing | many times the expected load; the database on its own server keeps the website fast during heavy admin work |

Notes:
- **The minimum tier is fine to launch on.** Monitor it for a few weeks (Horizon wait times, `free -m`, PHP-FPM slow log), then move to the ideal tier if memory stays above ~80%. Most providers let you resize a VPS in minutes.
- **The maximum tier is only useful** if the CMS later serves more institutions or much more traffic. For 10 users and 50 concurrent visitors it is spare capacity.
- **Bandwidth is small:** pages are ~300 KB without images, and images come from S3/CDN. Expect well under 100 GB/month of server traffic, which any VPS plan includes.
- **Location:** choose a data centre in Jakarta or Singapore, for low latency to visitors in Indonesia.

## Other services for production (all tiers)

| Service | Requirement |
|---|---|
| Operating system | Ubuntu 24.04 LTS (or Debian 12), with automatic security updates |
| Software | PHP 8.4 (FPM + CLI) with the extensions in deployment.md §1, PostgreSQL 16/17 + `postgresql-client`, Redis 7, nginx, Supervisor, Composer 2, Node.js 22 LTS (build only) |
| Domains | `admin.<pondok domain>`, `<pondok domain>` + `www`, `<ma'had domain>` + `www`; TLS via certbot (free) |
| Object storage | S3-compatible: one **public** bucket for media (Instagram/TikTok fetch from it), one **private** bucket for off-site backups, ideally with another provider or region. Start with ~10 GB each. |
| Email (SMTP) | A transactional mail service for notifications, password resets and admission emails |
| Monitoring | An uptime check on `/up` and both home pages. Backup and queue alerts go to `CMS_ALERT_EMAIL`. |
| Access | SSH keys only, firewall with ports 22/80/443 open, 2FA or passkeys for every superadmin |

After setup, `php artisan cms:check` must report no FAIL lines (see [deployment.md](deployment.md) §5).
