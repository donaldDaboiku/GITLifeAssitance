import { getAccessToken, isNativePlatform } from './platform'

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

  const native = isNativePlatform() || Boolean(getAccessToken())
  let response: Response
  try {
    if (!native) {
      await fetch(`${API}/sanctum/csrf-cookie`, { credentials: 'include' })
    }

    const headers = new Headers(options.headers)
    headers.set('Accept', 'application/json')
    if (options.body && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json')
    }

    const accessToken = getAccessToken()
    if (accessToken) {
      headers.set('Authorization', `Bearer ${accessToken}`)
    } else {
      const token = xsrfToken()
      if (token) {
        headers.set('X-XSRF-TOKEN', token)
      }
    }

    response = await fetch(`${API}${path}`, {
      ...options,
      headers,
      credentials: accessToken ? 'omit' : 'include',
    })
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

export type ActivityType =
  | 'payment'
  | 'task'
  | 'birthday'
  | 'anniversary'
  | 'visit'
  | 'appointment'
  | 'shopping'
  | 'follow_up'
  | 'event'
  | 'maintenance'
  | 'custom'

export type Activity = {
  id: string
  type: ActivityType
  title: string
  description: string | null
  category: string | null
  priority: string
  timezone: string
  location: string | null
  contact_id: string | null
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
  links?: Array<{
    id: string
    relation: string
    child_activity_id: string
    child_title?: string
    child_type?: string
  }>
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

export type ExpectedBucket = {
  amount_minor: number
  currency: string
  label: string
}

export type Dashboard = {
  today: DashboardItem[]
  due_today: DashboardItem[]
  upcoming: DashboardItem[]
  payments: DashboardItem[]
  tasks: DashboardItem[]
  shopping: DashboardItem[]
  events: DashboardItem[]
  follow_ups: DashboardItem[]
  expected_payments: {
    this_week: ExpectedBucket
    this_month: ExpectedBucket
  }
}

export type Contact = {
  id: string
  name: string
  phone: string | null
  email: string | null
  relationship: string | null
  birthday: string | null
  anniversary: string | null
  notes: string | null
}

export type ShoppingList = {
  id: string
  name: string
  notes: string | null
  totals: {
    estimated_minor: number
    actual_minor: number
    remaining_minor: number
    estimated_display: string
    actual_display: string
    remaining_display: string
    label: string
  }
  items: Array<{
    id: string
    name: string
    quantity: number
    unit: string | null
    estimated_price_minor: number | null
    actual_price_minor: number | null
    priority: string
    purchased: boolean
    store: string | null
    notes: string | null
  }>
}

export type SearchHit = {
  kind: string
  id: string
  type: string
  title: string
  subtitle?: string | null
  shopping_list_id?: string
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
