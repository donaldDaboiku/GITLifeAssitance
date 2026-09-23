# Phase 6 — Smart summaries, forecast, and follow-ups

## Build

1. **Morning summary** — optional, user-chosen local time, stops when disabled.
2. **Full money forecast** — this week, this month, next month (all labelled expected/planned).
3. **Smart follow-up suggestions** — shown for confirmation; never auto-created.
4. **Personalization settings** — timezone, reminder time, due-soon window, theme, morning summary.

## API

| Endpoint | Purpose |
| --- | --- |
| `GET/PUT /api/preferences` | Read/update personalization |
| `GET /api/suggestions` | List follow-up suggestions |
| `POST /api/suggestions/confirm` | Create from a confirmed suggestion |
| Dashboard `expected_payments.next_month` | Next-month expected total |

## Scheduler

```powershell
php artisan schedule:work
```

Runs `reminders:dispatch` and `summaries:morning` every minute.

## Acceptance

- [x] Summary delivers at the user's chosen time and stops when disabled
- [x] Forecast totals match the sum of pending payment occurrences in each period
- [x] Follow-up suggestions are shown for confirmation, never auto-created silently
