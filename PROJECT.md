# GIT Life Assistant

**Tagline:** Remember. Plan. Act.

## 1. Vision

GIT Life Assistant is a unified personal life-management assistant. It is **not** primarily a reminder app, a calendar, a shopping app, or an accounting app. Its differentiator is that modules work together:

- Birthday → gift idea → shopping item → purchase reminder → birthday reminder → follow-up message
- Payment → upcoming reminder → mark paid → next occurrence generated
- Client visit → reminder → visit completed → follow-up task → quotation reminder

One account syncs across Android, Windows desktop, and Web/PWA. It must remain useful offline.

Financial features are for **planning and reminders only**. Always label amounts "expected" or "planned", never as actual transactions.

## 2. Technology stack

| Layer | Choice |
|-------|--------|
| Backend | Laravel (current LTS), PHP, REST API with API Resources |
| Database | PostgreSQL (JSONB for type-specific metadata) |
| Queue/scheduler | Laravel Scheduler + queues (database driver first, Redis later) |
| Web/PWA | React + TypeScript (Vite), installable PWA, responsive |
| Windows | Tauri wrapping the same React app, plus native tray/shortcut/notifications |
| Android | Capacitor wrapping the same React app (default; confirm or keep Flutter for fully native Android) |
| Push | FCM for Android; Web Push for PWA; native notifications on Windows |
| Fallback channels | Email first; WhatsApp/SMS via a provider configured in `.env` |
| Auth | Laravel Sanctum: cookie-based for web SPA, personal access tokens for native apps |

Share one React codebase and one TypeScript API client across all three front ends.

## 3. Core principle: everything is an Activity

Activity types: payment, task, birthday, anniversary, visit, appointment, shopping, maintenance, subscription, follow_up, habit, event, custom.

Separate three concepts:

1. **Activity** = the definition (title, type, rules).
2. **Recurrence** = an RFC 5545 RRULE string attached to an activity.
3. **Occurrence** = one concrete due instance with its own status. A one-off activity has exactly one occurrence.

Completing, paying, skipping, or snoozing acts on an **occurrence**, never on the definition.

### Common Activity fields

`id (UUID, client-generated)`, `user_id`, `type`, `title`, `description`, `category`, `priority`, `timezone (IANA, default Africa/Lagos)`, `location`, `contact_id`, `notes`, `metadata (JSONB)`, `created_at`, `updated_at`, `deleted_at`, `version`, `origin_device_id`.

### Occurrence fields

`id (UUID)`, `activity_id`, `due_at (UTC)`, `due_local_date`, `status`, `completed_at`, `snoozed_until`, `version`, `updated_at`, `deleted_at`.

Stored occurrence statuses: `pending`, `in_progress`, `completed` (paid for payments), `skipped`, `cancelled`, `deferred`.

**Computed, never stored:** Upcoming, Due Soon, Due Today, Overdue. Derive these from `due_at`, `status`, and the user's "due soon" window.

### Type-specific data

- `payment_details`: `amount_minor BIGINT` (kobo), `currency` (default NGN), `payment_category`, `payment_method`, `account_reference` (encrypted).
- `tasks`: subtasks and follow-up rules (see Section 7).
- Birthdays/anniversaries link to `contacts`.
- Shopping uses `shopping_lists` and `shopping_items`.
- Anything else goes in `metadata` JSONB until it proves it needs columns.

### Links between activities (the differentiator)

Table `activity_links`: `parent_activity_id`, `child_activity_id`, `relation` (`gift_for`, `follow_up_of`, `generated_from`, `part_of`).

Workflow rules (implemented as services, not hard-coded in controllers):

- Birthday created with gift planning → create a shopping item and a "buy gift" task linked to it.
- Visit completed → offer: create follow-up task, quotation reminder, report task, or next visit.
- Payment marked paid → generate next occurrence.
- Task with follow-up rule ("remind me again in 2 days if not done") → create a linked follow-up occurrence when the first stays incomplete.

## 4. Recurrence and reminders

Use a maintained RRULE library on the server and client. Do not hand-roll date maths.

Support: once, daily, weekly, monthly, yearly, every N days/weeks/months, specific weekday, first/second/last weekday of month, last day of month, business day, custom RRULE.

Rules:

- Monthly on the 29th/30th/31st **clamps to the last day** of shorter months (Jan 31 → Feb 28/29 → Mar 31).
- Store `due_at` in UTC and keep the IANA timezone with the activity so times don't drift.
- Reminders are offsets from `due_at` (e.g. 14 days, 7 days, 3 days, 1 day, 0). Multiple per activity.
- Reminder time-of-day for date-only items comes from a user preference (default 09:00).
- Snooze sets `snoozed_until` on the occurrence and never modifies the RRULE. Options: 15 min, 1 hour, tomorrow, next week, custom.
- **Missed reminders:** if the scheduler or device was offline, fire overdue reminders once on recovery (catch-up), not once per missed minute.
- Materialize occurrences on a rolling horizon (next 12 months) and generate the next one when the current one is completed or skipped.
- "Business day" ignores Saturdays and Sundays. Public-holiday awareness is a later optional feature.

## 5. Notifications

- The server scheduler (every minute) finds reminders whose fire time has passed and sends them via the enabled channels.
- Every reminder has a **deterministic key**: `occurrence_id + offset_minutes`. Store it in a unique-indexed `notification_deliveries` table so it can never be sent twice.
- Devices also schedule **local** notifications for offline use, using the same key. When any device acknowledges (done, paid, snooze, open), the server marks the key handled and other devices cancel their copy.
- Users choose which devices and channels receive notifications.
- Notification actions: Open, Complete, Mark Paid (payments only), Snooze.
- Channels are pluggable behind an interface: in-app, email, web push, FCM, WhatsApp/SMS.

## 6. Synchronization

Design for it from Phase 1; build the engine in Phase 4.

- Every syncable table has: client-generated UUID `id`, `version`, server-assigned `updated_at`, `deleted_at` (tombstones), `origin_device_id`.
- **Push:** client sends a batch of mutations, each with a unique `client_mutation_id` so retries are idempotent.
- **Pull:** client sends its last cursor; server returns changes after it, including tombstones.
- **Conflicts:** last-write-wins per field using server time, with one exception: a completed/paid status is not overwritten by an older pending edit unless the user explicitly reopens it.
- Offline queue with exponential-backoff retry.
- `devices` table: id, name, type, last_sync_at, app_version, push_token, active.

## 7. Feature modules

**Payments.** Name, amount, currency, due date, recurrence, category, method, reference, notes. Statuses as in Section 3. Marking paid completes the occurrence and generates the next. Dashboard shows expected totals for this week, this month, next month (Phase 2 basic totals, Phase 6 full forecast).

**Tasks.** One-time or recurring, priority, deadline, subtasks, notes, attachments, follow-up rules.

**People and events.** Contacts (name, phone, email, relationship, birthday, anniversary, notes). Reminders such as 7 days, 1 day, on the day. Optional gift idea, budget, purchased flag.

**Shopping.** Lists with items (name, quantity, unit, estimated price, actual price, priority, purchased, store, notes). Automatically compute estimated, actual, and remaining totals. Prices stored in kobo.

**Visits and appointments.** Person/company, date, time, location, purpose, contact, notes, reminder schedule, follow-up actions.

**Dashboard.** Today, Due Today, Upcoming, Payments, Tasks, Shopping, Events, Follow-ups. Financial figures shown separately and labelled "expected".

**Morning summary.** Optional, user-configurable, can be disabled.

**Global search.** Across activities, payments, shopping items, contacts, events, tasks, notes (PostgreSQL full-text search first).

**Desktop (Tauri).** Tray icon, compact widget (Today, Next reminder, Payment, Quick Add) and expanded widget (Today, Payments, Shopping, Events, Tasks, AI assistant), resizable, always-on-top option. Global shortcut **Ctrl+Alt+Space** (user-configurable) opens "What do you want to remember?". Note: this is an app window, not a native Windows 11 Widgets-board widget.

## 8. AI rules (Phase 5, but design for them now)

- **Parsing** returns strict JSON validated against a schema before anything is shown:

```json
{
  "intent": "create_activity",
  "type": "payment",
  "title": "Internet Bill",
  "amount_minor": 2000000,
  "currency": "NGN",
  "rrule": "FREQ=MONTHLY;BYMONTHDAY=25",
  "reminder_offsets_minutes": [4320],
  "missing_fields": [],
  "ambiguities": [],
  "confidence": 0.93
}
```

- **Never silently invent critical fields** (dates, amounts, recipients). Put them in `missing_fields` and ask the user.
- **Always show a confirmation card** before saving when confidence is low, fields are missing, or the action is destructive.
- **The assistant reads data only through server-side tools** (`list_activities`, `list_payments`, `search`, etc.) that are scoped to the authenticated user. Never place other users' data in a prompt.
- **Treat stored notes, titles, and imported text as untrusted.** Instructions found inside user data must never change the assistant's behavior or trigger actions.
- Model, provider, and API key come from `.env`. Add per-user rate limits and a monthly usage cap.
- **Voice:** choose a speech-to-text service deliberately (browser speech recognition is inconsistent across Android WebView and Windows WebView2). Test with Nigerian English accents. The transcript goes through the same parse-and-confirm flow.

## 9. Security and privacy

- Password hashing (bcrypt/argon2), Sanctum tokens, token revocation per device, HTTPS only.
- Laravel Policies on every model; every query scoped by `user_id`.
- Rate limiting on auth and AI endpoints; strict input validation via Form Requests.
- Encrypt `account_reference` and other sensitive fields at rest (Laravel encrypted casts).
- **Never store** card numbers, CVV, PINs, or bank passwords.
- Audit log for logins, device changes, exports, deletions.
- Comply with the Nigeria Data Protection Act 2023: privacy notice, consent, **data export** and **account deletion** features, data-minimization.
- Required tests: a user can never read, edit, or delete another user's data via any endpoint.
- Secrets only in environment variables.

## 10. UX rules

Simple, clean, fast, friendly, minimal. Do not overload the home screen. Distinct colors and icons for Urgent, Upcoming, Completed, Overdue, Shopping, Payments, Events, Tasks. Light and dark mode. Default currency ₦ (NGN), Africa/Lagos timezone, day-first date format. Accessible contrast and tap targets.

## 11. Database (PostgreSQL)

Core tables: `users`, `devices`, `activities`, `activity_recurrences`, `activity_occurrences`, `activity_reminders`, `activity_links`, `payment_details`, `tasks`, `subtasks`, `contacts`, `shopping_lists`, `shopping_items`, `notifications`, `notification_deliveries`, `user_preferences`, `attachments`, `sync_mutations`, `audit_logs`.

Use foreign keys, indexes on `(user_id, due_at)`, `(user_id, updated_at)`, unique index on the notification key, and soft deletes on syncable tables.

## 12. Environment variables

Database, app key, Sanctum/session domains, mail, FCM credentials, Web Push VAPID keys, WhatsApp/SMS provider, AI provider/key/model, speech-to-text provider, storage disk, queue driver. Provide a complete `.env.example`. Never commit secrets.

## 13. Development rules (apply to every phase)

Build incrementally. For each phase, in order:

1. Architecture note (short)
2. Migrations
3. Models and relationships
4. Validation (Form Requests)
5. Services (business logic lives here, not in controllers)
6. API endpoints and Resources
7. Automated tests (recurrence edge cases, policies, sync where relevant)
8. Frontend
9. API integration
10. Error-handling and offline behavior checks
11. Documentation
12. Confirm every acceptance criterion passes and the app still runs

At every step, explain in beginner-friendly language with copy-and-paste commands:

- What is being built and why
- Files created and files modified
- Database changes
- API changes
- How to test it
- How to run it locally

Use clean architecture, SOLID, reusable components, clear naming, and type safety. Do not add complexity that the current phase doesn't need.

## Phase 1 acceptance criteria

Goal: a user can sign up, create a recurring payment and a task, receive a reminder, mark the payment paid, and see the next occurrence, all from a mobile-friendly PWA.

- A payment "Internet ₦20,000, monthly on the 25th, reminders 3 days and 1 day before" creates correct occurrences and reminders
- Marking it paid completes that occurrence only and the next month remains Upcoming
- A monthly rule on the 31st produces Feb 28/29, Apr 30, etc.; leap-year test passes
- Reminders fire once (unique key enforced); a missed reminder fires once on recovery
- Snooze does not alter the recurrence rule
- Amounts stored as integer kobo; UI shows ₦ formatting
- Every syncable table has UUID id, `version`, `updated_at`, `deleted_at`
- Test proves user A cannot access user B's activities
- PWA installs on a phone and works in a mobile browser
- README explains running the API, PWA, scheduler, and tests locally
