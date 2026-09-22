export function formatNaira(amountMinor: number): string {
  const sign = amountMinor < 0 ? '-' : ''
  const abs = Math.abs(amountMinor)
  const major = Math.floor(abs / 100)
  const minor = String(abs % 100).padStart(2, '0')
  return `${sign}₦${major.toLocaleString('en-NG')}.${minor}`
}

export function formatDayFirst(isoDate: string): string {
  const [year, month, day] = isoDate.split('-')
  return `${day}/${month}/${year}`
}

export function statusLabel(status: string): string {
  return status.replaceAll('_', ' ')
}
