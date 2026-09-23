import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, ApiError, type User } from '../api'
import { clearNativeSession, getAccessToken } from '../platform'
import { clearSyncState } from '../sync/queue'

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

export function SettingsPage({ onUser }: { onUser?: (user: User | null) => void }) {
  const navigate = useNavigate()
  const [prefs, setPrefs] = useState<Preferences | null>(null)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [saved, setSaved] = useState('')
  const [saving, setSaving] = useState(false)
  const [password, setPassword] = useState('')
  const [confirmDelete, setConfirmDelete] = useState(false)

  useEffect(() => {
    void Promise.all([
      api<{ data: Preferences }>('/api/preferences'),
      api<{ data: { summary: string } }>('/api/privacy/notice'),
    ])
      .then(([prefBody, noticeBody]) => {
        setPrefs(prefBody.data)
        setNotice(noticeBody.data.summary)
      })
      .catch((caught) => setError(caught instanceof ApiError ? caught.message : 'Could not load settings.'))
  }, [])

  async function save(event: FormEvent) {
    event.preventDefault()
    if (!prefs) return
    setSaving(true)
    setError('')
    setSaved('')
    try {
      const body = await api<{ data: Preferences; user: User }>('/api/preferences', {
        method: 'PUT',
        body: JSON.stringify(prefs),
      })
      setPrefs(body.data)
      onUser?.(body.user)
      if (body.data.theme) {
        document.documentElement.dataset.theme = body.data.theme === 'system' ? '' : body.data.theme
        localStorage.setItem('theme', body.data.theme)
      }
      setSaved('Settings saved.')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not save settings.')
    } finally {
      setSaving(false)
    }
  }

  async function acceptNotice() {
    await api('/api/privacy/accept', { method: 'POST' })
    const body = await api<{ data: Preferences }>('/api/preferences')
    setPrefs(body.data)
    setSaved('Privacy notice accepted.')
  }

  async function downloadExport() {
    setError('')
    try {
      const API = import.meta.env.VITE_API_URL ?? ''
      const token = getAccessToken()
      const headers: HeadersInit = { Accept: 'application/json' }
      if (token) {
        headers.Authorization = `Bearer ${token}`
      }
      const response = await fetch(`${API}/api/privacy/export`, {
        headers,
        credentials: token ? 'omit' : 'include',
      })
      if (!response.ok) {
        throw new Error('Export failed.')
      }
      const blob = await response.blob()
      const url = URL.createObjectURL(blob)
      const anchor = document.createElement('a')
      anchor.href = url
      anchor.download = 'gitlife-data-export.json'
      anchor.click()
      URL.revokeObjectURL(url)
      setSaved('Export downloaded.')
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Could not export data.')
    }
  }

  async function deleteAccount() {
    setError('')
    try {
      await api('/api/privacy/account', {
        method: 'DELETE',
        body: JSON.stringify({ password, confirm: confirmDelete }),
      })
      clearNativeSession()
      clearSyncState()
      onUser?.(null)
      navigate('/login')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not delete account.')
    }
  }

  if (!prefs) {
    return <p className="center">{error || 'Loading…'}</p>
  }

  return (
    <div className="stack">
      <div className="row">
        <h1>Settings</h1>
        <Link to="/">Home</Link>
      </div>
      <form className="card narrow" onSubmit={(event) => void save(event)}>
        <h2>Personalization</h2>
        <label>Timezone
          <input value={prefs.timezone} onChange={(event) => setPrefs({ ...prefs, timezone: event.target.value })} />
        </label>
        <label>Reminder time
          <input type="time" value={prefs.reminder_time} onChange={(event) => setPrefs({ ...prefs, reminder_time: event.target.value })} />
        </label>
        <label>Due soon (days)
          <input
            type="number"
            min={0}
            max={30}
            value={prefs.due_soon_days}
            onChange={(event) => setPrefs({ ...prefs, due_soon_days: Number(event.target.value) })}
          />
        </label>
        <label>Currency
          <input value={prefs.currency} onChange={(event) => setPrefs({ ...prefs, currency: event.target.value.toUpperCase() })} maxLength={3} />
        </label>
        <label>Theme
          <select value={prefs.theme} onChange={(event) => setPrefs({ ...prefs, theme: event.target.value })}>
            <option value="system">System</option>
            <option value="light">Light</option>
            <option value="dark">Dark</option>
          </select>
        </label>
        <h2>Morning summary</h2>
        <p className="muted">Optional. Delivers at your local time and stops when disabled.</p>
        <label className="check">
          <input
            type="checkbox"
            checked={prefs.morning_summary_enabled}
            onChange={(event) => setPrefs({ ...prefs, morning_summary_enabled: event.target.checked })}
          />
          Enable morning summary
        </label>
        <label>Summary time
          <input
            type="time"
            value={prefs.morning_summary_time}
            onChange={(event) => setPrefs({ ...prefs, morning_summary_time: event.target.value })}
            disabled={!prefs.morning_summary_enabled}
          />
        </label>
        {error && <p className="error">{error}</p>}
        {saved && <p className="muted">{saved}</p>}
        <button type="submit" disabled={saving}>{saving ? 'Saving…' : 'Save settings'}</button>
      </form>

      <section className="card narrow">
        <h2>Privacy (NDPA)</h2>
        <p className="muted">{notice}</p>
        {prefs.privacy_notice_accepted_at
          ? <p className="muted">Accepted {new Date(prefs.privacy_notice_accepted_at).toLocaleString()}.</p>
          : <button type="button" onClick={() => void acceptNotice()}>Accept privacy notice</button>}
        <div className="actions">
          <button type="button" onClick={() => void downloadExport()}>Download my data</button>
        </div>
        <h3>Delete account</h3>
        <p className="muted">Permanently erases your account and personal data. This cannot be undone.</p>
        <label>Password
          <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="current-password" />
        </label>
        <label className="check">
          <input type="checkbox" checked={confirmDelete} onChange={(event) => setConfirmDelete(event.target.checked)} />
          I understand this permanently deletes my account
        </label>
        <button type="button" className="danger" disabled={!password || !confirmDelete} onClick={() => void deleteAccount()}>
          Delete my account
        </button>
      </section>
    </div>
  )
}
