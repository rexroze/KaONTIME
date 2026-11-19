import type { OrderStatus } from './types'

export const ORDER_STATUSES: OrderStatus[] = [
  'Pending',
  'Preparing',
  'Out for Delivery',
  'Delivered',
  'Cancelled'
]

export function money(value: number | null | undefined): string {
  return `₱${Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`
}

export function shortDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }
  return date.toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  })
}

export function isTerminal(status: OrderStatus | string): boolean {
  return status === 'Delivered' || status === 'Cancelled'
}

/** The API only allows the happy path plus cancellation before dispatch. */
export function canMoveTo(current: string, next: string): boolean {
  if (current === next) {
    return true
  }
  if (next === 'Cancelled') {
    return current === 'Pending' || current === 'Preparing'
  }
  const index = ORDER_STATUSES.indexOf(current as OrderStatus)
  return index >= 0 && ORDER_STATUSES[index + 1] === next
}

/**
 * Product images arrive as a bare filename (bundled assets), a path, or an
 * absolute https URL. Absolute URLs must survive the Capacitor WebView, where
 * a leading slash would resolve to the app bundle instead of the host.
 */
export function resolveImage(image: string | null | undefined): string {
  const value = (image || '').trim()
  if (!value) {
    return ''
  }
  if (/^https?:\/\//i.test(value)) {
    return value
  }
  if (value.startsWith('/')) {
    return value
  }
  const name = value.split('/').pop() || ''
  return `/images/${name}`
}

export function errorMessage(error: unknown, fallback: string): string {
  const typed = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const errors = typed.response?.data?.errors
  if (errors) {
    return Object.values(errors).flat().join(' ')
  }
  return typed.response?.data?.message || fallback
}
