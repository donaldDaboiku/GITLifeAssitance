import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError, koboToNairaInput, nairaToKobo, type Activity, type ActivityType, type Contact } from '../api'

const offsets = [
  { label: '1 week before', minutes: 10080 },
  { label: '3 days before', minutes: 4320 },
  { label: '1 day before', minutes: 1440 },
  { label: 'On the day', minutes: 0 },
]

const types: ActivityType[] = ['payment', 'task', 'birthday', 'anniversary', 'visit', 'appointment', 'shopping']

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

function presetFromRrule(rrule: string | null): string {
  if (!rrule) return 'once'
  if (rrule === 'FREQ=DAILY') return 'daily'
  if (rrule.includes('BYDAY=MO,TU,WE,TH,FR')) return 'weekdays'
  if (rrule.startsWith('FREQ=WEEKLY')) return 'weekly'
  if (rrule.startsWith('FREQ=MONTHLY')) return 'monthly'
  if (rrule.startsWith('FREQ=YEARLY')) return 'yearly'
  return 'once'
}

function defaultRepeat(type: ActivityType): string {
  if (type === 'birthday' || type === 'anniversary') return 'yearly'
  if (type === 'payment') return 'monthly'
  return 'once'
}

function defaultOffsets(type: ActivityType): number[] {
  if (type === 'birthday' || type === 'anniversary') return [10080, 1440, 0]
  if (type === 'payment') return [4320, 1440]
  return [0]
}

export function ActivityFormPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [type, setType] = useState<ActivityType>('payment')
  const [title, setTitle] = useState('')
  const [amount, setAmount] = useState('')
  const [category, setCategory] = useState('')
  const [method, setMethod] = useState('')
  const [dueOn, setDueOn] = useState('')
  const [dueTime, setDueTime] = useState('')
  const [location, setLocation] = useState('')
  const [repeat, setRepeat] = useState('monthly')
  const [selectedOffsets, setSelectedOffsets] = useState<number[]>([4320, 1440])
  const [notes, setNotes] = useState('')
  const [giftIdea, setGiftIdea] = useState('')
  const [giftBudget, setGiftBudget] = useState('')
  const [contacts, setContacts] = useState<Contact[]>([])
  const [contactId, setContactId] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    void api<{ data: Contact[] }>('/api/contacts').then((body) => setContacts(body.data)).catch(() => setContacts([]))
  }, [])

  useEffect(() => {
    if (!id) return
    api<{ data: Activity }>(`/api/activities/${id}`).then((body) => {
      const activity = body.data
      setType(activity.type)
      setTitle(activity.title)
      const next = activity.occurrences.find((occurrence) => occurrence.status === 'pending') ?? activity.occurrences[0]
      setDueOn(next?.due_local_date ?? '')
      setRepeat(presetFromRrule(activity.rrule))
      setSelectedOffsets(activity.reminder_offsets_minutes ?? [])
      setNotes(activity.notes ?? '')
      setLocation(activity.location ?? '')
      setContactId(activity.contact_id ?? '')
      setCategory(activity.payment?.payment_category ?? activity.category ?? '')
      setMethod(activity.payment?.payment_method ?? '')
      if (activity.payment) setAmount(koboToNairaInput(activity.payment.amount_minor))
    }).catch((caught) => setError(caught instanceof ApiError ? caught.message : 'Could not load this item.'))
  }, [id])

  function chooseType(next: ActivityType) {
    setType(next)
    setRepeat(defaultRepeat(next))
    setSelectedOffsets(defaultOffsets(next))
  }

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

    const giftBudgetMinor = giftBudget ? nairaToKobo(giftBudget) : null
    if (giftIdea && giftBudget && Number.isNaN(giftBudgetMinor)) {
      setError('Gift budget must be a valid naira amount.')
      return
    }

    const payload = {
      type,
      title,
      due_on: dueOn,
      due_at_time: dueTime ? `${dueTime}:00` : undefined,
      timezone: 'Africa/Lagos',
      location: location || null,
      contact_id: contactId || null,
      notes: notes || null,
      category: category || null,
      rrule: rruleFor(repeat, dueOn),
      reminder_offsets_minutes: selectedOffsets,
      payment: type === 'payment'
        ? {
            amount_minor: amountMinor,
            currency: 'NGN',
            payment_category: category || null,
            payment_method: method || null,
          }
        : undefined,
      gift: !id && giftIdea && (type === 'birthday' || type === 'anniversary')
        ? { idea: giftIdea, budget_minor: giftBudgetMinor ?? undefined }
        : undefined,
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
      <div className="actions">
        {types.map((option) => (
          <button key={option} type="button" className={type === option ? 'on' : ''} onClick={() => chooseType(option)}>
            {option}
          </button>
        ))}
      </div>
      <label>Title<input value={title} onChange={(event) => setTitle(event.target.value)} required /></label>
      {type === 'payment' && (
        <>
          <label>Expected amount (₦)<input inputMode="decimal" value={amount} onChange={(event) => setAmount(event.target.value)} required placeholder="20000" /></label>
          <label>Category<input value={category} onChange={(event) => setCategory(event.target.value)} placeholder="utilities" /></label>
          <label>Method<input value={method} onChange={(event) => setMethod(event.target.value)} placeholder="transfer" /></label>
        </>
      )}
      {(type === 'visit' || type === 'appointment') && (
        <>
          <label>Location<input value={location} onChange={(event) => setLocation(event.target.value)} /></label>
          <label>Time<input type="time" value={dueTime} onChange={(event) => setDueTime(event.target.value)} /></label>
        </>
      )}
      <label>Person
        <select value={contactId} onChange={(event) => setContactId(event.target.value)}>
          <option value="">None</option>
          {contacts.map((contact) => <option key={contact.id} value={contact.id}>{contact.name}</option>)}
        </select>
      </label>
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
      {!id && (type === 'birthday' || type === 'anniversary') && (
        <fieldset>
          <legend>Gift planning</legend>
          <label>Gift idea<input value={giftIdea} onChange={(event) => setGiftIdea(event.target.value)} placeholder="Notebook" /></label>
          <label>Budget (₦)<input inputMode="decimal" value={giftBudget} onChange={(event) => setGiftBudget(event.target.value)} placeholder="5000" /></label>
        </fieldset>
      )}
      <label>Notes<textarea value={notes} onChange={(event) => setNotes(event.target.value)} rows={3} /></label>
      {error && <p className="error">{error}</p>}
      <button type="submit">Save</button>
      <p className="muted"><Link to={id ? `/activities/${id}` : '/'}>Cancel</Link></p>
    </form>
  )
}
