import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError, koboToNairaInput, nairaToKobo, type Activity } from '../api'

const offsets = [
  { label: '3 days before', minutes: 4320 },
  { label: '1 day before', minutes: 1440 },
  { label: 'On the day', minutes: 0 },
]

function rruleFor(preset: string, dueOn: string): string | null {
  if (!dueOn || preset === 'once') return null
  const date = new Date(`${dueOn}T00:00:00`)
  const days = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA']
  if (preset === 'daily') return 'FREQ=DAILY'
  if (preset === 'weekdays') return 'FREQ=DAILY;BYDAY=MO,TU,WE,TH,FR'
  if (preset === 'weekly') return `FREQ=WEEKLY;BYDAY=${days[date.getDay()]}`
  if (preset === 'monthly') return `FREQ=MONTHLY;BYMONTHDAY=${date.getDate()}`
  if (preset === 'yearly') return 'FREQ=YEARLY'
  return null
}

function presetFromRrule(rrule: string | null, dueOn: string): string {
  if (!rrule) return 'once'
  if (rrule === 'FREQ=DAILY') return 'daily'
  if (rrule.includes('BYDAY=MO,TU,WE,TH,FR')) return 'weekdays'
  if (rrule.startsWith('FREQ=WEEKLY')) return 'weekly'
  if (rrule.startsWith('FREQ=MONTHLY')) return 'monthly'
  if (rrule.startsWith('FREQ=YEARLY')) return 'yearly'
  return dueOn ? 'monthly' : 'once'
}

export function ActivityFormPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [type, setType] = useState<'payment' | 'task'>('payment')
  const [title, setTitle] = useState('')
  const [amount, setAmount] = useState('')
  const [dueOn, setDueOn] = useState('')
  const [repeat, setRepeat] = useState('monthly')
  const [selectedOffsets, setSelectedOffsets] = useState<number[]>([4320, 1440])
  const [notes, setNotes] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    if (!id) return
    api<{ data: Activity }>(`/api/activities/${id}`).then((body) => {
      const activity = body.data
      setType(activity.type)
      setTitle(activity.title)
      const next = activity.occurrences.find((occurrence) => occurrence.status === 'pending') ?? activity.occurrences[0]
      setDueOn(next?.due_local_date ?? '')
      setRepeat(presetFromRrule(activity.rrule, next?.due_local_date ?? ''))
      setSelectedOffsets(activity.reminder_offsets_minutes ?? [])
      setNotes(activity.notes ?? '')
      if (activity.payment) setAmount(koboToNairaInput(activity.payment.amount_minor))
    }).catch((caught) => setError(caught instanceof ApiError ? caught.message : 'Could not load this item.'))
  }, [id])

  function toggleOffset(minutes: number) {
    setSelectedOffsets((current) => current.includes(minutes)
      ? current.filter((value) => value !== minutes)
      : [...current, minutes])
  }

  async function submit(event: FormEvent) {
    event.preventDefault()
    setError('')
    const amountMinor = type === 'payment' ? nairaToKobo(amount) : null
    if (type === 'payment' && (amountMinor === null || Number.isNaN(amountMinor))) {
      setError('Enter the expected amount in naira, for example 20000.')
      return
    }

    const payload = {
      type,
      title,
      due_on: dueOn,
      timezone: 'Africa/Lagos',
      notes: notes || null,
      rrule: rruleFor(repeat, dueOn),
      reminder_offsets_minutes: selectedOffsets,
      payment: type === 'payment' ? { amount_minor: amountMinor, currency: 'NGN' } : undefined,
    }

    try {
      const path = id ? `/api/activities/${id}` : '/api/activities'
      const response = await api<{ data: Activity }>(path, {
        method: id ? 'PUT' : 'POST',
        body: JSON.stringify(payload),
      })
      navigate(`/activities/${response.data.id}`)
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not save.')
    }
  }

  return (
    <form className="card" onSubmit={(event) => void submit(event)}>
      <h1>{id ? 'Edit' : 'What do you want to remember?'}</h1>
      <div className="row">
        <button type="button" className={type === 'payment' ? 'on' : ''} onClick={() => setType('payment')}>Payment</button>
        <button type="button" className={type === 'task' ? 'on' : ''} onClick={() => setType('task')}>Task</button>
      </div>
      <label>Title<input value={title} onChange={(event) => setTitle(event.target.value)} required placeholder="Internet" /></label>
      {type === 'payment' && (
        <label>Expected amount (₦)<input inputMode="decimal" value={amount} onChange={(event) => setAmount(event.target.value)} required placeholder="20000" /></label>
      )}
      <label>Due date<input type="date" value={dueOn} onChange={(event) => setDueOn(event.target.value)} required /></label>
      <label>Repeat
        <select value={repeat} onChange={(event) => setRepeat(event.target.value)}>
          <option value="once">Once</option>
          <option value="daily">Daily</option>
          <option value="weekdays">Weekdays</option>
          <option value="weekly">Weekly</option>
          <option value="monthly">Monthly</option>
          <option value="yearly">Yearly</option>
        </select>
      </label>
      <fieldset>
        <legend>Reminders</legend>
        {offsets.map((offset) => (
          <label key={offset.minutes} className="check">
            <input type="checkbox" checked={selectedOffsets.includes(offset.minutes)} onChange={() => toggleOffset(offset.minutes)} />
            {offset.label}
          </label>
        ))}
      </fieldset>
      <label>Notes<textarea value={notes} onChange={(event) => setNotes(event.target.value)} rows={3} /></label>
      {error && <p className="error">{error}</p>}
      <button type="submit">Save</button>
      <p><Link to={id ? `/activities/${id}` : '/'}>Cancel</Link></p>
    </form>
  )
}
