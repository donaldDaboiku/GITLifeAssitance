import { api, type Dashboard } from './api'
import { detectPlatform, getAccessToken, getDeviceId, setDeviceId } from './platform'
import { completeOccurrenceOfflineCapable, snoozeOccurrenceOfflineCapable } from './sync/actions'

export async function registerCurrentDevice(options?: {
  name?: string
  pushToken?: string | null
}): Promise<void> {
  if (!getAccessToken() && detectPlatform() === 'web') {
    return
  }

  const platform = detectPlatform()
  const existing = getDeviceId()
  if (existing && options?.pushToken) {
    await api(`/api/devices/${existing}`, {
      method: 'PUT',
      body: JSON.stringify({
        push_token: options.pushToken,
        last_sync_at: true,
        app_version: import.meta.env.VITE_APP_VERSION ?? '0.3.0',
      }),
    })
    return
  }

  if (existing) {
    await api(`/api/devices/${existing}`, {
      method: 'PUT',
      body: JSON.stringify({ last_sync_at: true }),
    }).catch(() => undefined)
    return
  }

  const response = await api<{ device: { id: string }; token?: string }>('/api/devices', {
    method: 'POST',
    body: JSON.stringify({
      name: options?.name ?? `${platform} device`,
      type: platform === 'web' ? 'web' : platform,
      app_version: import.meta.env.VITE_APP_VERSION ?? '0.3.0',
      push_token: options?.pushToken ?? null,
    }),
  })
  setDeviceId(response.device.id)
}

export async function applyNotificationAction(action: string, occurrenceId: string): Promise<void> {
  if (action === 'snooze' || action === 'Snooze') {
    await snoozeOccurrenceOfflineCapable(occurrenceId, 1)
    return
  }
  const asPayment = action === 'mark_paid' || action === 'Mark Paid'
  await completeOccurrenceOfflineCapable(occurrenceId, asPayment)
}

export async function fetchWidgetSummary(): Promise<Dashboard> {
  return api<Dashboard>('/api/dashboard')
}
