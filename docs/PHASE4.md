# Phase 4 — sync engine

Auto-sync keeps the same account consistent across web, Windows, and Android.

## How it works

1. **Push** — the client sends a batch of mutations, each with a unique `client_mutation_id`. Retries are idempotent.
2. **Pull** — the client sends its last cursor; the server returns changes (including tombstones) after that cursor.
3. **Conflicts** — last-write-wins on the server clock. A `completed` / `paid` occurrence is not reopened to `pending` unless `reopen: true`.
4. **Offline queue** — Done / Mark paid / Snooze (and notification actions) enqueue in `localStorage` when offline, then flush with exponential backoff when back online. A sync loop also runs about every 30 seconds while signed in.

## API

| Endpoint | Body | Result |
| --- | --- | --- |
| `POST /api/sync/push` | `{ device_id?, mutations[] }` | `{ results[] }` |
| `POST /api/sync/pull` | `{ device_id?, cursor?, limit? }` | `{ changes[], next_cursor, has_more }` |

Synced entities: `activity`, `occurrence`, `contact`, `shopping_list`, `shopping_item`.

## Frontend

| File | Role |
| --- | --- |
| `src/sync/queue.ts` | Offline mutation queue + cursor |
| `src/sync/client.ts` | Push/pull loop |
| `src/sync/actions.ts` | Offline-capable occurrence actions |

## Acceptance

- [ ] Change on device A appears on device B after auto-sync (no manual refresh required once pull completes)
- [ ] Offline Done / Mark paid / Snooze queues and syncs after reconnect
- [ ] Duplicate `client_mutation_id` does not double-apply
- [ ] Completed occurrence stays completed if an older pending edit arrives without `reopen`
- [ ] Soft-deleted rows appear as pull tombstones (`deleted_at` set, `data` null)
