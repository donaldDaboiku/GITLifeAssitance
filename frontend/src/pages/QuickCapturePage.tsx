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

const EXAMPLES = [
  'Pay DSTV ₦24,000 on the 15th',
  'Mum’s birthday March 3',
  'Buy rice and oil this weekend',
]

function typeLabel(type: string | null): string {
  if (!type) return 'Item'
  if (type === 'payment') return 'Payment'
  if (type === 'task') return 'Task'
  if (type === 'shopping') return 'Shopping'
  return type.replaceAll('_', ' ')
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
  const confirmRef = useRef<HTMLElement>(null)

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        if (proposal) {
          setProposal(null)
          return
        }
        navigate('/')
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [navigate, proposal])

  useEffect(() => {
    if (proposal) {
      confirmRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  }, [proposal])

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

  const step = proposal ? 'confirm' : 'capture'
  const type = proposal?.type ?? 'task'

  return (
    <div className={`capture-screen step-${step}`}>
      <div className="capture-top">
        <button type="button" className="ghost close-capture" onClick={() => navigate('/')}>
          Close
        </button>
        <span className="capture-step-label">{step === 'capture' ? 'Step 1 of 2' : 'Step 2 of 2'}</span>
      </div>

      {step === 'capture' && (
        <form className="capture-hero" onSubmit={(event) => void submit(event)}>
          <p className="eyebrow">GIT Life</p>
          <h1>What do you want to remember?</h1>
          <p className="capture-lead">Say it like you’d text yourself — rent, birthdays, shopping, visits.</p>

          <textarea
            className="capture-input"
            autoFocus
            rows={4}
            value={text}
            onChange={(event) => setText(event.target.value)}
            placeholder="Pay internet ₦20,000 monthly on the 25th…"
            required
          />

          <div className="example-row" aria-label="Examples">
            {EXAMPLES.map((example) => (
              <button
                key={example}
                type="button"
                className="chip example-chip"
                onClick={() => setText(example)}
              >
                {example}
              </button>
            ))}
          </div>

          {error && <p className="error">{error}</p>}
          {voiceHint && <p className="muted">{voiceHint}</p>}

          <div className="capture-actions">
            <button type="submit" className="capture-primary" disabled={saving || !text.trim()}>
              {saving ? 'Reading…' : 'Remember this'}
            </button>
            <button
              type="button"
              className={`ghost voice-btn${recording ? ' recording' : ''}`}
              onClick={() => void toggleVoice()}
            >
              {recording ? 'Stop voice' : 'Voice'}
            </button>
          </div>

          <p className="muted capture-hint">
            Esc closes · Prefer forms? <Link to="/activities/new">Add with form</Link>
            {' · '}
            <Link to="/assistant">Ask assistant</Link>
          </p>
        </form>
      )}

      {proposal && (
        <section
          ref={confirmRef}
          className={`confirm-step type-${type}`}
          aria-label="Confirm before saving"
        >
          <div className="confirm-rail" aria-hidden />
          <div className="confirm-body">
            <p className="eyebrow">Check this first</p>
            <h2>Looks right?</h2>
            <p className="muted">
              Nothing is saved until you confirm.
              {' · '}
              {(proposal.confidence * 100).toFixed(0)}% confidence
              {proposal.requires_confirmation ? ' · needs your eyes' : ''}
            </p>

            <div className="confirm-preview">
              <span className={`type-pill type-${type}`}>{typeLabel(proposal.type)}</span>
              <strong className="confirm-title">{proposal.title?.trim() || text.trim() || 'Untitled'}</strong>
              {proposal.due_on && <span className="muted">Due {proposal.due_on.split('-').reverse().join('/')}</span>}
              {proposal.type === 'payment' && amountInput && (
                <span className="muted">Expected ₦{amountInput}</span>
              )}
            </div>

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
                {['payment', 'task', 'shopping', 'birthday', 'visit', 'appointment', 'event', 'custom'].map((option) => (
                  <option key={option} value={option}>{option}</option>
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
              <p className="error">Still need: {proposal.missing_fields.join(', ')}</p>
            )}
            {proposal.ambiguities.length > 0 && (
              <ul className="muted ambiguity-list">
                {proposal.ambiguities.map((item) => <li key={item}>{item}</li>)}
              </ul>
            )}
            {error && <p className="error">{error}</p>}

            <div className="capture-actions">
              <button
                type="button"
                className="capture-primary"
                disabled={saving || !canConfirm(proposal, amountInput, text)}
                onClick={() => void confirm()}
              >
                {saving ? 'Saving…' : 'Save'}
              </button>
              <button type="button" className="ghost" onClick={() => setProposal(null)}>
                Edit capture
              </button>
            </div>
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
