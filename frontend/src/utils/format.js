import { i18n, intlLocale } from '@/i18n'

const t = (...args) => i18n.global.t(...args)

export function formatDate(value, options = { weekday: 'long', day: 'numeric', month: 'long' }) {
  if (!value) return ''
  const date = typeof value === 'string' && value.length === 10 ? new Date(`${value}T00:00:00`) : new Date(value)
  return new Intl.DateTimeFormat(intlLocale(), options).format(date)
}

export function formatShortDate(value) {
  return formatDate(value, { day: 'numeric', month: 'short', year: 'numeric' })
}

/** "07:30" -> "٧:٣٠ ص" */
export function formatTime(value) {
  if (!value) return ''
  const [h, m] = value.split(':').map(Number)
  const date = new Date()
  date.setHours(h, m, 0, 0)
  return new Intl.DateTimeFormat(intlLocale(), { hour: 'numeric', minute: '2-digit' }).format(date)
}

export function formatDateTime(value) {
  if (!value) return ''
  return new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

export function formatMoney(amount) {
  const n = new Intl.NumberFormat(intlLocale(), { maximumFractionDigits: 2 }).format(amount ?? 0)
  return `${n} ${t('common.egp')}`
}

export function formatNumber(value) {
  return new Intl.NumberFormat(intlLocale()).format(value ?? 0)
}

/** Human price label for a trip, according to its cost type. */
export function tripPriceLabel(trip) {
  if (trip.cost_type === 'free') return t('trip.costType.free')
  if (trip.cost_type === 'cost_sharing') return `${formatMoney(trip.estimated_cost_per_passenger)} · ${t('trip.costType.cost_sharing')}`
  return `${formatMoney(trip.price_per_seat)} / ${t('common.seat')}`
}

export function todayISO(offsetDays = 0) {
  const d = new Date()
  d.setDate(d.getDate() + offsetDays)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

export function relativeTime(value) {
  const diff = (new Date(value).getTime() - Date.now()) / 1000
  const rtf = new Intl.RelativeTimeFormat(intlLocale(), { numeric: 'auto' })
  const units = [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60]]
  for (const [unit, seconds] of units) {
    if (Math.abs(diff) >= seconds) return rtf.format(Math.round(diff / seconds), unit)
  }
  return rtf.format(Math.round(diff), 'second')
}
