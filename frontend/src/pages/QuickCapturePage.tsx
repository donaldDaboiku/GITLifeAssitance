import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, ApiError, nairaToKobo, koboToNairaInput } from '../api'

type Proposal = {
  intent: string
  type: string | null
  title: string | null
  amount_minor: number | null
  currency: string | null
  due_on: string | null
  rrule: string | null
  reminder_offsets_minutes: number[] | null
  missing_fields: string[]
  ambiguities: string[]
  confidence: number
  notes: string | null
  requires_confirmation: boolean
}

export function QuickCapturePage() {
  const navigate = useNavigate()
  const [text, setText] = useState('')
  const [proposal, setProposal] = useState<Proposal | null>(null)
  const [amountInput, setAmountInput] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [recording, setRecording] = useState(false)
  const [voiceHint, setVoiceHint] = useState('')
  const mediaRef = useRef<MediaRecorder | null>(null)
  const chunksRef = useRef<Blob[]>([])

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        navigate('/')
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [navigate])

  async function parseText(value: string) {
    setSaving(true)
    setError('')
    try {
      const body = await api<{ proposal: Proposal }>('/api/assistant/parse', {
        method: 'POST',
        body: JSON.stringify({ text: value }),
      })
      setProposal(body.proposal)
      setAmountInput(body.proposal.amount_minor != null ? koboToNairaInput(body.proposal.amount_minor) : '')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not parse.')
    } finally {
      setSaving(false)
    }
  }

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (!text.trim()) return
    await parseText(text.trim())
  }

  async function confirm() {
    if (!proposal) return
    setSaving(true)
    setError('')
    try {
      const next: Proposal = {
        ...proposal,
        title: proposal.title?.trim() || text.trim(),
        amount_minor: amountInput.trim()
          ? nairaToKobo(amountInput)
          : proposal.amount_minor,
        missing_fields: proposal.missing_fields.filter((field) => {
          if (field === 'amount_minor' && amountInput.trim()) return false
          if (field === 'title' && (proposal.title || text.trim())) return false
          return true
        }),
      }
      if (Number.isNaN(next.amount_minor as number)) {
        setError('Enter a valid amount in naira.')
        setSaving(false)
        return
      }
      const response = await api<{ data: { id: string } }>('/api/assistant/confirm', {
        method: 'POST',
        body: JSON.stringify({ proposal: next }),
      })
      navigate(`/activities/${response.data.id}`)
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not save.')
      setSaving(false)
    }
  }

  async function toggleVoice() {
    setVoiceHint('')
    setError('')
    if (recording && mediaRef.current) {
      mediaRef.current.stop()
      setRecording(false)
      return
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const recorder = new MediaRecorder(stream)
      chunksRef.current = []
      recorder.ondataavailable = (event) => {
        if (event.data.size > 0) chunksRef.current.push(event.data)
      }
      recorder.onstop = () => {
        void (async () => {
          stream.getTracks().forEach((track) => track.stop())
          const blob = new Blob(chunksRef.current, { type: 'audio/webm' })
          const form = new FormData()
          form.append('audio', blob, 'capture.webm')
          try {
            const token = localStorage.getItem('gitlife_access_token')
            const headers: HeadersInit = { Accept: 'application/json' }
            if (token) headers.Authorization = `Bearer ${token}`
            const response = await fetch(`${import.meta.env.VITE_API_URL ?? ''}/api/assistant/transcribe`, {
              method: 'POST',
              headers,
              credentials: token ? 'omit' : 'include',
              body: form,
            })
            const body = await response.json().catch(() => ({}))
            if (!response.ok) {
              setVoiceHint(body.message ?? 'Voice needs SPEECH_TO_TEXT_PROVIDER=openai in the API .env.')
              return
            }
            setText(body.text)
            await parseText(body.text)
          } catch {
            setVoiceHint('Could not reach speech-to-text. Configure Whisper in .env.')
          }
        })()
      }
      mediaRef.current = recorder
      recorder.start()
      setRecording(true)
    } catch {
      setVoiceHint('Microphone permission is required for voice capture.')
    }
  }

  return (
    <div className="stack narrow-wrap">
      <form className="card narrow" onSubmit={(event) => void submit(event)}>
        <h1>What do you want to remember?</h1>
        <p className="muted">Ctrl+Alt+Space on Windows. Esc closes. Voice uses Whisper when configured — not browser speech.</p>
        <label>
          Capture
          <textarea
            autoFocus
            rows={4}
            value={text}
            onChange={(event) => setText(event.target.value)}
            placeholder="Pay internet ₦20,000 monthly on the 25th…"
            required
          />
        </label>
        {error && <p className="error">{error}</p>}
        {voiceHint && <p className="muted">{voiceHint}</p>}
        <div className="actions">
          <button type="submit" disabled={saving}>{saving ? 'Reading…' : 'Parse'}</button>
          <button type="button" onClick={() => void toggleVoice()}>{recording ? 'Stop voice' : 'Voice'}</button>
          <button type="button" onClick={() => navigate('/')}>Cancel</button>
          <Link to="/assistant">Ask assistant</Link>
        </div>
      </form>

      {proposal && (
        <section className="card narrow confirm-card">
          <h2>Confirm before saving</h2>
          <p className="muted">
            Confidence {(proposal.confidence * 100).toFixed(0)}%
            {proposal.requires_confirmation ? ' · confirmation required' : ''}
          </p>
          <label>Title
            <input
              value={proposal.title ?? ''}
              onChange={(event) => setProposal({ ...proposal, title: event.target.value })}
            />
          </label>
          <label>Type
            <select
              value={proposal.type ?? 'task'}
              onChange={(event) => setProposal({ ...proposal, type: event.target.value })}
            >
              {['payment', 'task', 'shopping', 'birthday', 'visit', 'appointment', 'event', 'custom'].map((type) => (
                <option key={type} value={type}>{type}</option>
              ))}
            </select>
          </label>
          <label>Due on
            <input
              type="date"
              value={proposal.due_on ?? ''}
              onChange={(event) => setProposal({ ...proposal, due_on: event.target.value })}
            />
          </label>
          {proposal.type === 'payment' && (
            <label>Amount (₦)
              <input value={amountInput} onChange={(event) => setAmountInput(event.target.value)} placeholder="20000" />
            </label>
          )}
          {proposal.rrule && <p className="muted">Recurrence: {proposal.rrule}</p>}
          {proposal.missing_fields.length > 0 && (
            <p className="error">Missing: {proposal.missing_fields.join(', ')}</p>
          )}
          {proposal.ambiguities.length > 0 && (
            <ul className="muted">
              {proposal.ambiguities.map((item) => <li key={item}>{item}</li>)}
            </ul>
          )}
          <div className="actions">
            <button
              type="button"
              disabled={saving || !canConfirm(proposal, amountInput, text)}
              onClick={() => void confirm()}
            >
              {saving ? 'Saving…' : 'Save'}
            </button>
            <button type="button" onClick={() => setProposal(null)}>Edit capture</button>
          </div>
        </section>
      )}
    </div>
  )
}

function canConfirm(proposal: Proposal, amountInput: string, text: string): boolean {
  return proposal.missing_fields.every((field) => {
    if (field === 'amount_minor') {
      return amountInput.trim() !== '' && !Number.isNaN(nairaToKobo(amountInput))
    }
    if (field === 'title') {
      return Boolean(proposal.title?.trim() || text.trim())
    }
    if (field === 'due_on' || field === 'due_day') {
      return Boolean(proposal.due_on || proposal.rrule)
    }
    return false
  })
}
