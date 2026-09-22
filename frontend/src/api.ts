const API = import.meta.env.VITE_API_URL ?? ''

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

function xsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : null
}

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  if (!navigator.onLine) {
    throw new ApiError(0, 'You appear to be offline. This action needs a connection.')
  }

  let response: Response
  try {
    await fetch(`${API}/sanctum/csrf-cookie`, { credentials: 'include' })
    const headers = new Headers(options.headers)
    headers.set('Accept', 'application/json')
    if (options.body && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json')
    }
    const token = xsrfToken()
    if (token) {
      headers.set('X-XSRF-TOKEN', token)
    }
    response = await fetch(`${API}${path}`, { ...options, headers, credentials: 'include' })
  } catch {
    throw new ApiError(0, 'The server could not be reached. Check that the API is running.')
  }

  if (response.status === 204) {
    return undefined as T
  }

  const body = await response.json().catch(() => ({}))
  if (!response.ok) {
    throw new ApiError(response.status, body.message ?? 'Something went wrong.', body.errors ?? {})
  }

  return body as T
}

export type User = {
  id: string
  name: string
  email: string
  timezone: string
  currency: string
  theme: string
}

export type Occurrence = {
  id: string
  activity_id: string
  due_at: string
  due_local_date: string
  status: string
  computed_status: string
  completed_at: string | null
  snoozed_until: string | null
}

export type Activity = {
  id: string
  type: 'payment' | 'task'
  title: string
  description: string | null
  category: string | null
  priority: string
  timezone: string
  location: string | null
  notes: string | null
  rrule: string | null
  reminder_offsets_minutes: number[]
  payment: {
    amount_minor: number
    currency: string
    amount_label: string
    amount_display: string
    payment_category: string | null
    payment_method: string | null
    account_reference: string | null
  } | null
  occurrences: Occurrence[]
}

export type DashboardItem = {
  occurrence_id: string
  activity_id: string
  type: string
  title: string
  due_at: string
  due_local_date: string
  status: string
  computed_status: string
  amount_minor: number | null
  currency: string | null
  amount_label: string | null
}

export type Dashboard = {
  today: DashboardItem[]
  due_today: DashboardItem[]
  upcoming: DashboardItem[]
  payments: DashboardItem[]
  tasks: DashboardItem[]
}

export function nairaToKobo(value: string): number {
  const normalized = value.replace(/,/g, '').trim()
  if (!/^\d+(\.\d{0,2})?$/.test(normalized)) {
    return Number.NaN
  }
  const [major, minor = ''] = normalized.split('.')
  return Number(major) * 100 + Number((minor + '00').slice(0, 2))
}

export function koboToNairaInput(amountMinor: number): string {
  const major = Math.floor(amountMinor / 100)
  const minor = amountMinor % 100
  return minor === 0 ? String(major) : `${major}.${String(minor).padStart(2, '0')}`
}
