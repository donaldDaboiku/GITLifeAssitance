import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../api'
import { detectPlatform, getGlobalShortcut, setGlobalShortcut } from '../platform'

type Device = {
  id: string
  name: string
  type: string
  app_version: string | null
  last_sync_at: string | null
  active: boolean
}

export function DevicesPage() {
  const [devices, setDevices] = useState<Device[]>([])
  const [shortcut, setShortcut] = useState(getGlobalShortcut())
  const [error, setError] = useState('')
  const [saved, setSaved] = useState('')

  async function load() {
    const body = await api<{ data: Device[] }>('/api/devices')
    setDevices(body.data)
  }

  useEffect(() => {
    void load().catch((caught) => setError(caught instanceof ApiError ? caught.message : 'Could not load devices.'))
  }, [])

  async function revoke(id: string) {
    await api(`/api/devices/${id}`, { method: 'DELETE' })
    await load()
  }

  function saveShortcut(event: FormEvent) {
    event.preventDefault()
    setGlobalShortcut(shortcut.trim() || 'Ctrl+Alt+Space')
    setSaved('Shortcut saved. It applies on this Windows session immediately.')
    void window.dispatchEvent(new CustomEvent('gitlife:shortcut-changed', { detail: shortcut.trim() || 'Ctrl+Alt+Space' }))
  }

  return (
    <div className="stack">
      <div className="row">
        <h1>Devices</h1>
        <Link to="/">Home</Link>
      </div>
      <p className="muted">Platform: {detectPlatform()}. Auto-sync runs about every 30 seconds while online.</p>
      {detectPlatform() === 'windows' && (
        <form className="card" onSubmit={saveShortcut}>
          <h2>Global shortcut</h2>
          <p className="muted">Default is Ctrl+Alt+Space. Opens Quick Capture.</p>
          <label>Shortcut
            <input value={shortcut} onChange={(event) => setShortcut(event.target.value)} placeholder="Ctrl+Alt+Space" />
          </label>
          <button type="submit">Save shortcut</button>
          {saved && <p className="muted">{saved}</p>}
        </form>
      )}
      <section className="card">
        {error && <p className="error">{error}</p>}
        <ul className="list">
          {devices.map((device) => (
            <li key={device.id}>
              <strong>{device.name}</strong>
              <span className={`pill ${device.type}`}>{device.type}</span>
              <p className="muted">
                {device.app_version ?? 'no version'}
                {device.last_sync_at ? ` · last sync ${new Date(device.last_sync_at).toLocaleString()}` : ''}
              </p>
              <button type="button" className="danger" onClick={() => void revoke(device.id)}>Revoke</button>
            </li>
          ))}
          {devices.length === 0 && <p className="muted">No registered devices yet.</p>}
        </ul>
      </section>
    </div>
  )
}
