# Backend deploy (production)

The backend is a Symfony 7.4 app. In prod it needs:
- **PHP 8.2+** (ffmpeg in PATH — already installed), Composer, MariaDB/MySQL.
- A web server (Apache/nginx) with docroot = **`backend/public`** and a **public HTTPS** domain (Telegram webhooks require HTTPS).
- A process manager (supervisor/systemd) for the **Messenger worker**.

The admin SPA deploys separately to Vercel (root dir `admin/`, env `VITE_API_BASE_URL=https://<backend-domain>/admin/api`).

---

## First deploy (once)

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

Create an **untracked** `backend/.env.local` with the real prod values:

```dotenv
APP_ENV=prod
APP_SECRET=<random 32+ chars>
DATABASE_URL="mysql://user:pass@127.0.0.1:3306/dbname?charset=utf8mb4&serverVersion=10.11.0-MariaDB"
JWT_PASSPHRASE=<random>
# Allow the Vercel admin origin (regex):
CORS_ALLOW_ORIGIN='^https://<your-admin>\.vercel\.app$'
```

Then:

```bash
php bin/console lexik:jwt:generate-keypair        # writes config/jwt/*.pem (uses JWT_PASSPHRASE)
php bin/console doctrine:migrations:migrate -n    # creates all tables
php bin/console cache:clear                       # prod cache (composer install also runs this)
php bin/console app:admin:create --email=you@example.com --password='...'
```

Make `backend/public/audio` and `backend/var` writable by the web-server user.

Start the **worker** under supervisor (see below) — it must always run for sample warming.

Then, in the **admin** (Vercel): create each bot (name, username, token, storage chat id), and add each bot as **admin** to the storage channel. Finally register the webhooks:

```bash
php bin/console app:bot:set-webhook --all --url=https://<your-backend-domain>
```

Upload samples via the admin (or `php bin/console app:import-audio <bot>`); the worker warms them.

---

## Every subsequent deploy

```bash
cd backend
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console cache:clear
php bin/console messenger:stop-workers      # running workers exit after current job; supervisor restarts them with new code
```

- **Webhooks: do NOT re-register on routine deploys.** `setWebhook` is a one-time action per bot — it persists on Telegram's side. Re-run `app:bot:set-webhook` only if the **domain changes** or you **add a new bot**.
- **Worker: must be restarted** so it loads new code — that's what `messenger:stop-workers` is for (graceful), supervisor brings it back up.

---

## The Messenger worker (supervisor example)

`/etc/supervisor/conf.d/voicebot-worker.conf`:

```ini
[program:voicebot-worker]
command=php /path/to/backend/bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
user=www-data
numprocs=1
autostart=true
autorestart=true
startsecs=0
```

`--time-limit=3600` makes it exit hourly (and after `messenger:stop-workers`); supervisor restarts it. Without a running worker, uploaded samples stay `pending` forever.

(systemd alternative: a simple service running the same `messenger:consume` command with `Restart=always`.)

---

## Your questions, answered

- **"After pull + migrations, start the consumer?"** — Yes: on the first deploy set up the supervisor worker; on later deploys it's already running, you just **restart** it (`messenger:stop-workers`) so it picks up new code. It must be running whenever you want uploads to warm.
- **"How to register webhooks, or is it optional?"** — In prod it's **required** (Telegram pushes updates via the webhook; local dev used `app:bot:poll` to avoid it). The admin only *shows* the URL — you still register it once with `app:bot:set-webhook --all --url=https://<domain>`. It persists; no need to repeat per deploy.

## Notes

- Once a sample is warmed, its file is no longer needed to *serve* it (Telegram hosts it by file_id) — but keep `public/audio` if you want to re-warm. Don't wipe it on deploy.
- `config/jwt/*.pem`, `.env.local`, `public/audio/`, `var/` are git-ignored — each environment has its own.
- Telegram bot tokens live in the DB (`bot` table), per bot — not in env.
