import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../api'

export function AssistantPage() {
  const [question, setQuestion] = useState('')
  const [answer, setAnswer] = useState('')
  const [tools, setTools] = useState<string[]>([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function ask(event: FormEvent) {
    event.preventDefault()
    if (!question.trim()) return
    setLoading(true)
    setError('')
    try {
      const body = await api<{ answer: string; tools_used: string[] }>('/api/assistant/ask', {
        method: 'POST',
        body: JSON.stringify({ question: question.trim() }),
      })
      setAnswer(body.answer)
      setTools(body.tools_used)
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not ask.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="stack">
      <div className="row">
        <h1>Assistant</h1>
        <Link to="/capture">Quick capture</Link>
      </div>
      <p className="muted">Answers use your data only through server tools (list payments, activities, search). Notes are treated as data, not instructions.</p>
      <form className="card" onSubmit={(event) => void ask(event)}>
        <label>
          Ask
          <textarea
            rows={3}
            value={question}
            onChange={(event) => setQuestion(event.target.value)}
            placeholder="What payments do I have?"
            required
          />
        </label>
        {error && <p className="error">{error}</p>}
        <button type="submit" disabled={loading}>{loading ? 'Thinking…' : 'Ask'}</button>
      </form>
      {answer && (
        <section className="card">
          <h2>Answer</h2>
          {tools.length > 0 && <p className="muted">Tools: {tools.join(', ')}</p>}
          <pre className="assistant-answer">{answer}</pre>
        </section>
      )}
    </div>
  )
}
