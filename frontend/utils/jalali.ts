const TEHRAN_TZ = 'Asia/Tehran'

export interface JalaliDateTimeParts {
  jy: number
  jm: number
  jd: number
  hour: number
  minute: number
}

export function gregorianToJalali(gy: number, gm: number, gd: number): [number, number, number] {
  const gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
  const gy2 = gm > 2 ? gy + 1 : gy
  let days =
    355666 +
    365 * gy +
    Math.floor((gy2 + 3) / 4) -
    Math.floor((gy2 + 99) / 100) +
    Math.floor((gy2 + 399) / 400) +
    gd +
    gDaysInMonth[gm - 1]

  let jy = -1595 + 33 * Math.floor(days / 12053)
  days %= 12053
  jy += 4 * Math.floor(days / 1461)
  days %= 1461

  if (days > 365) {
    jy += Math.floor((days - 1) / 365)
    days = (days - 1) % 365
  }

  if (days < 186) {
    return [jy, 1 + Math.floor(days / 31), 1 + (days % 31)]
  }

  return [jy, 7 + Math.floor((days - 186) / 30), 1 + ((days - 186) % 30)]
}

export function jalaliToGregorian(jy: number, jm: number, jd: number): [number, number, number] {
  jy += 1595
  let days =
    -355668 +
    365 * jy +
    Math.floor(jy / 33) * 8 +
    Math.floor(((jy % 33) + 3) / 4) +
    jd +
    (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186)

  let gy = 400 * Math.floor(days / 146097)
  days %= 146097

  if (days > 36524) {
    gy += 100 * Math.floor(--days / 36524)
    days %= 36524
    if (days >= 365) days++
  }

  gy += 4 * Math.floor(days / 1461)
  days %= 1461

  if (days > 365) {
    gy += Math.floor((days - 1) / 365)
    days = (days - 1) % 365
  }

  const gDaysInMonth = [0, 31, (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0 ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
  let gm = 0
  while (gm < 13 && days >= gDaysInMonth[gm + 1]) {
    days -= gDaysInMonth[gm + 1]
    gm++
  }

  return [gy, gm + 1, days + 1]
}

function toAsciiDigits(value: string): string {
  return value.replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
    .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
}

function pad2(value: number): string {
  return String(value).padStart(2, '0')
}

function normalizeIsoInstant(raw: string): string {
  let value = raw.trim()
  if (!value.includes('T') && /^\d{4}-\d{2}-\d{2} /.test(value)) {
    value = value.replace(' ', 'T')
  }
  // Laravel toIso8601String: 2026-03-26T18:30:00.000000Z
  value = value.replace(/\.(\d{3})\d+(?=(Z|[+-]\d{2}:?\d{2})?$)/, '.$1')
  return value
}

function instantToTehranParts(iso: string): { gy: number; gm: number; gd: number; hour: number; minute: number } | null {
  const date = new Date(normalizeIsoInstant(iso))
  if (Number.isNaN(date.getTime())) return null

  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: TEHRAN_TZ,
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: 'numeric',
    minute: 'numeric',
    hourCycle: 'h23',
  }).formatToParts(date)

  const get = (type: Intl.DateTimeFormatPartTypes) =>
    Number(parts.find((part) => part.type === type)?.value ?? 0)

  return {
    gy: get('year'),
    gm: get('month'),
    gd: get('day'),
    hour: get('hour') % 24,
    minute: get('minute'),
  }
}

export function parseToTehranGregorian(iso?: string | null): { gy: number; gm: number; gd: number; hour: number; minute: number } | null {
  if (!iso?.trim()) return null

  const ascii = toAsciiDigits(iso.trim())

  const naiveGregorian = ascii.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})(?:[ T](\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?/)
  if (naiveGregorian) {
    const year = Number(naiveGregorian[1])
    const hasTimezone = /Z|[+-]\d{2}:?\d{2}$/.test(ascii) || ascii.includes('T') && /T.*Z|[+-]/.test(ascii)
    if (year >= 1800 && year <= 2100 && !hasTimezone) {
      return {
        gy: year,
        gm: Number(naiveGregorian[2]),
        gd: Number(naiveGregorian[3]),
        hour: Number(naiveGregorian[4] ?? 0) % 24,
        minute: Number(naiveGregorian[5] ?? 0),
      }
    }
  }

  return instantToTehranParts(ascii)
}

export function isoToJalaliParts(iso?: string | null): JalaliDateTimeParts | null {
  if (!iso) return null

  const ascii = toAsciiDigits(iso.trim())
  const jalaliAlready = ascii.match(/^((?:13|14)\d{2})[/-](\d{1,2})[/-](\d{1,2})(?:[ T](\d{1,2}):(\d{1,2}))?/)
  if (jalaliAlready) {
    return {
      jy: Number(jalaliAlready[1]),
      jm: Number(jalaliAlready[2]),
      jd: Number(jalaliAlready[3]),
      hour: Number(jalaliAlready[4] ?? 0),
      minute: Number(jalaliAlready[5] ?? 0),
    }
  }

  const gregorian = parseToTehranGregorian(ascii)
  if (!gregorian) return null

  const [jy, jm, jd] = gregorianToJalali(gregorian.gy, gregorian.gm, gregorian.gd)
  return {
    jy,
    jm,
    jd,
    hour: gregorian.hour,
    minute: gregorian.minute,
  }
}

export function isValidJalaliParts(parts: JalaliDateTimeParts): boolean {
  return Number.isFinite(parts.jy) && parts.jy > 0
    && Number.isFinite(parts.jm) && parts.jm >= 1 && parts.jm <= 12
    && Number.isFinite(parts.jd) && parts.jd >= 1 && parts.jd <= 31
    && Number.isFinite(parts.hour) && parts.hour >= 0 && parts.hour <= 23
    && Number.isFinite(parts.minute) && parts.minute >= 0 && parts.minute <= 59
}

export function jalaliPartsToApiDateTime(parts: JalaliDateTimeParts): string {
  if (!isValidJalaliParts(parts)) return ''
  const [gy, gm, gd] = jalaliToGregorian(parts.jy, parts.jm, parts.jd)
  if (gm < 1 || gm > 12 || gd < 1 || gd > 31) return ''
  return `${gy}-${pad2(gm)}-${pad2(gd)} ${pad2(parts.hour)}:${pad2(parts.minute)}:00`
}

export function toPersianDigits(value: string | number): string {
  return String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)])
}

export function formatJalaliLabel(parts: JalaliDateTimeParts): string {
  return toPersianDigits(`${parts.jy}/${pad2(parts.jm)}/${pad2(parts.jd)} ${pad2(parts.hour)}:${pad2(parts.minute)}`)
}

export function toApiDateTime(iso?: string | null): string {
  const gregorian = parseToTehranGregorian(iso)
  if (!gregorian) return ''
  return `${gregorian.gy}-${pad2(gregorian.gm)}-${pad2(gregorian.gd)} ${pad2(gregorian.hour)}:${pad2(gregorian.minute)}:00`
}

/** API (Gregorian ISO or Tehran local) → picker model (Gregorian YYYY-MM-DD HH:mm). */
export function apiDateTimeToPickerValue(iso?: string | null): string {
  const gregorian = parseToTehranGregorian(iso)
  if (!gregorian) return ''
  return `${gregorian.gy}-${pad2(gregorian.gm)}-${pad2(gregorian.gd)} ${pad2(gregorian.hour)}:${pad2(gregorian.minute)}`
}

/** Picker model → API datetime (Gregorian Tehran, Y-m-d H:i:s). */
export function pickerValueToApiDateTime(value?: string | null): string {
  if (!value?.trim()) return ''

  const ascii = toAsciiDigits(value.trim())
  const match = ascii.match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})(?:[ T](\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/)
  if (!match) return ''

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])
  const hour = Number(match[4] ?? 0)
  const minute = Number(match[5] ?? 0)

  if (year >= 1200 && year <= 1599) {
    return jalaliPartsToApiDateTime({ jy: year, jm: month, jd: day, hour, minute })
  }

  if (year < 1800 || year > 2100 || month < 1 || month > 12 || day < 1 || day > 31) {
    return ''
  }

  return `${year}-${pad2(month)}-${pad2(day)} ${pad2(hour)}:${pad2(minute)}:00`
}
