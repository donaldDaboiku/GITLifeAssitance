# GIT Life Assistant

Remember. Plan. Act.

Phase 1 through Phase 6 are in this repo.

- **Phase 1:** sign up, recurring payments and tasks, reminders, mark paid, installable PWA.
- **Phase 2:** payment categories/methods and expected week/month totals, contacts, birthdays/anniversaries with gift planning, shopping lists with kobo totals, visits with follow-up offers, activity links, and global search.
- **Phase 3:** same React app in Tauri (Windows) and Capacitor (Android), tray/widgets/global shortcut, Android notification actions, device registration, and Sanctum token login for native shells.
- **Phase 4:** sync engine — push/pull with idempotent mutations, tombstones, completed-status protection, offline queue with backoff, and auto-sync while online.
- **Phase 5:** AI assistant — parse → confirm cards, server-side tools, usage caps, optional Whisper voice (not browser speech).
- **Phase 6:** morning summary, full expected payment forecast (incl. next month), confirm-only follow-up suggestions, settings.
- **Privacy:** NDPA privacy notice, data export, and account deletion.
- **Web Push:** PWA push subscriptions stored on web devices; reminders go through in-app, email, and Web Push.

Laravel has not shipped a long-term-support release since version 6. This project uses **Laravel 13**, the current supported release (PHP 8.3+).

## Architecture (short)

An **activity** is the definition (Internet bill, a task). A **recurrence** is an RRULE on that definition. An **occurrence** is one due date with its own status. Paying, completing, skipping, or snoozing changes the occurrence only. Upcoming, Due Today, and Overdue are calculated, not stored. Money is stored as integer kobo (₦20,000 is `2000000`). Every syncable row has a UUID, a version, `updated_at`, and `deleted_at` so a later sync engine can use the same tables. The sync engine itself is not in this phase.

## What you need

- PHP 8.3 or newer, with `pdo_pgsql`
- Composer
- Node.js 20 or newer
- Docker Desktop (for PostgreSQL)

## 1. Start the database

From the project folder:

```powershell
docker compose up -d
```

PostgreSQL listens on port **5433** so it does not clash with a PostgreSQL already installed on port 5432. The local database name, user, and password are all `gitlife`. That password is only for this development database.

## 2. Start the API

```powershell
cd backend
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

If `.env` already exists, skip the copy and key steps. The API is at http://localhost:8000.

Email reminders are written to the Laravel log (`backend/storage/logs/laravel.log`) unless you set a real mail server in `.env`.

## 3. Start the reminder scheduler

In a second terminal:

```powershell
cd backend
php artisan schedule:work
```

That runs `reminders:dispatch` every minute. You can also run it once:

```powershell
php artisan reminders:dispatch
```

Each reminder has a key of occurrence id plus offset. The scheduler will not send that key twice, including when it catches up after being offline.

## 4. Start the PWA

In a third terminal:

```powershell
cd frontend
npm install
npm run dev
```

Open http://localhost:5173. On a phone on the same Wi-Fi, open `http://<your-computer-ip>:5173` and add that host (with port 5173) to `SANCTUM_STATEFUL_DOMAINS` in `backend/.env`, then restart `php artisan serve`.

The app is installable: it has a web manifest and a service worker. In Chrome on a phone, use the browser menu and choose Install app, or Add to Home Screen.

## 5. Run the tests

```powershell
cd backend
php artisan test
```

Tests use an in-memory SQLite database. They do not need Docker.

## Try Phase 2

1. Add a person under People.
2. Add a birthday with a gift idea. That creates a linked shopping item and a “Buy gift” task.
3. Complete a visit. Choose follow-up options, then confirm.
4. Open Shopping to see estimated, actual, and remaining totals.
5. Use Search to find something by name across activities, people, shopping, and notes.
6. On Home, check **Expected payments** for this week and this month.

## Phase 3 native shells

Native apps use the same React build. They sign in with `POST /api/token-login` (bearer token) instead of cookies, and register under **Devices**.

### Windows (Tauri)

Needs Rust (`rustc` / `cargo`) once. From `frontend`:

```powershell
npm run tauri:dev
```

That opens the desk app with tray menu, **Ctrl+Alt+Space** Quick Capture, and `/widget` compact/expanded views. Change the shortcut under Devices.

### Android (Capacitor)

Needs Android Studio. From `frontend`:

```powershell
npm run build
npx cap sync android
npm run cap:open
```

Point the device at your API with `VITE_API_URL` (for example `http://192.168.x.x:8000`) before `npm run build`. Local notification actions Open / Done / Mark Paid / Snooze are registered at startup; the FCM token is stored on the device row when push registration succeeds.

See [docs/PHASE3.md](docs/PHASE3.md) for the acceptance checklist.

## Phase 4 sync

While signed in, the app pushes queued mutations and pulls server changes about every 30 seconds (and when the network comes back). Done / Mark paid / Snooze still work offline; they sit in a local queue until sync succeeds.

```powershell
cd backend
php artisan migrate
php artisan test --filter=PhaseFourSyncTest
```

See [docs/PHASE4.md](docs/PHASE4.md).

## Phase 5 assistant

Quick Capture now **parses** first, then shows a **confirmation card** before saving. Ask questions under **Assistant**. Voice uploads audio to Whisper when `SPEECH_TO_TEXT_PROVIDER=openai` is set (browser speech is not used).

```powershell
cd backend
php artisan migrate
php artisan test --filter=PhaseFiveAssistantTest
```

Optional in `backend/.env`:

```
AI_PROVIDER=openai
AI_API_KEY=sk-...
AI_MODEL=gpt-4o-mini
SPEECH_TO_TEXT_PROVIDER=openai
SPEECH_TO_TEXT_API_KEY=sk-...
```

Without keys, the local heuristic parser still handles common phrases. See [docs/PHASE5.md](docs/PHASE5.md).

## Phase 6 summaries and forecast

Home shows **expected** totals for this week, this month, and next month. Suggested follow-ups appear for confirmation only. Enable the morning summary under **Settings**.

```powershell
cd backend
php artisan migrate
php artisan test --filter=PhaseSixSummaryForecastTest
php artisan schedule:work
```

See [docs/PHASE6.md](docs/PHASE6.md).

## Privacy (NDPA)

Under **Settings → Privacy** you can accept the privacy notice, download a JSON export of your data, or permanently delete your account (password required).

```powershell
cd backend
php artisan migrate
php artisan test --filter=PrivacyNdpaTest
```

See [docs/PRIVACY.md](docs/PRIVACY.md).

## Web Push

Enable browser reminders under **Settings → Web push**. Generate keys once:

```powershell
cd backend
php artisan webpush:vapid
# if that fails on Windows OpenSSL:
npx --yes web-push generate-vapid-keys
```

Paste into `backend/.env`, restart the API, then use Enable push on `http://localhost:5173` (HTTPS or localhost required). See [docs/WEBPUSH.md](docs/WEBPUSH.md).




