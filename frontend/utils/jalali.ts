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

function tehranGregorianParts(iso: string): { gy: number; gm: number; gd: number; hour: number; minute: number } | null {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return null

  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: TEHRAN_TZ,
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: 'numeric',
    minute: 'numeric',
    hour12: false,
  }).formatToParts(date)

  const get = (type: Intl.DateTimeFormatPartTypes) =>
    Number(parts.find((part) => part.type === type)?.value ?? 0)

  return {
    gy: get('year'),
    gm: get('month'),
    gd: get('day'),
    hour: get('hour'),
    minute: get('minute'),
  }
}

export function isoToJalaliParts(iso?: string | null): JalaliDateTimeParts | null {
  if (!iso) return null

  const normalized = iso.includes('T') ? iso : iso.replace(' ', 'T')
  const gregorian = tehranGregorianParts(normalized)
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
  return [parts.jy, parts.jm, parts.jd, parts.hour, parts.minute].every(
    (value) => Number.isFinite(value) && value > 0,
  )
    && parts.jm <= 12
    && parts.jd <= 31
    && parts.hour <= 23
    && parts.minute <= 59
}

export function jalaliPartsToApiDateTime(parts: JalaliDateTimeParts): string {
  if (!isValidJalaliParts(parts)) return ''
  const [gy, gm, gd] = jalaliToGregorian(parts.jy, parts.jm, parts.jd)
  if (gm < 1 || gm > 12 || gd < 1 || gd > 31) return ''
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${gy}-${pad(gm)}-${pad(gd)} ${pad(parts.hour)}:${pad(parts.minute)}:00`
}

export function toPersianDigits(value: string | number): string {
  return String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)])
}

export function formatJalaliLabel(parts: JalaliDateTimeParts): string {
  const pad = (value: number) => String(value).padStart(2, '0')
  return toPersianDigits(`${parts.jy}/${pad(parts.jm)}/${pad(parts.jd)} ${pad(parts.hour)}:${pad(parts.minute)}`)
}
