# VoiceBot Admin

React admin SPA for the Telegram voice-bot project. Manage bots and their voice samples.

## Stack

Vite + React 19 + TypeScript + react-router-dom v7 + @tanstack/react-query v5 + axios + Tailwind CSS v4 + react-toastify + react-dropzone.

## Run locally

```bash
npm install
npm run dev
```

Dev server runs on http://localhost:5173.

By default the app talks to the backend at `http://127.0.0.1:8123/admin/api`. To point elsewhere, copy `.env.example` to `.env` and set `VITE_API_BASE_URL`:

```bash
cp .env.example .env
# edit VITE_API_BASE_URL
```

## Build

```bash
npm run build      # tsc -b && vite build  -> outputs to dist/
npm run typecheck  # tsc -b --noEmit
npm run preview    # serve the production build locally
```

Requires Node 20.19.0+ (Vite 8 engine constraint).

## Deploy to Vercel

- Set the Vercel project **Root Directory** to `admin/`.
- Set the env var `VITE_API_BASE_URL` to your backend's `/admin/api` URL.
- `vercel.json` adds SPA rewrites so client-side routes resolve to `index.html`.

## API contract

- Auth: JWT (Lexik), no refresh tokens. Token stored in `localStorage` under `token`.
  axios attaches `Authorization: Bearer <token>`; a 401 clears the token and redirects to `/login`.
- Envelopes: list `{data, meta}`, single `{data}`, delete `{status:"ok"}`, error `{message}`.

## Pages

- `/login` — email + password.
- `/bots` — list bots; add / edit / delete; jump to a bot's samples.
- `/bots/new`, `/bots/:id/edit` — bot form. Edit view shows the webhook URL with a copy button.
- `/bots/:id/samples` — list samples; drag-and-drop audio upload (mp3/wav/m4a/ogg); retry / delete.
