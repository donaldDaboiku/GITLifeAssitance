# Privacy — NDPA export and account deletion

Supports the Nigeria Data Protection Act 2023 obligations for access and erasure.

## Features

1. **Privacy notice** — short in-app notice; acceptance is stored on preferences.
2. **Data export** — `GET /api/privacy/export` downloads JSON for the signed-in user only (no passwords or API tokens).
3. **Account deletion** — `DELETE /api/privacy/account` with password + confirmation permanently erases the user (cascade) and revokes tokens.

## API

| Endpoint | Purpose |
| --- | --- |
| `GET /api/privacy/notice` | Notice text |
| `POST /api/privacy/accept` | Record consent timestamp |
| `GET /api/privacy/export` | Stream JSON export |
| `DELETE /api/privacy/account` | `{ password, confirm: true }` |

## UI

Settings → Privacy (NDPA): accept notice, download export, delete account.
