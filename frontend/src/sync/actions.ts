import { api } from '../api'
import { queueOrFail } from './client'

export async function completeOccurrenceOfflineCapable(occurrenceId: string, asPayment = false): Promise<'queued' | 'synced' | 'ok'> {
  if (!navigator.onLine) {
    return queueOrFail({
      entity: 'occurrence',
      op: 'upsert',
      id: occurrenceId,
      data: { status: asPayment ? 'paid' : 'completed' },
    })
  }

  await api(`/api/occurrences/${occurrenceId}/${asPayment ? 'pay' : 'complete'}`, { method: 'POST' })
  return 'ok'
}

export async function snoozeOccurrenceOfflineCapable(occurrenceId: string, hours = 1): Promise<'queued' | 'synced' | 'ok'> {
  if (!navigator.onLine) {
    return queueOrFail({
      entity: 'occurrence',
      op: 'upsert',
      id: occurrenceId,
      data: { snoozed_until: new Date(Date.now() + hours * 3_600_000).toISOString() },
    })
  }

  await api(`/api/occurrences/${occurrenceId}/snooze`, {
    method: 'POST',
    body: JSON.stringify({
      preset: hours === 1 ? '1hour' : undefined,
      until: hours === 1 ? undefined : new Date(Date.now() + hours * 3_600_000).toISOString(),
    }),
  })
  return 'ok'
}
