export function parseAmount(value: unknown): number {
  if (value === null || value === undefined || value === '') return 0
  if (typeof value === 'number') return Number.isFinite(value) ? value : 0
  const normalized = String(value).replace(/[,،\s]/g, '')
  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? parsed : 0
}

export function formatToman(value: unknown, suffix = true): string {
  const amount = Math.round(parseAmount(value))
  const formatted = amount.toLocaleString('fa-IR')
  return suffix ? `${formatted} تومان` : formatted
}
