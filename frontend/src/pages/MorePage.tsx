import { Link } from 'react-router-dom'
import { isNativePlatform } from '../platform'

export function MorePage({
  theme,
  onTheme,
  onLogout,
}: {
  theme: string
  onTheme: () => void
  onLogout: () => void
}) {
  return (
    <div className="stack more-page">
      <header className="page-hero">
        <p className="eyebrow">GIT Life</p>
        <h1>More</h1>
        <p className="muted">People, shopping, settings, and tools.</p>
      </header>

      <section className="card menu-list">
        <Link to="/contacts">People</Link>
        <Link to="/shopping">Shopping</Link>
        <Link to="/assistant">Assistant</Link>
        <Link to="/activities/new">Add activity (form)</Link>
        <Link to="/devices">Devices</Link>
        <Link to="/settings">Settings & privacy</Link>
        {isNativePlatform() && <Link to="/widget">Desktop widget</Link>}
      </section>

      <section className="card menu-list">
        <button type="button" className="menu-btn" onClick={onTheme}>
          Theme: {theme === 'dark' ? 'Dark' : theme === 'light' ? 'Light' : 'System'}
        </button>
        <button type="button" className="menu-btn danger-text" onClick={() => void onLogout()}>
          Log out
        </button>
      </section>
    </div>
  )
}
