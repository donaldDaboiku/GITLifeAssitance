# Web Push (PWA)

Browser push notifications for reminders when the PWA is closed or in the background.

## Setup

```powershell
cd backend
php artisan webpush:vapid
```

If that fails on Windows PHP OpenSSL, use Node instead:

```powershell
npx --yes web-push generate-vapid-keys
```

Copy the public/private keys into `backend/.env` as `WEBPUSH_VAPID_PUBLIC_KEY` / `WEBPUSH_VAPID_PRIVATE_KEY`, set `WEBPUSH_VAPID_SUBJECT` to your app URL, then restart `php artisan serve`.

HTTPS (or `localhost`) is required for push in browsers.

## Flow

1. Settings → **Enable push** asks for notification permission.
2. Frontend fetches `GET /api/push/vapid-public-key`, subscribes via `PushManager`, then `POST /api/push/subscribe`.
3. Subscription JSON is stored on a `devices` row (`type=web`, `push_token`).
4. `reminders:dispatch` sends through `WebPushChannel` (with in-app + email).
5. `public/sw.js` shows the notification and opens the app on click.

## API

| Endpoint | Purpose |
| --- | --- |
| `GET /api/push/vapid-public-key` | Public VAPID key |
| `POST /api/push/subscribe` | Save subscription |
| `DELETE /api/push/subscribe` | Clear subscription by endpoint |
