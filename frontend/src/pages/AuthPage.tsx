import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError, type User } from '../api'
import { detectPlatform, isNativePlatform, setAccessToken, setDeviceId } from '../platform'
import { registerCurrentDevice } from '../native'

export function AuthPage({ mode, onUser }: { mode: 'login' | 'register'; onUser: (user: User) => void }) {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState('')

  async function submit(event: FormEvent) {
    event.preventDefault()
    setError('')
    try {
      if (mode === 'login' && isNativePlatform()) {
        const platform = detectPlatform()
        const response = await api<{
          data: User
          token: string
          device: { id: string }
        }>('/api/token-login', {
          method: 'POST',
          body: JSON.stringify({
            email,
            password,
            device_name: `${platform} app`,
            device_type: platform,
            app_version: import.meta.env.VITE_APP_VERSION ?? '0.3.0',
          }),
        })
        setAccessToken(response.token)
        setDeviceId(response.device.id)
        onUser(response.data)
        return
      }

      const path = mode === 'login' ? '/api/login' : '/api/register'
      const body = mode === 'login'
        ? { email, password }
        : { name, email, password, password_confirmation: passwordConfirmation }
      const response = await api<{ data: User }>(path, { method: 'POST', body: JSON.stringify(body) })
      if (mode === 'register' || !isNativePlatform()) {
        await registerCurrentDevice({ name: 'Web browser' }).catch(() => undefined)
      }
      onUser(response.data)
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Could not sign in.')
    }
  }

  return (
    <form className="card narrow" onSubmit={(event) => void submit(event)}>
      <h1>{mode === 'login' ? 'Welcome back' : 'Create your account'}</h1>
      <p className="muted">Remember. Plan. Act.</p>
      {mode === 'register' && (
        <label>Name<input value={name} onChange={(event) => setName(event.target.value)} required /></label>
      )}
      <label>Email<input type="email" value={email} onChange={(event) => setEmail(event.target.value)} required /></label>
      <label>Password<input type="password" value={password} onChange={(event) => setPassword(event.target.value)} required minLength={8} /></label>
      {mode === 'register' && (
        <label>Confirm password<input type="password" value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} required /></label>
      )}
      {error && <p className="error">{error}</p>}
      <button type="submit">{mode === 'login' ? 'Log in' : 'Sign up'}</button>
      {mode === 'login'
        ? <p>New here? <Link to="/register">Create an account</Link></p>
        : <p>Already have an account? <Link to="/login">Log in</Link></p>}
    </form>
  )
}
