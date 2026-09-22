# Phase 3 architecture

Same React app runs in three shells:

1. **Web/PWA** — Vite in the browser (Phase 1).
2. **Windows** — Tauri 2 wraps the built frontend. Extra native pieces: system tray, compact and expanded widget windows, global shortcut **Ctrl+Alt+Space** (configurable), and Windows toast notifications.
3. **Android** — Capacitor wraps the same build. Extra native pieces: local notifications with actions Open / Done / Mark Paid / Snooze, and FCM token registration on the device row.

Native shells authenticate with Sanctum **personal access tokens** (`POST /api/token-login`), then register a `devices` row. The web SPA keeps cookie auth. Manual refresh still syncs data across platforms until Phase 4 auto-sync.

## Backend

| Endpoint | Purpose |
| --- | --- |
| `POST /api/token-login` | Email/password → bearer token + device row |
| `GET/POST /api/devices` | List or register devices |
| `PUT /api/devices/{id}` | Update push token / last sync |
| `DELETE /api/devices/{id}` | Revoke device + token |

## Frontend shell entry points

| File | Role |
| --- | --- |
| `src/platform.ts` | Detect web / windows / android; store token, device id, shortcut |
| `src/native.ts` | Device registration + notification action helpers |
| `src/windowsShell.ts` | Tray, Ctrl+Alt+Space, desktop notifications |
| `src/androidShell.ts` | Local notification actions + FCM registration |
| `/capture` | Quick Capture (“What do you want to remember?”) |
| `/widget` | Compact / expanded desktop widget UI |
| `/devices` | Device list + shortcut settings |

## Commands

```powershell
# Windows desk shell (API must be running)
cd frontend
npm run tauri:dev

# Android (needs Android Studio / SDK)
cd frontend
npm run build
npx cap sync android
npm run cap:open
```

Set `VITE_API_URL` to your LAN API URL for physical devices (for example `http://192.168.x.x:8000`).

## Acceptance checklist

- [ ] Windows tray Show / Quick Capture / Widget / Quit
- [ ] Ctrl+Alt+Space opens Quick Capture (changeable under Devices)
- [ ] Compact and expanded widget routes load dashboard data
- [ ] Android local notification actions: Open, Done, Mark Paid, Snooze
- [ ] Android FCM token stored on `devices.push_token`
- [ ] Token login works without cookies; web login still uses cookies
- [ ] Manual refresh on one platform shows changes made on another
