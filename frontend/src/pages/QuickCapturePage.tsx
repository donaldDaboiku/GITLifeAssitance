import { useNavigate } from 'react-router-dom'
import { useEffect, useState, type FormEvent } from 'react'
import { api, ApiError } from '../api'

export function QuickCapturePage() {
  const navigate = useNavigate()
  const [text, setText] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        navigate('/')
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [navigate])

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (!text.trim()) return
    setSaving(true)
    setError('')
    try {
      const today = new Date().toISOString().slice(0, 10)
      const looksLikePayment = /₦|\bnaira\b|\bpay\b|\bbill\b/i.test(text)
      const response = await api<{ data: { id: string } }>('/api/activities', {
        method: 'POST',
        body: JSON.stringify({
          type: looksLikePayment ? 'payment' : 'task',
          title: text.trim().slice(0, 255),
          due_on: today,
          timezone: 'Africa/Lagos',
          reminder_offsets_minutes: [0],
          payment: looksLikePayment
            ? { amount_minor: 0, currency: 'NGN' }
            : undefined,
          notes: 'Captured from Quick Capture. Refine details if needed.',
        }),
      })
      navigate(`/activities/${response.data.id}/edit`)
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not save.')
      setSaving(false)
    }
  }

  return (
    <form className="card narrow" onSubmit={(event) => void submit(event)}>
      <h1>What do you want to remember?</h1>
      <p className="muted">Opens with Ctrl+Alt+Space on Windows. Esc closes.</p>
      <label>
        Capture
        <textarea
          autoFocus
          rows={4}
          value={text}
          onChange={(event) => setText(event.target.value)}
          placeholder="Pay internet bill, call Ada, buy toner…"
          required
        />
      </label>
      {error && <p className="error">{error}</p>}
      <div className="actions">
        <button type="submit" disabled={saving}>{saving ? 'Saving…' : 'Remember'}</button>
        <button type="button" onClick={() => navigate('/')}>Cancel</button>
      </div>
    </form>
  )
}
