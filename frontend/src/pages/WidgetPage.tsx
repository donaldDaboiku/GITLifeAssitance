import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError, type DashboardItem } from '../api'
import { formatDayFirst, formatNaira, statusLabel } from '../format'
import { getWidgetMode, setWidgetMode, type WidgetMode } from '../platform'

export function WidgetPage() {
  const [mode, setMode] = useState<WidgetMode>(getWidgetMode())
  const [items, setItems] = useState<DashboardItem[]>([])
  const [next, setNext] = useState<DashboardItem | null>(null)
  const [payment, setPayment] = useState<DashboardItem | null>(null)
  const [error, setError] = useState('')

  async function load() {
    try {
      const data = await api<{
        today: DashboardItem[]
        upcoming: DashboardItem[]
        payments: DashboardItem[]
      }>('/api/dashboard')
      setItems(data.today)
      setNext(data.upcoming[0] ?? data.today[0] ?? null)
      setPayment(data.payments[0] ?? null)
      setError('')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not load widget.')
    }
  }

  useEffect(() => {
    void load()
    const timer = window.setInterval(() => void load(), 60_000)
    return () => window.clearInterval(timer)
  }, [])

  function switchMode(nextMode: WidgetMode) {
    setMode(nextMode)
    setWidgetMode(nextMode)
  }

  return (
    <div className="stack" style={{ padding: 8 }}>
      <div className="row">
        <strong>GIT Life</strong>
        <div className="actions">
          <button type="button" className={mode === 'compact' ? 'on' : ''} onClick={() => switchMode('compact')}>Compact</button>
          <button type="button" className={mode === 'expanded' ? 'on' : ''} onClick={() => switchMode('expanded')}>Expanded</button>
          <Link className="button" to="/capture">Quick Add</Link>
        </div>
      </div>
      {error && <p className="error">{error}</p>}
      {mode === 'compact' ? (
        <section className="card">
          <h2>Today</h2>
          {items.length === 0 && <p className="muted">Nothing due.</p>}
          <ul className="list">
            {items.slice(0, 4).map((item) => (
              <li key={item.occurrence_id}>
                <Link to={`/activities/${item.activity_id}`}>{item.title}</Link>
                <span className={`pill ${item.computed_status}`}>{statusLabel(item.computed_status)}</span>
              </li>
            ))}
          </ul>
          {next && <p className="muted">Next: {next.title} · {formatDayFirst(next.due_local_date)}</p>}
          {payment && <p className="muted">Payment: {payment.title}{payment.amount_minor != null ? ` · Expected ${formatNaira(payment.amount_minor)}` : ''}</p>}
        </section>
      ) : (
        <>
          <Section title="Today" items={items} />
          <Section title="Payments" items={payment ? [payment] : []} />
          <Section title="Upcoming" items={next ? [next] : []} />
          <p className="muted"><Link to="/">Open full app</Link> · <Link to="/capture">AI assistant later (Phase 5)</Link></p>
        </>
      )}
    </div>
  )
}

function Section({ title, items }: { title: string; items: DashboardItem[] }) {
  return (
    <section className="card">
      <h2>{title}</h2>
      {items.length === 0 && <p className="muted">None.</p>}
      <ul className="list">
        {items.map((item) => (
          <li key={`${title}-${item.occurrence_id}`}>
            <Link to={`/activities/${item.activity_id}`}>
              <strong>{item.title}</strong>
            </Link>
            <p className="muted">{formatDayFirst(item.due_local_date)}</p>
          </li>
        ))}
      </ul>
    </section>
  )
}
