import { api } from './api'

function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(base64)
  const output = new Uint8Array(raw.length)
  for (let i = 0; i < raw.length; i += 1) {
    output[i] = raw.charCodeAt(i)
  }
  return output
}

export function pushSupported(): boolean {
  return typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window
}

export async function enableWebPush(): Promise<'granted' | 'denied' | 'unsupported' | 'unconfigured'> {
  if (!pushSupported()) {
    return 'unsupported'
  }

  const permission = await Notification.requestPermission()
  if (permission !== 'granted') {
    return 'denied'
  }

  let publicKey: string
  try {
    const body = await api<{ public_key: string }>('/api/push/vapid-public-key')
    publicKey = body.public_key
  } catch {
    return 'unconfigured'
  }

  const registration = await navigator.serviceWorker.ready
  const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(publicKey) as BufferSource,
  })

  await api('/api/push/subscribe', {
    method: 'POST',
    body: JSON.stringify({
      name: 'Web browser',
      subscription: subscription.toJSON(),
    }),
  })

  return 'granted'
}

export async function disableWebPush(): Promise<void> {
  if (!pushSupported()) return
  const registration = await navigator.serviceWorker.ready
  const subscription = await registration.pushManager.getSubscription()
  if (!subscription) return

  await api('/api/push/subscribe', {
    method: 'DELETE',
    body: JSON.stringify({ endpoint: subscription.endpoint }),
  }).catch(() => undefined)

  await subscription.unsubscribe()
}
