# VOICEBOT

Telegram inline bot: drop audio samples into a folder, import them, and send any
sample into a chat as a cached audio message via `@botname <search>`.

## Requirements

- PHP 8.1+
- MariaDB 10.x or MySQL 5.7+ (InnoDB FULLTEXT is used for search)
- `ffmpeg` in `PATH` (samples are converted to OGG/Opus on import)

## Installation

1. `git clone https://github.com/gumione/voicebot.git . && composer install`
2. Create an **untracked** `.env.local` with your real settings (never commit secrets):
   ```dotenv
   APP_ENV=dev                       # omit on a server: .env defaults to prod
   APP_SECRET=<random-string>
   DATABASE_URL="mysql://user:pass@host/dbname?charset=utf8mb4&serverVersion=mariadb-10.8.4"
   TELEGRAM_BOT_TOKEN=<token from @BotFather>
   TELEGRAM_BOT_USERNAME=<botname without @>
   TELEGRAM_STORAGE_CHAT_ID=<chat/channel id used to mint file_ids>
   TELEGRAM_WEBHOOK_SECRET=<random-string>
   ```
   Enable inline mode for the bot in @BotFather so it can be called as `@botname`.
3. `php bin/console doctrine:migrations:migrate`
4. Put your audio files into `public/audio/<Artist>/<Title>.mp3` (subfolder = artist).
5. `php bin/console app:import-audio` — scans the folder, adds new rows, and
   uploads each new sample **once** to `TELEGRAM_STORAGE_CHAT_ID` to obtain a
   reusable `file_id` (use `--no-warm` to skip the upload step).
6. Register the webhook with the same secret:
   `https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://<host>/bot/index&secret_token=<TELEGRAM_WEBHOOK_SECRET>`

## Notes

- `.env` holds non-secret defaults and is committed; real credentials live in
  `.env.local` (git-ignored). `APP_ENV` defaults to `prod`.
- The inline handler is read-only — it never converts or uploads, so the webhook
  stays fast. All heavy work happens in `app:import-audio`.

## Live example

> http://t.me/gtalks_bot
