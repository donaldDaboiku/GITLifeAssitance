import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { api, ApiError, type Dashboard, type DashboardItem } from '../api'
import { formatDayFirst, formatNaira, statusLabel } from '../format'
import { completeOccurrenceOfflineCapable, snoozeOccurrenceOfflineCapable } from '../sync/actions'

type Suggestion = {
  id: string
  kind: string
  title: string
  reason: string
  requires_confirmation: boolean
  payload: Record<string, unknown>
}

const empty: Dashboard = {
  today: [],
  due_today: [],
  upcoming: [],
  payments: [],
  tasks: [],
  shopping: [],
  events: [],
  follow_ups: [],
  expected_payments: {
    this_week: { amount_minor: 0, currency: 'NGN', label: 'expected' },
    this_month: { amount_minor: 0, currency: 'NGN', label: 'expected' },
    next_month: { amount_minor: 0, currency: 'NGN', label: 'expected' },
  },
}

export function DashboardPage() {
  const [data, setData] = useState<Dashboard>(empty)
  const [suggestions, setSuggestions] = useState<Suggestion[]>([])
  const [error, setError] = useState('')
  const location = useLocation()

  async function load() {
    try {
      setData(await api<Dashboard>('/api/dashboard'))
      const body = await api<{ data: Suggestion[] }>('/api/suggestions')
      setSuggestions(body.data)
      setError('')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not load the dashboard.')
    }
  }

  async function confirmSuggestion(suggestion: Suggestion) {
    await api('/api/suggestions/confirm', {
      method: 'POST',
      body: JSON.stringify({
        suggestion_id: suggestion.id,
        payload: suggestion.payload,
      }),
    })
    await load()
  }

  useEffect(() => {
    void load()
    const onSynced = () => void load()
    window.addEventListener('gitlife:synced', onSynced)
    return () => window.removeEventListener('gitlife:synced', onSynced)
  }, [])

  return (
    <div className="stack">
      <div className="row">
        <h1>Today</h1>
        <Link className="button" to="/activities/new">Add</Link>
      </div>
      <nav className="nav-links">
        <Link className={location.pathname === '/' ? 'active' : ''} to="/">Home</Link>
        <Link to="/contacts">People</Link>
        <Link to="/shopping">Shopping</Link>
        <Link to="/search">Search</Link>
        <Link to="/settings">Settings</Link>
      </nav>
      {error && <p className="error">{error}</p>}
      <section className="card">
        <h2>Expected payments</h2>
        <p className="muted">These are planned amounts, not bank transactions.</p>
        <div className="totals">
          <div>This week · expected {formatNaira(data.expected_payments.this_week.amount_minor)}</div>
          <div>This month · expected {formatNaira(data.expected_payments.this_month.amount_minor)}</div>
          <div>Next month · expected {formatNaira(data.expected_payments.next_month.amount_minor)}</div>
        </div>
      </section>
      {suggestions.length > 0 && (
        <section className="card">
          <h2>Suggested follow-ups</h2>
          <p className="muted">Nothing is created until you confirm.</p>
          <ul className="list">
            {suggestions.map((suggestion) => (
              <li key={suggestion.id}>
                <strong>{suggestion.title}</strong>
                <p className="muted">{suggestion.reason}</p>
                <button type="button" onClick={() => void confirmSuggestion(suggestion)}>Confirm</button>
              </li>
            ))}
          </ul>
        </section>
      )}
      <Section title="Today" items={data.today} empty="Nothing due today." onChange={load} />
      <Section title="Due today" items={data.due_today} empty="No items are due today." onChange={load} />
      <Section title="Upcoming" items={data.upcoming} empty="Nothing coming up." onChange={load} />
      <Section title="Payments" items={data.payments} empty="No expected payments." onChange={load} note="Amounts are expected." />
      <Section title="Tasks" items={data.tasks} empty="No open tasks." onChange={load} />
      <Section title="Shopping" items={data.shopping} empty="No shopping reminders." onChange={load} />
      <Section title="Events" items={data.events} empty="No birthdays or events." onChange={load} />
      <Section title="Follow-ups" items={data.follow_ups} empty="No follow-ups." onChange={load} />
    </div>
  )
}

function Section({ title, items, empty, note, onChange }: { title: string; items: DashboardItem[]; empty: string; note?: string; onChange: () => Promise<void> }) {
  return (
    <section className="card">
      <h2>{title}</h2>
      {note && <p className="muted">{note}</p>}
      {items.length === 0 && <p className="muted">{empty}</p>}
      <ul className="list">
        {items.map((item) => (
          <li key={`${title}-${item.occurrence_id}`}>
            <Link to={`/activities/${item.activity_id}`}>
              <strong>{item.title}</strong>
              <span className={`pill ${item.computed_status}`}>{statusLabel(item.computed_status)}</span>
            </Link>
            <p className="muted">
              {formatDayFirst(item.due_local_date)}
              {item.amount_minor != null && ` · Expected ${formatNaira(item.amount_minor)}`}
            </p>
            <Actions item={item} onChange={onChange} />
          </li>
        ))}
      </ul>
    </section>
  )
}

function Actions({ item, onChange }: { item: DashboardItem; onChange: () => Promise<void> }) {
  const [note, setNote] = useState('')

  async function finish(asPayment: boolean) {
    const result = await completeOccurrenceOfflineCapable(item.occurrence_id, asPayment)
    setNote(result === 'queued' ? 'Saved offline — will sync soon.' : '')
    await onChange().catch(() => undefined)
  }

  async function snooze() {
    const result = await snoozeOccurrenceOfflineCapable(item.occurrence_id, 1)
    setNote(result === 'queued' ? 'Snooze queued offline.' : '')
    await onChange().catch(() => undefined)
  }

  return (
    <div className="actions">
      {item.type === 'payment'
        ? <button type="button" onClick={() => void finish(true)}>Mark paid</button>
        : <button type="button" onClick={() => void finish(false)}>Done</button>}
      <button type="button" onClick={() => void snooze()}>Snooze 1h</button>
      {note && <p className="muted">{note}</p>}
    </div>
  )
}
