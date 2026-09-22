# GIT Life Assistant

Remember. Plan. Act.

Phase 1 and Phase 2 are in this repo.

- **Phase 1:** sign up, recurring payments and tasks, reminders, mark paid, installable PWA.
- **Phase 2:** payment categories/methods and expected week/month totals, contacts, birthdays/anniversaries with gift planning, shopping lists with kobo totals, visits with follow-up offers, activity links, and global search.

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
