export function usePersianDateTime() {
  const timeZone = 'Asia/Tehran'

  function isPersianDisplay(value: string): boolean {
    return /[۰-۹]/.test(value) && value.includes('/')
  }

  function formatDateTime(value?: string | null): string {
    if (!value) return '—'
    if (isPersianDisplay(value)) return value

    const date = new Date(value.includes('T') ? value : value.replace(' ', 'T'))
    if (Number.isNaN(date.getTime())) return value

    return new Intl.DateTimeFormat('fa-IR', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  }

  function formatDate(value?: string | null, display?: string | null): string {
    if (display) {
      const [datePart] = display.split(' ')
      if (datePart) return datePart
    }
    if (!value) return '—'
    if (isPersianDisplay(value)) return value.split(' ')[0] || value

    const date = new Date(value.includes('T') ? value : value.replace(' ', 'T'))
    if (Number.isNaN(date.getTime())) return '—'

    return new Intl.DateTimeFormat('fa-IR', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(date)
  }

  function formatTime(value?: string | null, display?: string | null): string {
    if (display) {
      const parts = display.split(' ')
      if (parts[1]) return parts[1]
    }
    if (!value) return '—'

    const date = new Date(value.includes('T') ? value : value.replace(' ', 'T'))
    if (Number.isNaN(date.getTime())) return '—'

    return new Intl.DateTimeFormat('fa-IR', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  }

  return { formatDateTime, formatDate, formatTime, timeZone }
}
