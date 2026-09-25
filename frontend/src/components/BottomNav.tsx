import { NavLink, useLocation } from 'react-router-dom'

const HIDDEN_ON = ['/login', '/register', '/capture', '/widget', '/onboarding']

export function BottomNav() {
  const location = useLocation()
  if (HIDDEN_ON.some((path) => location.pathname === path || location.pathname.startsWith(`${path}/`))) {
    return null
  }

  return (
    <nav className="bottom-nav" aria-label="Main">
      <NavLink to="/" end className={({ isActive }) => (isActive ? 'tab on' : 'tab')}>
        <span className="tab-icon" aria-hidden>⌂</span>
        Home
      </NavLink>
      <NavLink to="/capture" className="tab capture-tab">
        <span className="capture-fab" aria-hidden>+</span>
        Capture
      </NavLink>
      <NavLink to="/search" className={({ isActive }) => (isActive ? 'tab on' : 'tab')}>
        <span className="tab-icon" aria-hidden>⌕</span>
        Search
      </NavLink>
      <NavLink to="/more" className={({ isActive }) => (isActive ? 'tab on' : 'tab')}>
        <span className="tab-icon" aria-hidden>⋯</span>
        More
      </NavLink>
    </nav>
  )
}
