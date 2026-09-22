import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError, type Contact } from '../api'
import { formatDayFirst } from '../format'

export function ContactsPage() {
  const [contacts, setContacts] = useState<Contact[]>([])
  const [name, setName] = useState('')
  const [relationship, setRelationship] = useState('')
  const [birthday, setBirthday] = useState('')
  const [phone, setPhone] = useState('')
  const [error, setError] = useState('')

  async function load() {
    const body = await api<{ data: Contact[] }>('/api/contacts')
    setContacts(body.data)
  }

  useEffect(() => {
    void load().catch((caught) => setError(caught instanceof ApiError ? caught.message : 'Could not load contacts.'))
  }, [])

  async function submit(event: FormEvent) {
    event.preventDefault()
    setError('')
    try {
      await api('/api/contacts', {
        method: 'POST',
        body: JSON.stringify({
          name,
          relationship: relationship || null,
          birthday: birthday || null,
          phone: phone || null,
        }),
      })
      setName('')
      setRelationship('')
      setBirthday('')
      setPhone('')
      await load()
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not save contact.')
    }
  }

  return (
    <div className="stack">
      <div className="row">
        <h1>People</h1>
        <Link to="/">Home</Link>
      </div>
      <form className="card" onSubmit={(event) => void submit(event)}>
        <h2>Add person</h2>
        <label>Name<input value={name} onChange={(event) => setName(event.target.value)} required /></label>
        <label>Relationship<input value={relationship} onChange={(event) => setRelationship(event.target.value)} /></label>
        <label>Phone<input value={phone} onChange={(event) => setPhone(event.target.value)} /></label>
        <label>Birthday<input type="date" value={birthday} onChange={(event) => setBirthday(event.target.value)} /></label>
        {error && <p className="error">{error}</p>}
        <button type="submit">Save</button>
      </form>
      <section className="card">
        <ul className="list">
          {contacts.map((contact) => (
            <li key={contact.id}>
              <strong>{contact.name}</strong>
              {contact.relationship && <span className="muted"> · {contact.relationship}</span>}
              {contact.birthday && <p className="muted">Birthday {formatDayFirst(contact.birthday)}</p>}
              {contact.phone && <p className="muted">{contact.phone}</p>}
            </li>
          ))}
          {contacts.length === 0 && <p className="muted">No people yet.</p>}
        </ul>
      </section>
    </div>
  )
}
