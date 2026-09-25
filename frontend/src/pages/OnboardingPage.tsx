import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, ApiError, type User } from '../api'
import { markOnboardingDone } from '../onboarding'
import { enableWebPush, pushSupported } from '../push'

type Preferences = {
  timezone: string
  reminder_time: string
  due_soon_days: number
  currency: string
  theme: string
  morning_summary_enabled: boolean
  morning_summary_time: string
  privacy_notice_accepted_at: string | null
}

const TIMEZONES = [
  'Africa/Lagos',
  'Africa/Accra',
  'Africa/Nairobi',
  'Africa/Johannesburg',
  'UTC',
]

type Step = 1 | 2 | 3

export function OnboardingPage({
  onUser,
  onComplete,
}: {
  onUser?: (user: User) => void
  onComplete?: () => void
}) {
  const navigate = useNavigate()
  const [step, setStep] = useState<Step>(1)
  const [prefs, setPrefs] = useState<Preferences | null>(null)
  const [notice, setNotice] = useState('')
  const [accepted, setAccepted] = useState(false)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const [pushHint, setPushHint] = useState('')

  useEffect(() => {
    void api<{ data: Preferences }>('/api/preferences')
      .then((prefBody) => {
        setPrefs(prefBody.data)
        if (prefBody.data.privacy_notice_accepted_at) {
          setAccepted(true)
        }
      })
      .catch((caught) => setError(caught instanceof ApiError ? caught.message : 'Could not start setup.'))

    void api<{ data: { summary: string } }>('/api/privacy/notice')
      .then((noticeBody) => setNotice(noticeBody.data.summary))
      .catch(() => setNotice('We store your reminders and expected amounts to help you Remember. Plan. Act.'))
  }, [])

  async function savePrefs(patch: Partial<Preferences>) {
    if (!prefs) return prefs
    const next = { ...prefs, ...patch }
    const body = await api<{ data: Preferences; user: User }>('/api/preferences', {
      method: 'PUT',
      body: JSON.stringify(next),
    })
    setPrefs(body.data)
    onUser?.(body.user)
    if (body.data.theme) {
      localStorage.setItem('theme', body.data.theme)
      document.documentElement.dataset.theme = body.data.theme
    }
    return body.data
  }

  async function finishStep1() {
    if (!prefs || !accepted) {
      setError('Accept the privacy notice to continue.')
      return
    }
    setSaving(true)
    setError('')
    try {
      await savePrefs({
        timezone: prefs.timezone || 'Africa/Lagos',
        currency: prefs.currency || 'NGN',
      })
      if (!prefs.privacy_notice_accepted_at) {
        await api('/api/privacy/accept', { method: 'POST' })
      }
      setStep(2)
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not save preferences.')
    } finally {
      setSaving(false)
    }
  }

  async function finishAll() {
    setSaving(true)
    setError('')
    try {
      if (prefs) {
        await savePrefs({
          morning_summary_enabled: prefs.morning_summary_enabled,
          morning_summary_time: prefs.morning_summary_time || '07:30',
          reminder_time: prefs.reminder_time || '09:00',
        })
      }
      markOnboardingDone()
      onComplete?.()
      navigate('/', { replace: true })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not finish setup.')
    } finally {
      setSaving(false)
    }
  }

  async function enableReminders() {
    setPushHint('')
    if (!pushSupported()) {
      setPushHint('This browser cannot use push. Morning summary still works.')
      return
    }
    const result = await enableWebPush()
    if (result === 'granted') setPushHint('Browser reminders enabled.')
    else if (result === 'denied') setPushHint('Permission denied — you can enable later in Settings.')
    else if (result === 'unconfigured') setPushHint('Push keys are not set on the server yet. Morning summary still works.')
    else setPushHint('Push is not supported here.')
  }

  function completeAndGo(path: string) {
    markOnboardingDone()
    onComplete?.()
    navigate(path)
  }

  function skipTour() {
    markOnboardingDone()
    onComplete?.()
    navigate('/', { replace: true })
  }

  if (!prefs) {
    return <p className="center">{error || 'Loading…'}</p>
  }

  return (
    <div className="onboarding-screen">
      <div className="onboarding-top">
        <span className="capture-step-label">Step {step} of 3</span>
        {step > 1 && (
          <button type="button" className="ghost close-capture" onClick={() => skipTour()}>
            Skip
          </button>
        )}
      </div>

      {step === 1 && (
        <section className="onboarding-panel" aria-label="Your defaults">
          <p className="eyebrow">GIT Life</p>
          <h1>Set your home base</h1>
          <p className="capture-lead">Nigerian defaults first — change anytime in Settings.</p>

          <label>Timezone
            <select
              value={prefs.timezone}
              onChange={(event) => setPrefs({ ...prefs, timezone: event.target.value })}
            >
              {TIMEZONES.map((zone) => (
                <option key={zone} value={zone}>{zone}</option>
              ))}
            </select>
          </label>

          <label>Currency
            <select
              value={prefs.currency}
              onChange={(event) => setPrefs({ ...prefs, currency: event.target.value })}
            >
              <option value="NGN">NGN (₦)</option>
              <option value="GHS">GHS</option>
              <option value="KES">KES</option>
              <option value="ZAR">ZAR</option>
              <option value="USD">USD</option>
            </select>
          </label>

          <label>Theme
            <select
              value={prefs.theme}
              onChange={(event) => setPrefs({ ...prefs, theme: event.target.value })}
            >
              <option value="system">System</option>
              <option value="light">Light</option>
              <option value="dark">Dark</option>
            </select>
          </label>

          <div className="privacy-box">
            <p className="muted">{notice || 'We store your reminders and expected amounts to help you Remember. Plan. Act.'}</p>
            <label className="check">
              <input
                type="checkbox"
                checked={accepted}
                onChange={(event) => setAccepted(event.target.checked)}
              />
              I accept the privacy notice
            </label>
          </div>

          {error && <p className="error">{error}</p>}
          <div className="capture-actions">
            <button type="button" className="capture-primary" disabled={saving || !accepted} onClick={() => void finishStep1()}>
              {saving ? 'Saving…' : 'Continue'}
            </button>
          </div>
        </section>
      )}

      {step === 2 && (
        <section className="onboarding-panel" aria-label="First memory">
          <p className="eyebrow">Remember</p>
          <h1>Add your first memory</h1>
          <p className="capture-lead">One payment, birthday, or errand — Capture will keep the rest.</p>

          <div className="onboarding-choices">
            <button type="button" className="choice-card" onClick={() => completeAndGo('/capture')}>
              <strong>Capture in words</strong>
              <span className="muted">“Pay DSTV ₦24,000 on the 15th”</span>
            </button>
            <button type="button" className="choice-card" onClick={() => completeAndGo('/activities/new')}>
              <strong>Add with form</strong>
              <span className="muted">Rent, utilities, or a person event</span>
            </button>
            <button type="button" className="choice-card" onClick={() => completeAndGo('/contacts')}>
              <strong>Add a person</strong>
              <span className="muted">Birthdays and visits start here</span>
            </button>
          </div>

          {error && <p className="error">{error}</p>}
          <div className="capture-actions">
            <button type="button" className="capture-primary" onClick={() => setStep(3)}>
              Continue
            </button>
            <button type="button" className="ghost" onClick={() => setStep(3)}>
              Skip for now
            </button>
          </div>
        </section>
      )}

      {step === 3 && (
        <section className="onboarding-panel" aria-label="Reminders">
          <p className="eyebrow">Plan · Act</p>
          <h1>Stay ahead of due day</h1>
          <p className="capture-lead">Optional — turn on a morning summary and browser reminders.</p>

          <label className="check">
            <input
              type="checkbox"
              checked={prefs.morning_summary_enabled}
              onChange={(event) => setPrefs({ ...prefs, morning_summary_enabled: event.target.checked })}
            />
            Morning summary (expected payments + due today)
          </label>

          {prefs.morning_summary_enabled && (
            <label>Summary time
              <input
                type="time"
                value={prefs.morning_summary_time}
                onChange={(event) => setPrefs({ ...prefs, morning_summary_time: event.target.value })}
              />
            </label>
          )}

          <label>Default reminder time
            <input
              type="time"
              value={prefs.reminder_time}
              onChange={(event) => setPrefs({ ...prefs, reminder_time: event.target.value })}
            />
          </label>

          <div className="capture-actions">
            <button type="button" className="ghost" onClick={() => void enableReminders()}>
              Enable browser reminders
            </button>
          </div>
          {pushHint && <p className="muted">{pushHint}</p>}

          {error && <p className="error">{error}</p>}
          <div className="capture-actions">
            <button type="button" className="capture-primary" disabled={saving} onClick={() => void finishAll()}>
              {saving ? 'Finishing…' : 'Go to Today'}
            </button>
          </div>
        </section>
      )}
    </div>
  )
}
