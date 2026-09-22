import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError, type Activity } from '../api'
import { formatDayFirst, statusLabel } from '../format'

export function ActivityPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [activity, setActivity] = useState<Activity | null>(null)
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
    await api(`/api/occurrences/${occurrenceId}/${action}`, {
      method: 'POST',
      body: body ? JSON.stringify(body) : undefined,
    })
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
      {activity.rrule && <p className="muted">Repeats: {activity.rrule}</p>}
      {activity.notes && <p>{activity.notes}</p>}
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
                <button type="button" onClick={() => void act(occurrence.id, 'snooze', { preset: 'tomorrow' })}>Snooze until tomorrow</button>
                <button type="button" onClick={() => void act(occurrence.id, 'skip')}>Skip</button>
              </div>
            )}
          </li>
        ))}
      </ul>
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
