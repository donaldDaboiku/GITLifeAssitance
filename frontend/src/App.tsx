import { Link, Navigate, Route, Routes } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { api, type User } from './api'
import { ActivityFormPage } from './pages/ActivityFormPage'
import { ActivityPage } from './pages/ActivityPage'
import { AuthPage } from './pages/AuthPage'
import { DashboardPage } from './pages/DashboardPage'

export function App() {
  const [user, setUser] = useState<User | null>(null)
  const [ready, setReady] = useState(false)
  const [theme, setTheme] = useState(() => localStorage.getItem('theme') ?? 'system')
  const [offline, setOffline] = useState(!navigator.onLine)

  useEffect(() => {
    document.documentElement.dataset.theme = theme
    localStorage.setItem('theme', theme)
  }, [theme])

  useEffect(() => {
    const on = () => setOffline(false)
    const off = () => setOffline(true)
    window.addEventListener('online', on)
    window.addEventListener('offline', off)
    return () => {
      window.removeEventListener('online', on)
      window.removeEventListener('offline', off)
    }
  }, [])

  useEffect(() => {
    api<{ data: User }>('/api/user')
      .then((body) => setUser(body.data))
      .catch(() => setUser(null))
      .finally(() => setReady(true))
  }, [])

  async function logout() {
    await api('/api/logout', { method: 'POST' })
    setUser(null)
  }

  if (!ready) {
    return <p className="center">Loading…</p>
  }

  return (
    <>
      {offline && <p className="banner">You are offline. Reminders already on this phone still apply once sync arrives in a later phase. Saving needs a connection.</p>}
      {user && (
        <header className="top">
          <Link to="/" className="brand">GIT Life</Link>
          <nav>
            <Link to="/activities/new">Add</Link>
            <button type="button" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}>
              {theme === 'dark' ? 'Light' : 'Dark'}
            </button>
            <button type="button" onClick={() => void logout()}>Log out</button>
          </nav>
        </header>
      )}
      <main>
        <Routes>
          <Route path="/login" element={user ? <Navigate to="/" /> : <AuthPage mode="login" onUser={setUser} />} />
          <Route path="/register" element={user ? <Navigate to="/" /> : <AuthPage mode="register" onUser={setUser} />} />
          <Route path="/" element={user ? <DashboardPage /> : <Navigate to="/login" />} />
          <Route path="/activities/new" element={user ? <ActivityFormPage /> : <Navigate to="/login" />} />
          <Route path="/activities/:id" element={user ? <ActivityPage /> : <Navigate to="/login" />} />
          <Route path="/activities/:id/edit" element={user ? <ActivityFormPage /> : <Navigate to="/login" />} />
        </Routes>
      </main>
    </>
  )
}
