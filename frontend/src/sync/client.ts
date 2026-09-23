import { api } from '../api'
import { getDeviceId } from '../platform'
import {
  deferMutations,
  enqueueMutation,
  getSyncCursor,
  pendingMutations,
  removeMutations,
  setSyncCursor,
  type SyncEntity,
  type SyncMutation,
  type SyncOp,
} from './queue'

type PushResult = {
  client_mutation_id: string
  status: string
}

type PullResponse = {
  changes: Array<{
    entity: string
    id: string
    version: number
    updated_at: string
    deleted_at: string | null
    data: Record<string, unknown> | null
  }>
  next_cursor: string | null
  has_more: boolean
}

let syncing = false

export async function queueOrFail(
  mutation: {
    entity: SyncEntity
    op: SyncOp
    id: string
    data?: Record<string, unknown>
    reopen?: boolean
  },
): Promise<'queued' | 'synced'> {
  if (!navigator.onLine) {
    enqueueMutation(mutation)
    return 'queued'
  }

  try {
    await pushMutations([{
      ...mutation,
      client_mutation_id: crypto.randomUUID(),
      queued_at: new Date().toISOString(),
      attempts: 0,
      next_attempt_at: Date.now(),
    }])
    return 'synced'
  } catch {
    enqueueMutation(mutation)
    return 'queued'
  }
}

export async function runSync(): Promise<{ pushed: number; pulled: number }> {
  if (syncing || !navigator.onLine) {
    return { pushed: 0, pulled: 0 }
  }

  syncing = true
  try {
    const ready = pendingMutations()
    let pushed = 0
    if (ready.length > 0) {
      pushed = await pushMutations(ready)
    }

    let pulled = 0
    let hasMore = true
    let guard = 0
    while (hasMore && guard < 20) {
      guard += 1
      const page = await pullPage()
      pulled += page.changes.length
      hasMore = page.has_more
      if (page.next_cursor) {
        setSyncCursor(page.next_cursor)
      }
      if (!page.has_more || page.changes.length === 0) {
        break
      }
    }

    window.dispatchEvent(new CustomEvent('gitlife:synced', { detail: { pushed, pulled } }))
    return { pushed, pulled }
  } finally {
    syncing = false
  }
}

async function pushMutations(mutations: SyncMutation[]): Promise<number> {
  const body = {
    device_id: getDeviceId(),
    mutations: mutations.map(({ client_mutation_id, entity, op, id, data, reopen }) => ({
      client_mutation_id,
      entity,
      op,
      id,
      data,
      reopen,
    })),
  }

  try {
    const response = await api<{ results: PushResult[] }>('/api/sync/push', {
      method: 'POST',
      body: JSON.stringify(body),
      // ponytail: bypass offline short-circuit inside api when flushing the queue
      headers: { 'X-GitLife-Sync': '1' },
    })
    const done = response.results
      .filter((result) => ['applied', 'duplicate', 'rejected'].includes(result.status))
      .map((result) => result.client_mutation_id)
    removeMutations(done)
    return done.length
  } catch {
    deferMutations(mutations.map((item) => item.client_mutation_id))
    throw new Error('sync push failed')
  }
}

async function pullPage(): Promise<PullResponse> {
  return api<PullResponse>('/api/sync/pull', {
    method: 'POST',
    body: JSON.stringify({
      device_id: getDeviceId(),
      cursor: getSyncCursor(),
      limit: 100,
    }),
    headers: { 'X-GitLife-Sync': '1' },
  })
}

export function startSyncLoop(intervalMs = 30_000): () => void {
  const tick = () => {
    void runSync().catch(() => undefined)
  }

  tick()
  const timer = window.setInterval(tick, intervalMs)
  const onOnline = () => tick()
  window.addEventListener('online', onOnline)

  return () => {
    window.clearInterval(timer)
    window.removeEventListener('online', onOnline)
  }
}
