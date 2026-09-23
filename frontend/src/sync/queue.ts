export type SyncEntity = 'activity' | 'occurrence' | 'contact' | 'shopping_list' | 'shopping_item'
export type SyncOp = 'upsert' | 'delete'

export type SyncMutation = {
  client_mutation_id: string
  entity: SyncEntity
  op: SyncOp
  id: string
  data?: Record<string, unknown>
  reopen?: boolean
  queued_at: string
  attempts: number
  next_attempt_at: number
}

const QUEUE_KEY = 'gitlife_sync_queue'
const CURSOR_KEY = 'gitlife_sync_cursor'

function readQueue(): SyncMutation[] {
  try {
    const raw = localStorage.getItem(QUEUE_KEY)
    return raw ? (JSON.parse(raw) as SyncMutation[]) : []
  } catch {
    return []
  }
}

function writeQueue(queue: SyncMutation[]): void {
  localStorage.setItem(QUEUE_KEY, JSON.stringify(queue))
  window.dispatchEvent(new CustomEvent('gitlife:queue-changed', { detail: queue.length }))
}

export function queueLength(): number {
  return readQueue().length
}

export function getSyncCursor(): string | null {
  return localStorage.getItem(CURSOR_KEY)
}

export function setSyncCursor(cursor: string | null): void {
  if (cursor) {
    localStorage.setItem(CURSOR_KEY, cursor)
  } else {
    localStorage.removeItem(CURSOR_KEY)
  }
}

export function enqueueMutation(input: Omit<SyncMutation, 'queued_at' | 'attempts' | 'next_attempt_at' | 'client_mutation_id'> & {
  client_mutation_id?: string
}): SyncMutation {
  const mutation: SyncMutation = {
    client_mutation_id: input.client_mutation_id ?? crypto.randomUUID(),
    entity: input.entity,
    op: input.op,
    id: input.id,
    data: input.data,
    reopen: input.reopen,
    queued_at: new Date().toISOString(),
    attempts: 0,
    next_attempt_at: Date.now(),
  }
  const queue = readQueue()
  queue.push(mutation)
  writeQueue(queue)
  return mutation
}

export function pendingMutations(now = Date.now()): SyncMutation[] {
  return readQueue().filter((item) => item.next_attempt_at <= now)
}

export function removeMutations(ids: string[]): void {
  writeQueue(readQueue().filter((item) => !ids.includes(item.client_mutation_id)))
}

/** Exponential backoff: 2s, 4s, 8s… capped at 5 minutes. */
export function deferMutations(ids: string[]): void {
  const queue = readQueue().map((item) => {
    if (!ids.includes(item.client_mutation_id)) {
      return item
    }
    const attempts = item.attempts + 1
    const delay = Math.min(300_000, 2000 * 2 ** Math.min(attempts, 8))
    return {
      ...item,
      attempts,
      next_attempt_at: Date.now() + delay,
    }
  })
  writeQueue(queue)
}

export function clearSyncState(): void {
  localStorage.removeItem(QUEUE_KEY)
  localStorage.removeItem(CURSOR_KEY)
  window.dispatchEvent(new CustomEvent('gitlife:queue-changed', { detail: 0 }))
}
