import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError, nairaToKobo, type ShoppingList } from '../api'
import { formatNaira } from '../format'

export function ShoppingPage() {
  const [lists, setLists] = useState<ShoppingList[]>([])
  const [name, setName] = useState('Market')
  const [itemName, setItemName] = useState('')
  const [estimated, setEstimated] = useState('')
  const [error, setError] = useState('')

  async function load() {
    const body = await api<{ data: ShoppingList[] }>('/api/shopping-lists')
    setLists(body.data)
  }

  useEffect(() => {
    void load().catch((caught) => setError(caught instanceof ApiError ? caught.message : 'Could not load shopping lists.'))
  }, [])

  async function submit(event: FormEvent) {
    event.preventDefault()
    setError('')
    const estimatedMinor = estimated ? nairaToKobo(estimated) : null
    if (estimated && Number.isNaN(estimatedMinor)) {
      setError('Estimated price must be a valid naira amount.')
      return
    }
    try {
      await api('/api/shopping-lists', {
        method: 'POST',
        body: JSON.stringify({
          name,
          items: itemName
            ? [{ name: itemName, estimated_price_minor: estimatedMinor ?? undefined }]
            : [],
        }),
      })
      setItemName('')
      setEstimated('')
      await load()
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not save list.')
    }
  }

  async function togglePurchased(itemId: string, purchased: boolean, estimatedPrice: number | null) {
    await api(`/api/shopping-items/${itemId}`, {
      method: 'PUT',
      body: JSON.stringify({
        purchased: !purchased,
        actual_price_minor: !purchased ? estimatedPrice ?? undefined : undefined,
      }),
    })
    await load()
  }

  return (
    <div className="stack">
      <div className="row">
        <h1>Shopping</h1>
        <Link to="/">Home</Link>
      </div>
      <form className="card" onSubmit={(event) => void submit(event)}>
        <h2>New list</h2>
        <label>List name<input value={name} onChange={(event) => setName(event.target.value)} required /></label>
        <label>First item<input value={itemName} onChange={(event) => setItemName(event.target.value)} /></label>
        <label>Estimated (₦)<input inputMode="decimal" value={estimated} onChange={(event) => setEstimated(event.target.value)} /></label>
        {error && <p className="error">{error}</p>}
        <button type="submit">Save</button>
      </form>
      {lists.map((list) => (
        <section className="card" key={list.id}>
          <h2>{list.name}</h2>
          <div className="totals">
            <div>Estimated · {list.totals.estimated_display}</div>
            <div>Actual · {list.totals.actual_display}</div>
            <div>Remaining · {list.totals.remaining_display}</div>
          </div>
          <ul className="list">
            {list.items.map((item) => (
              <li key={item.id}>
                <label className="check">
                  <input
                    type="checkbox"
                    checked={item.purchased}
                    onChange={() => void togglePurchased(item.id, item.purchased, item.estimated_price_minor)}
                  />
                  <strong>{item.name}</strong>
                </label>
                <p className="muted">
                  {item.estimated_price_minor != null && `Est. ${formatNaira(item.estimated_price_minor)}`}
                  {item.actual_price_minor != null && ` · Actual ${formatNaira(item.actual_price_minor)}`}
                </p>
              </li>
            ))}
          </ul>
        </section>
      ))}
    </div>
  )
}
