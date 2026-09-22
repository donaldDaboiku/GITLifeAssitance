import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError, type Activity } from '../api'
import { formatDayFirst, statusLabel } from '../format'

type Offer = { key: string; label: string }

export function ActivityPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [activity, setActivity] = useState<Activity | null>(null)
  const [offers, setOffers] = useState<Offer[] | null>(null)
  const [selected, setSelected] = useState<string[]>([])
  const [nextVisitOn, setNextVisitOn] = useState('')
  const [error, setError] = useState('')

  async function load() {
    try {
      const body = await api<{ data: Activity }>(`/api/activities/${id}`)
      setActivity(body.data)
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not load this item.')
    }
  }

  useEffect(() => {
    void load()
  }, [id])

  async function act(occurrenceId: string, action: string, body?: object) {
    const response = await api<{ data: Activity; follow_up_offers?: Offer[] | null }>(`/api/occurrences/${occurrenceId}/${action}`, {
      method: 'POST',
      body: body ? JSON.stringify(body) : undefined,
    })
    if (action === 'complete' && response.follow_up_offers) {
      setOffers(response.follow_up_offers)
      setSelected(response.follow_up_offers.map((offer) => offer.key))
    }
    await load()
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

  if (!activity) {
    return <p className="center">{error || 'Loading…'}</p>
  }

  return (
    <article className="card">
      <p className={`pill ${activity.type}`}>{activity.type}</p>
      <h1>{activity.title}</h1>
      {activity.payment && (
        <p className="amount">Expected {activity.payment.amount_display}</p>
      )}
      {activity.payment?.payment_category && <p className="muted">Category: {activity.payment.payment_category}</p>}
      {activity.payment?.payment_method && <p className="muted">Method: {activity.payment.payment_method}</p>}
      {activity.location && <p className="muted">{activity.location}</p>}
      {activity.rrule && <p className="muted">Repeats: {activity.rrule}</p>}
      {activity.notes && <p>{activity.notes}</p>}
      {activity.links && activity.links.length > 0 && (
        <div>
          <h3>Linked</h3>
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
        </div>
      )}
      {error && <p className="error">{error}</p>}
      <ul className="list">
        {activity.occurrences.map((occurrence) => (
          <li key={occurrence.id}>
            <strong>{formatDayFirst(occurrence.due_local_date)}</strong>
            <span className={`pill ${occurrence.computed_status}`}>{statusLabel(occurrence.computed_status)}</span>
            {occurrence.status === 'pending' && (
              <div className="actions">
                {activity.type === 'payment'
                  ? <button type="button" onClick={() => void act(occurrence.id, 'pay')}>Mark paid</button>
                  : <button type="button" onClick={() => void act(occurrence.id, 'complete')}>Done</button>}
                <button type="button" onClick={() => void act(occurrence.id, 'snooze', { preset: 'tomorrow' })}>Snooze</button>
                <button type="button" onClick={() => void act(occurrence.id, 'skip')}>Skip</button>
              </div>
            )}
          </li>
        ))}
      </ul>
      {offers && (
        <section className="card" style={{ marginTop: 12 }}>
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
            <label>Next visit date<input type="date" value={nextVisitOn} onChange={(event) => setNextVisitOn(event.target.value)} /></label>
          )}
          <div className="actions">
            <button type="button" onClick={() => void applyFollowUps()}>Create selected</button>
            <button type="button" onClick={() => setOffers(null)}>Skip</button>
          </div>
        </section>
      )}
      <div className="actions">
        <Link className="button" to={`/activities/${activity.id}/edit`}>Edit</Link>
        <button type="button" className="danger" onClick={() => {
          void api(`/api/activities/${activity.id}`, { method: 'DELETE' })
            .then(() => navigate('/'))
            .catch((caught) => setError(caught instanceof ApiError ? caught.message : 'Could not delete this item.'))
        }}>Delete</button>
      </div>
    </article>
  )
}
