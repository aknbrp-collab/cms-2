# Run the CMS on another computer (local network demo)

How to copy the project to another Windows computer running Laravel Herd, make it work exactly like the original, and open it from phones and laptops on the same network.

```
 phone / laptop ──Wi-Fi──▶  demo PC (Herd, PostgreSQL)
 http://pondok.192.168.1.50.nip.io        ▲
         │                                 │
         └─ nip.io turns that name into 192.168.1.50 (the demo PC)
```

Why nip.io: the sites are told apart by their domain names, and phones can't use the `.test` domains (only the PC's own hosts file knows them). A name like `pondok.192.168.1.50.nip.io` resolves to `192.168.1.50` from any device with internet DNS, and Herd understands these names: it strips the IP part and serves the linked site `pondok`.

---

## 1. What to copy from this computer

Git (`https://github.com/aknbrp-collab/cms-2.git`) has the code but **not** the following. Copy them separately (USB drive, shared folder):

| What | Where | Why |
|---|---|---|
| `.env` | project root | settings and `APP_KEY`. Keep the same key: social tokens are encrypted with it, and logins depend on it. Contains passwords, so don't share it publicly. |
| Database dump `cms.dump` | made in step 1a | all content, users, settings |
| Uploaded files | `storage\app\public\` (media library, logos) and `storage\app\private\` (admission documents; skip `livewire-tmp`) | not in Git |
| `public\build\` (optional) | project root | built CSS/JS. Copy it if you don't want to install Node.js on the other computer. |

Not needed: `vendor\`, `node_modules\`, `public\storage` (recreated by a command), `storage\logs`, `storage\framework`.

### 1a. Make the database dump

In PowerShell, in the project folder. Use the database user from `.env` (`DB_USERNAME`); it asks for that user's password.

```powershell
& "C:\Program Files\PostgreSQL\17\bin\pg_dump.exe" -U postgres -h 127.0.0.1 -Fc -f cms.dump cms
```

## 2. Install on the other computer

| Software | Notes |
|---|---|
| **Laravel Herd for Windows** (free) | Choose PHP **8.4**, the same as this computer. Composer comes with Herd. |
| **PostgreSQL 17** | The Windows installer from postgresql.org (EDB). Note the password you give the `postgres` user. |
| **Git** | To clone the code (or copy the project folder instead). |
| **Node.js 22 LTS or newer** | Only to build CSS/JS. Skip it if you copied `public\build`. |

## 3. Set up the project

1. **Get the code** into a folder, e.g. `D:\herd-laravel\cms-ready`:
   ```powershell
   git clone https://github.com/aknbrp-collab/cms-2.git D:\herd-laravel\cms-ready
   cd D:\herd-laravel\cms-ready
   ```
2. **Copy** `.env` into the project folder. Change `DB_USERNAME` / `DB_PASSWORD` if PostgreSQL on this computer uses other credentials.
3. **Install PHP packages:**
   ```powershell
   composer install
   ```
4. **Restore the database:**
   ```powershell
   & "C:\Program Files\PostgreSQL\17\bin\createdb.exe" -U postgres -h 127.0.0.1 cms
   & "C:\Program Files\PostgreSQL\17\bin\pg_restore.exe" -U postgres -h 127.0.0.1 -d cms --no-owner cms.dump
   ```
5. **Restore the uploaded files:** copy the saved `public` and `private` folders into `storage\app\`.
6. **Build CSS/JS** (or copy `public\build` instead):
   ```powershell
   npm ci
   npm run build
   ```
7. **Finish:**
   ```powershell
   php artisan storage:link
   php artisan migrate
   php artisan optimize:clear
   ```
   `migrate` should report nothing to migrate. If it runs something, the code is newer than the database, which is fine.
8. **Link the three sites in Herd** (in the project folder):
   ```powershell
   herd link pondok
   herd link mahad
   herd link admin.pondok
   ```
9. **Check on the demo PC itself:** `http://admin.pondok.test`, `http://pondok.test`, `http://mahad.test`. Log in with the same accounts as on the original computer.

The project now works on this PC exactly like on the original. The next section adds access from other devices.

## 4. Open it to the local network

1. **Give the demo PC a fixed IP.** Find its address with `ipconfig` (e.g. `192.168.1.50`). Reserve that address for the PC in the router (DHCP reservation), or the names below break when the IP changes. The rest of this guide uses `192.168.1.50`; replace it with yours everywhere.

2. **Switch the site addresses to nip.io names.** In `.env`:
   ```dotenv
   APP_URL=http://admin.pondok.192.168.1.50.nip.io
   ADMIN_DOMAIN=admin.pondok.192.168.1.50.nip.io
   SITE_PONDOK_DOMAIN=pondok.192.168.1.50.nip.io
   SITE_MAHAD_DOMAIN=mahad.192.168.1.50.nip.io
   ```
   The site domains are also stored in the database, so update them too:
   ```powershell
   php artisan tinker --execute "App\Models\Site::query()->where('key','pondok')->update(['domain' => 'pondok.192.168.1.50.nip.io']); App\Models\Site::query()->where('key','mahad')->update(['domain' => 'mahad.192.168.1.50.nip.io']);"
   php artisan optimize:clear
   ```
   From now on, use the nip.io addresses on the demo PC too; the `.test` addresses stop matching the app.

3. **Let Herd listen on the network.** Out of the box Herd only answers the PC itself (`127.0.0.1`). Create the file `C:\Users\<your user>\.config\herd\config\server_snippets\lan.conf` containing one line:
   ```nginx
   listen 80;
   ```
   Then restart Herd (**Stop all**, then **Start all** in the Herd window). This is a separate file, so Herd's own updates leave it alone.

4. **Allow it through Windows Firewall.** Set the Wi-Fi/Ethernet connection to **Private** (Settings → Network → your connection → Network profile). Then, in PowerShell **as administrator**:
   ```powershell
   New-NetFirewallRule -DisplayName "Herd LAN demo (HTTP)" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Private
   ```

5. **Open it from a phone** on the same Wi-Fi:

   | Site | Address |
   |---|---|
   | Admin panel | `http://admin.pondok.192.168.1.50.nip.io` |
   | Pondok website | `http://pondok.192.168.1.50.nip.io` |
   | Ma'had website | `http://mahad.192.168.1.50.nip.io` |

## 5. While demoing

- **Keep two background processes running** in two PowerShell windows in the project folder. They handle image thumbnails, emails, scheduled posts and the social media demo:
  ```powershell
  php artisan queue:work
  ```
  ```powershell
  php artisan schedule:work
  ```
- **Don't run `npm run dev`** for a network demo. It creates `public\hot`, and other devices then try to load CSS/JS from the demo PC's `localhost` and the pages look broken. If that file exists, delete it.
- **Log in with a password:** passkeys need HTTPS (or `localhost`), so they don't work over `http://` on the network. Password login and 2FA codes work.
- **Same as on the original computer:** social media and SIAKAD run in demo mode, and emails are written to `storage\logs\laravel.log` instead of being sent.

## 6. Afterwards

- **Stop network access:** delete `lan.conf` and restart Herd. To also remove the firewall rule:
  ```powershell
  Remove-NetFirewallRule -DisplayName "Herd LAN demo (HTTP)"
  ```
- **Get updates from Git:**
  ```powershell
  git pull
  composer install
  npm ci
  npm run build
  php artisan migrate
  php artisan optimize:clear
  ```

## Troubleshooting

| Problem | Check |
|---|---|
| Phone says the site can't be reached | Same Wi-Fi? Firewall rule added and network set to Private? Herd restarted after adding `lan.conf`? Can the demo PC open `http://192.168.1.50`? |
| Name doesn't resolve on any device | nip.io needs internet access. Some routers block DNS answers that point to a local IP ("DNS rebinding protection"). Allow `nip.io` in the router, or use another network. |
| Works on the demo PC, 404 "site not found" elsewhere | The Herd links `pondok`, `mahad` and `admin.pondok` must exist (`herd links`). |
| Page loads but says Not Found, or opens the wrong site | `.env` domains and the database domains (step 4.2) must match the address exactly; run `php artisan optimize:clear` after changes. |
| Pages without styling | `public\build` missing (run `npm run build`) or `public\hot` present (delete it). |
| Images missing | Run `php artisan storage:link`; check that the `storage\app\public` files were copied. |
| "419 Page Expired" after login | The address in the browser must match `APP_URL` / `ADMIN_DOMAIN`; clear the browser's cookies for the site. |
| Thumbnails or emails never appear | `php artisan queue:work` isn't running. |
| Port 80 already in use | Another web server (IIS, Skype, XAMPP) is running; stop it. |
