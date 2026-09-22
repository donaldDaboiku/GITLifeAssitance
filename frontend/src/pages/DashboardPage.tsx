import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError, type Dashboard, type DashboardItem } from '../api'
import { formatDayFirst, formatNaira, statusLabel } from '../format'

const empty: Dashboard = { today: [], due_today: [], upcoming: [], payments: [], tasks: [] }

export function DashboardPage() {
  const [data, setData] = useState<Dashboard>(empty)
  const [error, setError] = useState('')

  async function load() {
    try {
      setData(await api<Dashboard>('/api/dashboard'))
      setError('')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not load the dashboard.')
    }
  }

  useEffect(() => {
    void load()
  }, [])

  return (
    <div className="stack">
      <div className="row">
        <h1>Today</h1>
        <Link className="button" to="/activities/new">Add</Link>
      </div>
      {error && <p className="error">{error}</p>}
      <Section title="Today" items={data.today} empty="Nothing due today." onChange={load} />
      <Section title="Due today" items={data.due_today} empty="No items are due today." onChange={load} />
      <Section title="Upcoming" items={data.upcoming} empty="Nothing coming up." onChange={load} />
      <Section title="Payments" items={data.payments} empty="No expected payments." onChange={load} note="Amounts are expected, not bank transactions." />
      <Section title="Tasks" items={data.tasks} empty="No open tasks." onChange={load} />
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
  async function act(path: string, body?: object) {
    await api(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined })
    await onChange()
  }

  return (
    <div className="actions">
      {item.type === 'payment'
        ? <button type="button" onClick={() => void act(`/api/occurrences/${item.occurrence_id}/pay`)}>Mark paid</button>
        : <button type="button" onClick={() => void act(`/api/occurrences/${item.occurrence_id}/complete`)}>Done</button>}
      <button type="button" onClick={() => void act(`/api/occurrences/${item.occurrence_id}/snooze`, { preset: '1hour' })}>Snooze 1 hour</button>
    </div>
  )
}
