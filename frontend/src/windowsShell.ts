import { applyNotificationAction } from './native'
import { getGlobalShortcut } from './platform'

type Unlisten = () => void

export async function initWindowsShell(navigate: (path: string) => void): Promise<Unlisten | undefined> {
  try {
    const { getCurrentWindow } = await import('@tauri-apps/api/window')
    const { TrayIcon } = await import('@tauri-apps/api/tray')
    const { Menu } = await import('@tauri-apps/api/menu')
    const { defaultWindowIcon } = await import('@tauri-apps/api/app')
    const { register, unregister } = await import('@tauri-apps/plugin-global-shortcut')
    const { isPermissionGranted, requestPermission, sendNotification } = await import('@tauri-apps/plugin-notification')

    const window = getCurrentWindow()
    const icon = await defaultWindowIcon()

    const menu = await Menu.new({
      items: [
        {
          id: 'show',
          text: 'Show GIT Life',
          action: () => {
            void window.show()
            void window.setFocus()
          },
        },
        {
          id: 'capture',
          text: 'Quick Capture',
          action: () => {
            navigate('/capture')
            void window.show()
            void window.setFocus()
          },
        },
        {
          id: 'widget',
          text: 'Widget',
          action: () => {
            navigate('/widget')
            void window.show()
            void window.setFocus()
          },
        },
        {
          id: 'quit',
          text: 'Quit',
          action: () => {
            void window.close()
          },
        },
      ],
    })

    await TrayIcon.new({
      icon: icon ?? undefined,
      tooltip: 'GIT Life Assistant',
      menu,
      menuOnLeftClick: false,
      action: (event) => {
        if (event.type === 'Click' && event.button === 'Left') {
          void window.show()
          void window.setFocus()
        }
      },
    })

    let granted = await isPermissionGranted()
    if (!granted) {
      granted = (await requestPermission()) === 'granted'
    }
    if (granted) {
      sendNotification({
        title: 'GIT Life',
        body: 'Desktop notifications are ready.',
      })
    }

    const openCapture = () => {
      navigate('/capture')
      void window.show()
      void window.setFocus()
    }

    async function bindShortcut(shortcut: string) {
      try {
        await unregister(shortcut)
      } catch {
        // first bind
      }
      await register(shortcut, (event) => {
        if (event.state === 'Pressed') {
          openCapture()
        }
      })
    }

    const shortcut = getGlobalShortcut()
    await bindShortcut(shortcut)

    const onShortcutChanged = (event: Event) => {
      const detail = (event as CustomEvent<string>).detail
      void bindShortcut(detail)
    }
    globalThis.window.addEventListener('gitlife:shortcut-changed', onShortcutChanged as EventListener)

    return () => {
      globalThis.window.removeEventListener('gitlife:shortcut-changed', onShortcutChanged as EventListener)
      void unregister(getGlobalShortcut()).catch(() => undefined)
    }
  } catch (error) {
    console.warn('Windows shell init skipped', error)
    return undefined
  }
}

export async function showWindowsNotification(title: string, body: string): Promise<void> {
  try {
    const { isPermissionGranted, requestPermission, sendNotification } = await import('@tauri-apps/plugin-notification')
    let granted = await isPermissionGranted()
    if (!granted) {
      granted = (await requestPermission()) === 'granted'
    }
    if (granted) {
      sendNotification({ title, body })
    }
  } catch {
    // optional on web
  }
}

export async function handleDeepLinkAction(url: string): Promise<void> {
  const parsed = new URL(url)
  const occurrenceId = parsed.searchParams.get('occurrence_id')
  const action = parsed.searchParams.get('action')
  if (occurrenceId && action) {
    await applyNotificationAction(action, occurrenceId)
  }
}
