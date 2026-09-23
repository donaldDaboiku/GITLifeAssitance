import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
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

type Filter = 'all' | 'payment' | 'task' | 'people' | 'shopping'

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

const PEOPLE_TYPES = new Set(['birthday', 'anniversary', 'visit', 'appointment'])

const STATUS_ORDER: Record<string, number> = {
  overdue: 0,
  urgent: 1,
  due_today: 2,
  due_soon: 3,
  upcoming: 4,
  completed: 5,
}

function typeLabel(type: string): string {
  if (type === 'payment') return 'Payment'
  if (type === 'task') return 'Task'
  if (type === 'shopping') return 'Shopping'
  if (PEOPLE_TYPES.has(type)) return type.replaceAll('_', ' ')
  return type
}

function matchesFilter(item: DashboardItem, filter: Filter): boolean {
  if (filter === 'all') return true
  if (filter === 'payment') return item.type === 'payment'
  if (filter === 'task') return item.type === 'task'
  if (filter === 'shopping') return item.type === 'shopping'
  return PEOPLE_TYPES.has(item.type)
}

function mergeTimeline(data: Dashboard): DashboardItem[] {
  const byId = new Map<string, DashboardItem>()
  for (const item of [...data.today, ...data.due_today, ...data.upcoming, ...data.follow_ups]) {
    if (!byId.has(item.occurrence_id)) byId.set(item.occurrence_id, item)
  }
  return [...byId.values()].sort((a, b) => {
    const statusDiff = (STATUS_ORDER[a.computed_status] ?? 9) - (STATUS_ORDER[b.computed_status] ?? 9)
    if (statusDiff !== 0) return statusDiff
    return a.due_at.localeCompare(b.due_at)
  })
}

function greeting(): string {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 17) return 'Good afternoon'
  return 'Good evening'
}

export function DashboardPage() {
  const [data, setData] = useState<Dashboard>(empty)
  const [suggestions, setSuggestions] = useState<Suggestion[]>([])
  const [error, setError] = useState('')
  const [filter, setFilter] = useState<Filter>('all')
  const [showForecast, setShowForecast] = useState(false)

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

  const timeline = useMemo(() => mergeTimeline(data).filter((item) => matchesFilter(item, filter)), [data, filter])
  const dueCount = useMemo(
    () => mergeTimeline(data).filter((item) => ['overdue', 'urgent', 'due_today'].includes(item.computed_status)).length,
    [data],
  )

  return (
    <div className="stack home">
      <header className="page-hero">
        <p className="eyebrow">{greeting()}</p>
        <h1>Today</h1>
        <p className="muted">
          {dueCount === 0
            ? 'Nothing urgent right now.'
            : `${dueCount} item${dueCount === 1 ? '' : 's'} need${dueCount === 1 ? 's' : ''} you.`}
        </p>
      </header>

      {error && <p className="error">{error}</p>}

      <section className="forecast-strip">
        <button type="button" className="forecast-toggle" onClick={() => setShowForecast((open) => !open)}>
          This month · expected {formatNaira(data.expected_payments.this_month.amount_minor)}
          <span aria-hidden>{showForecast ? '▴' : '▾'}</span>
        </button>
        {showForecast && (
          <div className="totals">
            <div>This week · expected {formatNaira(data.expected_payments.this_week.amount_minor)}</div>
            <div>Next month · expected {formatNaira(data.expected_payments.next_month.amount_minor)}</div>
            <p className="muted">Planned amounts, not bank transactions.</p>
          </div>
        )}
      </section>

      {suggestions.length > 0 && (
        <section className="card suggest-card">
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

      <div className="filter-chips" role="tablist" aria-label="Filter timeline">
        {([
          ['all', 'All'],
          ['payment', 'Payments'],
          ['task', 'Tasks'],
          ['people', 'People'],
          ['shopping', 'Shopping'],
        ] as const).map(([key, label]) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={filter === key}
            className={filter === key ? 'chip on' : 'chip'}
            onClick={() => setFilter(key)}
          >
            {label}
          </button>
        ))}
      </div>

      {timeline.length === 0 ? (
        <EmptyState filter={filter} />
      ) : (
        <ul className="timeline">
          {timeline.map((item) => (
            <TimelineItem key={item.occurrence_id} item={item} onChange={load} />
          ))}
        </ul>
      )}
    </div>
  )
}

function EmptyState({ filter }: { filter: Filter }) {
  const copy =
    filter === 'payment'
      ? { title: 'No payments in view', hint: 'Add rent, DSTV, or data — we’ll remind you before due day.' }
      : filter === 'task'
        ? { title: 'No tasks right now', hint: 'Capture something you need to finish this week.' }
        : filter === 'people'
          ? { title: 'No people events', hint: 'Whose birthday or visit is coming up?' }
          : filter === 'shopping'
            ? { title: 'Shopping list is clear', hint: 'Add market or pharmacy items when you think of them.' }
            : { title: 'Your day is clear', hint: 'Capture rent, a birthday, or a quick task to get started.' }

  return (
    <section className="empty-state">
      <h2>{copy.title}</h2>
      <p className="muted">{copy.hint}</p>
      <div className="actions">
        <Link className="button" to="/capture">Capture</Link>
        <Link className="button ghost" to="/activities/new">Add with form</Link>
      </div>
    </section>
  )
}

function TimelineItem({ item, onChange }: { item: DashboardItem; onChange: () => Promise<void> }) {
  return (
    <li className={`timeline-item type-${item.type} status-${item.computed_status}`}>
      <div className="timeline-rail" aria-hidden />
      <div className="timeline-body">
        <Link to={`/activities/${item.activity_id}`} className="timeline-link">
          <div className="timeline-meta">
            <span className={`type-pill type-${item.type}`}>{typeLabel(item.type)}</span>
            <span className={`pill ${item.computed_status}`}>{statusLabel(item.computed_status)}</span>
          </div>
          <strong>{item.title}</strong>
          <p className="muted">
            {formatDayFirst(item.due_local_date)}
            {item.amount_minor != null && ` · Expected ${formatNaira(item.amount_minor)}`}
          </p>
        </Link>
        <Actions item={item} onChange={onChange} />
      </div>
    </li>
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
      <button type="button" className="ghost" onClick={() => void snooze()}>Snooze 1h</button>
      {note && <p className="muted">{note}</p>}
    </div>
  )
}
