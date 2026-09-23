import { useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError, type Dashboard, type DashboardItem } from '../api'
import { ActionToast } from '../components/ActionToast'
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

type PendingAction = {
  occurrenceId: string
  title: string
  kind: 'paid' | 'done' | 'snooze'
  commit: () => Promise<void>
}

const UNDO_MS = 5_000
const EXIT_MS = 220

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

function toastCopy(kind: PendingAction['kind']): string {
  if (kind === 'paid') return 'Marked paid'
  if (kind === 'snooze') return 'Snoozed 1 hour'
  return 'Marked done'
}

function removeOccurrence(data: Dashboard, occurrenceId: string): Dashboard {
  const drop = (items: DashboardItem[]) => items.filter((item) => item.occurrence_id !== occurrenceId)
  return {
    ...data,
    today: drop(data.today),
    due_today: drop(data.due_today),
    upcoming: drop(data.upcoming),
    payments: drop(data.payments),
    tasks: drop(data.tasks),
    shopping: drop(data.shopping),
    events: drop(data.events),
    follow_ups: drop(data.follow_ups),
  }
}

export function DashboardPage() {
  const [data, setData] = useState<Dashboard>(empty)
  const [suggestions, setSuggestions] = useState<Suggestion[]>([])
  const [error, setError] = useState('')
  const [filter, setFilter] = useState<Filter>('all')
  const [showForecast, setShowForecast] = useState(false)
  const [leavingIds, setLeavingIds] = useState<Set<string>>(() => new Set())
  const [hiddenIds, setHiddenIds] = useState<Set<string>>(() => new Set())
  const [pending, setPending] = useState<PendingAction | null>(null)
  const undoTimer = useRef<number | null>(null)
  const exitTimers = useRef<Map<string, number>>(new Map())
  const stash = useRef<Map<string, DashboardItem>>(new Map())
  const pendingRef = useRef<PendingAction | null>(null)

  function clearUndoTimer() {
    if (undoTimer.current != null) {
      window.clearTimeout(undoTimer.current)
      undoTimer.current = null
    }
  }

  function setPendingAction(action: PendingAction | null) {
    pendingRef.current = action
    setPending(action)
  }

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
    return () => {
      window.removeEventListener('gitlife:synced', onSynced)
      clearUndoTimer()
      exitTimers.current.forEach((id) => window.clearTimeout(id))
    }
  }, [])

  async function commitPending(action: PendingAction) {
    try {
      await action.commit()
      stash.current.delete(action.occurrenceId)
      setHiddenIds((current) => {
        const next = new Set(current)
        next.delete(action.occurrenceId)
        return next
      })
      setData((current) => removeOccurrence(current, action.occurrenceId))
      await load().catch(() => undefined)
    } catch {
      restoreItem(action.occurrenceId)
      setError('Could not save that change. Try again.')
    } finally {
      if (pendingRef.current?.occurrenceId === action.occurrenceId) {
        setPendingAction(null)
      }
    }
  }

  function restoreItem(occurrenceId: string) {
    const item = stash.current.get(occurrenceId)
    stash.current.delete(occurrenceId)
    setLeavingIds((current) => {
      const next = new Set(current)
      next.delete(occurrenceId)
      return next
    })
    setHiddenIds((current) => {
      const next = new Set(current)
      next.delete(occurrenceId)
      return next
    })
    if (item) {
      setData((current) => {
        if (mergeTimeline(current).some((row) => row.occurrence_id === occurrenceId)) return current
        return { ...current, today: [item, ...current.today] }
      })
    }
  }

  function scheduleAction(item: DashboardItem, kind: PendingAction['kind'], commit: () => Promise<void>) {
    const previous = pendingRef.current
    if (previous && previous.occurrenceId !== item.occurrence_id) {
      clearUndoTimer()
      void commitPending(previous)
    }

    stash.current.set(item.occurrence_id, item)
    setLeavingIds((current) => new Set(current).add(item.occurrence_id))

    const existingExit = exitTimers.current.get(item.occurrence_id)
    if (existingExit != null) window.clearTimeout(existingExit)
    exitTimers.current.set(
      item.occurrence_id,
      window.setTimeout(() => {
        setLeavingIds((current) => {
          const next = new Set(current)
          next.delete(item.occurrence_id)
          return next
        })
        setHiddenIds((current) => new Set(current).add(item.occurrence_id))
        exitTimers.current.delete(item.occurrence_id)
      }, EXIT_MS),
    )

    const action: PendingAction = {
      occurrenceId: item.occurrence_id,
      title: item.title,
      kind,
      commit,
    }
    setPendingAction(action)
    clearUndoTimer()
    undoTimer.current = window.setTimeout(() => {
      if (pendingRef.current?.occurrenceId === action.occurrenceId) {
        void commitPending(action)
      }
    }, UNDO_MS)
  }

  function undo() {
    const action = pendingRef.current
    if (!action) return
    clearUndoTimer()
    const exit = exitTimers.current.get(action.occurrenceId)
    if (exit != null) {
      window.clearTimeout(exit)
      exitTimers.current.delete(action.occurrenceId)
    }
    restoreItem(action.occurrenceId)
    setPendingAction(null)
  }

  const visibleTimeline = useMemo(
    () => mergeTimeline(data).filter((item) => matchesFilter(item, filter) && !hiddenIds.has(item.occurrence_id)),
    [data, filter, hiddenIds],
  )
  const dueCount = useMemo(
    () => visibleTimeline.filter((item) => ['overdue', 'urgent', 'due_today'].includes(item.computed_status)).length,
    [visibleTimeline],
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

      {visibleTimeline.length === 0 ? (
        <EmptyState filter={filter} />
      ) : (
        <ul className="timeline">
          {visibleTimeline.map((item) => (
            <TimelineItem
              key={item.occurrence_id}
              item={item}
              leaving={leavingIds.has(item.occurrence_id)}
              onDone={() => scheduleAction(item, item.type === 'payment' ? 'paid' : 'done', async () => {
                await completeOccurrenceOfflineCapable(item.occurrence_id, item.type === 'payment')
              })}
              onSnooze={() => scheduleAction(item, 'snooze', async () => {
                await snoozeOccurrenceOfflineCapable(item.occurrence_id, 1)
              })}
            />
          ))}
        </ul>
      )}

      {pending && (
        <ActionToast
          message={`${toastCopy(pending.kind)} · ${pending.title}`}
          onUndo={undo}
        />
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

function TimelineItem({
  item,
  leaving,
  onDone,
  onSnooze,
}: {
  item: DashboardItem
  leaving: boolean
  onDone: () => void
  onSnooze: () => void
}) {
  return (
    <li className={`timeline-item type-${item.type} status-${item.computed_status}${leaving ? ' leaving' : ''}`}>
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
        <div className="actions">
          {item.type === 'payment'
            ? <button type="button" onClick={onDone}>Mark paid</button>
            : <button type="button" onClick={onDone}>Done</button>}
          <button type="button" className="ghost" onClick={onSnooze}>Snooze 1h</button>
        </div>
      </div>
    </li>
  )
}
