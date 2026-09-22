import { Link, Navigate, Route, Routes, useNavigate } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { api, type User } from './api'
import { clearNativeSession, detectPlatform, isNativePlatform } from './platform'
import { registerCurrentDevice } from './native'
import { ActivityFormPage } from './pages/ActivityFormPage'
import { ActivityPage } from './pages/ActivityPage'
import { AuthPage } from './pages/AuthPage'
import { ContactsPage } from './pages/ContactsPage'
import { DashboardPage } from './pages/DashboardPage'
import { DevicesPage } from './pages/DevicesPage'
import { QuickCapturePage } from './pages/QuickCapturePage'
import { SearchPage } from './pages/SearchPage'
import { ShoppingPage } from './pages/ShoppingPage'
import { WidgetPage } from './pages/WidgetPage'

export function App() {
  const [user, setUser] = useState<User | null>(null)
  const [ready, setReady] = useState(false)
  const [theme, setTheme] = useState(() => localStorage.getItem('theme') ?? 'system')
  const [offline, setOffline] = useState(!navigator.onLine)
  const navigate = useNavigate()
  const platform = detectPlatform()

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

  useEffect(() => {
    if (!user) return
    void registerCurrentDevice({
      name: platform === 'web' ? 'Web browser' : `${platform} app`,
    }).catch(() => undefined)

    let cleanup: (() => void) | undefined
    if (platform === 'windows') {
      void import('./windowsShell').then(async (module) => {
        cleanup = await module.initWindowsShell((path) => navigate(path))
      })
    }
    if (platform === 'android') {
      void import('./androidShell').then((module) => module.initAndroidShell((path) => navigate(path)))
    }
    return () => cleanup?.()
  }, [user, platform, navigate])

  async function logout() {
    await api('/api/logout', { method: 'POST' }).catch(() => undefined)
    clearNativeSession()
    setUser(null)
  }

  if (!ready) {
    return <p className="center">Loading…</p>
  }

  return (
    <>
      {offline && <p className="banner">You are offline. Saving needs a connection.</p>}
      {user && (
        <header className="top">
          <Link to="/" className="brand">GIT Life</Link>
          <nav>
            <Link to="/activities/new">Add</Link>
            <Link to="/capture">Capture</Link>
            <Link to="/contacts">People</Link>
            <Link to="/shopping">Shop</Link>
            <Link to="/search">Search</Link>
            <Link to="/devices">Devices</Link>
            {isNativePlatform() && <Link to="/widget">Widget</Link>}
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
          <Route path="/capture" element={user ? <QuickCapturePage /> : <Navigate to="/login" />} />
          <Route path="/widget" element={user ? <WidgetPage /> : <Navigate to="/login" />} />
          <Route path="/devices" element={user ? <DevicesPage /> : <Navigate to="/login" />} />
          <Route path="/contacts" element={user ? <ContactsPage /> : <Navigate to="/login" />} />
          <Route path="/shopping" element={user ? <ShoppingPage /> : <Navigate to="/login" />} />
          <Route path="/search" element={user ? <SearchPage /> : <Navigate to="/login" />} />
          <Route path="/activities/new" element={user ? <ActivityFormPage /> : <Navigate to="/login" />} />
          <Route path="/activities/:id" element={user ? <ActivityPage /> : <Navigate to="/login" />} />
          <Route path="/activities/:id/edit" element={user ? <ActivityFormPage /> : <Navigate to="/login" />} />
        </Routes>
      </main>
    </>
  )
}
