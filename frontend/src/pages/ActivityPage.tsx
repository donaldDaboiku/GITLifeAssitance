import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError, type Activity, type Occurrence } from '../api'
import { ActionToast } from '../components/ActionToast'
import { formatDayFirst, statusLabel } from '../format'

type Offer = { key: string; label: string }

type PendingAction = {
  occurrenceId: string
  label: string
  commit: () => Promise<void>
}

const UNDO_MS = 5_000

const PEOPLE_TYPES = new Set(['birthday', 'anniversary', 'visit', 'appointment'])

function typeLabel(type: string): string {
  if (type === 'payment') return 'Payment'
  if (type === 'task') return 'Task'
  if (type === 'shopping') return 'Shopping'
  if (type === 'follow_up') return 'Follow-up'
  if (PEOPLE_TYPES.has(type)) return type.replaceAll('_', ' ')
  return type.replaceAll('_', ' ')
}

function repeatLabel(rrule: string | null): string | null {
  if (!rrule) return null
  if (rrule === 'FREQ=DAILY') return 'Daily'
  if (rrule.includes('BYDAY=MO,TU,WE,TH,FR')) return 'Weekdays'
  if (rrule.startsWith('FREQ=WEEKLY')) return 'Weekly'
  if (rrule.startsWith('FREQ=MONTHLY')) return 'Monthly'
  if (rrule.startsWith('FREQ=YEARLY')) return 'Yearly'
  return rrule
}

export function ActivityPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [activity, setActivity] = useState<Activity | null>(null)
  const [offers, setOffers] = useState<Offer[] | null>(null)
  const [selected, setSelected] = useState<string[]>([])
  const [nextVisitOn, setNextVisitOn] = useState('')
  const [error, setError] = useState('')
  const [pending, setPending] = useState<PendingAction | null>(null)
  const [leavingId, setLeavingId] = useState<string | null>(null)
  const undoTimer = useRef<number | null>(null)
  const pendingRef = useRef<PendingAction | null>(null)

  function setPendingAction(action: PendingAction | null) {
    pendingRef.current = action
    setPending(action)
  }

  function clearUndoTimer() {
    if (undoTimer.current != null) {
      window.clearTimeout(undoTimer.current)
      undoTimer.current = null
    }
  }

  async function load() {
    try {
      const body = await api<{ data: Activity }>(`/api/activities/${id}`)
      setActivity(body.data)
      setError('')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not load this item.')
    }
  }

  useEffect(() => {
    void load()
    return () => clearUndoTimer()
  }, [id])

  async function commitPending(action: PendingAction) {
    try {
      await action.commit()
      setLeavingId(null)
      await load()
    } catch {
      setLeavingId(null)
      setError('Could not save that change. Try again.')
    } finally {
      if (pendingRef.current?.occurrenceId === action.occurrenceId) {
        setPendingAction(null)
      }
    }
  }

  function scheduleAction(occurrenceId: string, label: string, commit: () => Promise<void>) {
    const previous = pendingRef.current
    if (previous && previous.occurrenceId !== occurrenceId) {
      clearUndoTimer()
      void commitPending(previous)
    }

    setLeavingId(occurrenceId)
    const action: PendingAction = { occurrenceId, label, commit }
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
    setLeavingId(null)
    setPendingAction(null)
  }

  async function runOccurrence(occurrenceId: string, action: string, body?: object) {
    const response = await api<{ data: Activity; follow_up_offers?: Offer[] | null }>(
      `/api/occurrences/${occurrenceId}/${action}`,
      {
        method: 'POST',
        body: body ? JSON.stringify(body) : undefined,
      },
    )
    if (action === 'complete' && response.follow_up_offers) {
      setOffers(response.follow_up_offers)
      setSelected(response.follow_up_offers.map((offer) => offer.key))
    }
  }

  async function applyFollowUps() {
    if (!activity || selected.length === 0) return
    await api(`/api/activities/${activity.id}/visit-follow-ups`, {
      method: 'POST',
      body: JSON.stringify({
        choices: selected,
        next_visit_on: selected.includes('next_visit') && nextVisitOn ? nextVisitOn : undefined,
      }),
    })
    setOffers(null)
    await load()
  }

  const primary = useMemo(() => {
    if (!activity) return null
    return activity.occurrences.find((row) => row.status === 'pending') ?? activity.occurrences[0] ?? null
  }, [activity])

  const history = useMemo(() => {
    if (!activity || !primary) return []
    return activity.occurrences.filter((row) => row.id !== primary.id)
  }, [activity, primary])

  if (!activity) {
    return <p className="center">{error || 'Loading…'}</p>
  }

  const repeat = repeatLabel(activity.rrule)

  return (
    <div className={`stack activity-detail type-${activity.type}`}>
      <div className="activity-top">
        <Link to="/" className="back-link">← Home</Link>
        <Link to={`/activities/${activity.id}/edit`} className="ghost button edit-link">Edit</Link>
      </div>

      <header className={`activity-hero type-${activity.type}`}>
        <div className="activity-rail" aria-hidden />
        <div className="activity-hero-body">
          <span className={`type-pill type-${activity.type}`}>{typeLabel(activity.type)}</span>
          <h1>{activity.title}</h1>
          {activity.payment && (
            <p className="amount">Expected {activity.payment.amount_display}</p>
          )}
          <div className="activity-meta muted">
            {primary && <span>{formatDayFirst(primary.due_local_date)}</span>}
            {primary && <span className={`pill ${primary.computed_status}`}>{statusLabel(primary.computed_status)}</span>}
            {repeat && <span>Repeats {repeat}</span>}
            {activity.location && <span>{activity.location}</span>}
          </div>
          {activity.payment?.payment_category && (
            <p className="muted">Category · {activity.payment.payment_category}</p>
          )}
          {activity.payment?.payment_method && (
            <p className="muted">Method · {activity.payment.payment_method}</p>
          )}
          {activity.notes && <p className="activity-notes">{activity.notes}</p>}
        </div>
      </header>

      {error && <p className="error">{error}</p>}

      {primary && primary.status === 'pending' && leavingId !== primary.id && (
        <section className="activity-actions card">
          <p className="muted">What do you want to do?</p>
          <div className="actions">
            {activity.type === 'payment' ? (
              <button
                type="button"
                className="capture-primary"
                onClick={() => scheduleAction(primary.id, 'Marked paid', () => runOccurrence(primary.id, 'pay'))}
              >
                Mark paid
              </button>
            ) : (
              <button
                type="button"
                className="capture-primary"
                onClick={() => scheduleAction(primary.id, 'Marked done', () => runOccurrence(primary.id, 'complete'))}
              >
                Done
              </button>
            )}
            <button
              type="button"
              className="ghost"
              onClick={() => scheduleAction(primary.id, 'Snoozed', () => runOccurrence(primary.id, 'snooze', { preset: '1hour' }))}
            >
              Snooze 1h
            </button>
            <button
              type="button"
              className="ghost"
              onClick={() => scheduleAction(primary.id, 'Skipped', () => runOccurrence(primary.id, 'skip'))}
            >
              Skip
            </button>
          </div>
        </section>
      )}

      {primary && leavingId === primary.id && (
        <p className="muted activity-pending-note">Waiting for undo window…</p>
      )}

      {offers && (
        <section className="card follow-up-card">
          <h2>Visit follow-ups</h2>
          <p className="muted">Choose what to create. Nothing is created until you confirm.</p>
          {offers.map((offer) => (
            <label key={offer.key} className="check">
              <input
                type="checkbox"
                checked={selected.includes(offer.key)}
                onChange={() => setSelected((current) => current.includes(offer.key)
                  ? current.filter((value) => value !== offer.key)
                  : [...current, offer.key])}
              />
              {offer.label}
            </label>
          ))}
          {selected.includes('next_visit') && (
            <label>Next visit date
              <input type="date" value={nextVisitOn} onChange={(event) => setNextVisitOn(event.target.value)} />
            </label>
          )}
          <div className="actions">
            <button type="button" onClick={() => void applyFollowUps()}>Create selected</button>
            <button type="button" className="ghost" onClick={() => setOffers(null)}>Not now</button>
          </div>
        </section>
      )}

      {activity.links && activity.links.length > 0 && (
        <section className="card">
          <h2>Linked</h2>
          <ul className="list">
            {activity.links.map((link) => (
              <li key={link.id}>
                <Link to={`/activities/${link.child_activity_id}`}>
                  {link.child_title ?? link.child_activity_id}
                </Link>
                <span className="muted"> · {link.relation}</span>
              </li>
            ))}
          </ul>
        </section>
      )}

      {history.length > 0 && (
        <section className="card">
          <h2>History</h2>
          <ul className="list occurrence-history">
            {history.map((occurrence) => (
              <OccurrenceRow key={occurrence.id} occurrence={occurrence} />
            ))}
          </ul>
        </section>
      )}

      <div className="activity-danger">
        <button
          type="button"
          className="ghost danger-text"
          onClick={() => {
            if (!window.confirm('Delete this item? This cannot be undone.')) return
            void api(`/api/activities/${activity.id}`, { method: 'DELETE' })
              .then(() => navigate('/'))
              .catch((caught) => setError(caught instanceof ApiError ? caught.message : 'Could not delete this item.'))
          }}
        >
          Delete
        </button>
      </div>

      {pending && (
        <ActionToast
          message={`${pending.label} · ${activity.title}`}
          onUndo={undo}
        />
      )}
    </div>
  )
}

function OccurrenceRow({ occurrence }: { occurrence: Occurrence }) {
  return (
    <li>
      <strong>{formatDayFirst(occurrence.due_local_date)}</strong>
      <span className={`pill ${occurrence.computed_status}`}>{statusLabel(occurrence.computed_status)}</span>
      {occurrence.status !== 'pending' && (
        <span className="muted"> · {occurrence.status}</span>
      )}
    </li>
  )
}
