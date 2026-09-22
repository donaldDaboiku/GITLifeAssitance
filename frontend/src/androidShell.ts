import { applyNotificationAction, registerCurrentDevice } from './native'

export async function initAndroidShell(navigate: (path: string) => void): Promise<void> {
  try {
    const { LocalNotifications } = await import('@capacitor/local-notifications')
    const { PushNotifications } = await import('@capacitor/push-notifications')
    const { App } = await import('@capacitor/app')

    await LocalNotifications.requestPermissions()
    await LocalNotifications.registerActionTypes({
      types: [
        {
          id: 'GITLIFE_REMINDER',
          actions: [
            { id: 'open', title: 'Open' },
            { id: 'complete', title: 'Done' },
            { id: 'mark_paid', title: 'Mark Paid' },
            { id: 'snooze', title: 'Snooze' },
          ],
        },
      ],
    })

    await LocalNotifications.addListener('localNotificationActionPerformed', async (event) => {
      const occurrenceId = String(event.notification.extra?.occurrence_id ?? '')
      const actionId = event.actionId
      if (!occurrenceId) {
        navigate('/')
        return
      }
      if (actionId === 'open' || actionId === 'tap') {
        navigate(`/activities/${String(event.notification.extra?.activity_id ?? '')}`)
        return
      }
      await applyNotificationAction(actionId, occurrenceId)
      navigate('/')
    })

    await PushNotifications.requestPermissions()
    await PushNotifications.register()
    await PushNotifications.addListener('registration', (token) => {
      void registerCurrentDevice({
        name: 'Android phone',
        pushToken: token.value,
      })
    })

    await App.addListener('appUrlOpen', ({ url }) => {
      if (url.includes('occurrence_id=')) {
        const parsed = new URL(url)
        const occurrenceId = parsed.searchParams.get('occurrence_id')
        const action = parsed.searchParams.get('action')
        if (occurrenceId && action) {
          void applyNotificationAction(action, occurrenceId).then(() => navigate('/'))
        }
      }
    })
  } catch (error) {
    console.warn('Android shell init skipped', error)
  }
}

export async function scheduleAndroidReminder(input: {
  id: number
  title: string
  body: string
  at: Date
  occurrenceId: string
  activityId: string
}): Promise<void> {
  const { LocalNotifications } = await import('@capacitor/local-notifications')
  await LocalNotifications.schedule({
    notifications: [
      {
        id: input.id,
        title: input.title,
        body: input.body,
        schedule: { at: input.at },
        actionTypeId: 'GITLIFE_REMINDER',
        extra: {
          occurrence_id: input.occurrenceId,
          activity_id: input.activityId,
        },
      },
    ],
  })
}
