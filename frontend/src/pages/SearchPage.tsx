import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError, type SearchHit } from '../api'

export function SearchPage() {
  const [query, setQuery] = useState('')
  const [hits, setHits] = useState<SearchHit[]>([])
  const [error, setError] = useState('')

  async function submit(event: FormEvent) {
    event.preventDefault()
    setError('')
    try {
      const body = await api<{ data: SearchHit[] }>(`/api/search?q=${encodeURIComponent(query)}`)
      setHits(body.data)
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Search failed.')
    }
  }

  function hrefFor(hit: SearchHit): string {
    if (hit.kind === 'contact') return '/contacts'
    if (hit.kind === 'shopping_item') return '/shopping'
    return `/activities/${hit.id}`
  }

  return (
    <div className="stack">
      <div className="row">
        <h1>Search</h1>
        <Link to="/">Home</Link>
      </div>
      <form className="card" onSubmit={(event) => void submit(event)}>
        <label>Find anything
          <input className="search-box" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="toner, birthday, rice" required />
        </label>
        {error && <p className="error">{error}</p>}
        <button type="submit">Search</button>
      </form>
      <section className="card">
        <ul className="list">
          {hits.map((hit) => (
            <li key={`${hit.kind}-${hit.id}`}>
              <Link to={hrefFor(hit)}>
                <strong>{hit.title}</strong>
                <span className={`pill ${hit.type}`}>{hit.kind}</span>
              </Link>
              {hit.subtitle && <p className="muted">{hit.subtitle}</p>}
            </li>
          ))}
          {hits.length === 0 && <p className="muted">No results yet.</p>}
        </ul>
      </section>
    </div>
  )
}
