export type PlatformKind = 'web' | 'windows' | 'android'

const TOKEN_KEY = 'gitlife_access_token'
const DEVICE_KEY = 'gitlife_device_id'
const SHORTCUT_KEY = 'gitlife_global_shortcut'
const WIDGET_MODE_KEY = 'gitlife_widget_mode'

export function detectPlatform(): PlatformKind {
  const forced = import.meta.env.VITE_PLATFORM as PlatformKind | undefined
  if (forced === 'windows' || forced === 'android' || forced === 'web') {
    return forced
  }

  if (typeof window !== 'undefined') {
    if ('__TAURI_INTERNALS__' in window || '__TAURI__' in window) {
      return 'windows'
    }
    const capacitor = (window as Window & { Capacitor?: { isNativePlatform?: () => boolean; getPlatform?: () => string } }).Capacitor
    if (capacitor?.isNativePlatform?.() || capacitor?.getPlatform?.() === 'android') {
      return 'android'
    }
  }

  return 'web'
}

export function isNativePlatform(): boolean {
  return detectPlatform() !== 'web'
}

export function getAccessToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setAccessToken(token: string | null): void {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

export function getDeviceId(): string | null {
  return localStorage.getItem(DEVICE_KEY)
}

export function setDeviceId(id: string | null): void {
  if (id) {
    localStorage.setItem(DEVICE_KEY, id)
  } else {
    localStorage.removeItem(DEVICE_KEY)
  }
}

export function getGlobalShortcut(): string {
  return localStorage.getItem(SHORTCUT_KEY) ?? 'Ctrl+Alt+Space'
}

export function setGlobalShortcut(shortcut: string): void {
  localStorage.setItem(SHORTCUT_KEY, shortcut)
}

export type WidgetMode = 'compact' | 'expanded'

export function getWidgetMode(): WidgetMode {
  const value = localStorage.getItem(WIDGET_MODE_KEY)
  return value === 'expanded' ? 'expanded' : 'compact'
}

export function setWidgetMode(mode: WidgetMode): void {
  localStorage.setItem(WIDGET_MODE_KEY, mode)
}

export function clearNativeSession(): void {
  setAccessToken(null)
  setDeviceId(null)
  try {
    localStorage.removeItem('gitlife_sync_queue')
    localStorage.removeItem('gitlife_sync_cursor')
  } catch {
    // ignore
  }
}
