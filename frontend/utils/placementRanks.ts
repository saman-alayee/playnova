export interface RankedLike {
  rank: number | null
  seat_number?: number | null
}

export function groupKey(row: RankedLike, index: number, seatMode = 1): string {
  if (row.rank && row.rank > 0) return `r-${row.rank}`
  const mode = Math.max(1, seatMode)
  const seat = row.seat_number && row.seat_number > 0 ? row.seat_number : 0
  if (seat) return `t-${Math.ceil(seat / mode)}`
  return `i-${index}`
}

export function placementRanks(rows: RankedLike[], seatMode = 1): number[] {
  const ranks = Array.from({ length: rows.length }, () => 0)
  const rankMap = new Map<number, number>()
  const unrankedTeamMap = new Map<string, number>()
  let next = 1
  const mode = Math.max(1, seatMode)

  rows.forEach((row, index) => {
    if (row.rank && row.rank > 0) {
      if (!rankMap.has(row.rank)) rankMap.set(row.rank, next++)
      ranks[index] = rankMap.get(row.rank) ?? 0
      return
    }

    const key = groupKey(row, index, mode)
    if (!unrankedTeamMap.has(key)) unrankedTeamMap.set(key, next++)
    ranks[index] = unrankedTeamMap.get(key) ?? 0
  })

  return ranks
}

export function compactRankMap(rows: RankedLike[], seatMode = 1): Map<number, number> {
  const placements = placementRanks(rows, seatMode)
  const map = new Map<number, number>()
  rows.forEach((row, index) => {
    if (row.rank && row.rank > 0) {
      map.set(row.rank, placements[index] ?? 0)
    }
  })
  return map
}

export function placementRankFor(rows: RankedLike[], index: number, seatMode = 1): number {
  return placementRanks(rows, seatMode)[index] ?? 0
}

export function rosterSlotShare(teamTotal: number, seatMode = 1): number {
  const slots = Math.max(1, seatMode)
  return Math.floor(Math.max(0, Math.round(teamTotal)) / slots)
}

export function teamIndexRange(rows: RankedLike[], index: number, seatMode = 1): { start: number; end: number } {
  const key = groupKey(rows[index] ?? { rank: null }, index, seatMode)
  let start = index
  let end = index
  while (start > 0 && groupKey(rows[start - 1], start - 1, seatMode) === key) start--
  while (end < rows.length - 1 && groupKey(rows[end + 1], end + 1, seatMode) === key) end++
  return { start, end }
}

export function moveSlice<T>(rows: T[], fromStart: number, fromEnd: number, destIndex: number): T[] {
  const next = [...rows]
  const count = fromEnd - fromStart + 1
  if (count <= 0 || fromStart < 0 || fromEnd >= next.length) return next

  const slice = next.splice(fromStart, count)
  let dest = destIndex
  if (dest > fromStart) dest -= count
  dest = Math.max(0, Math.min(next.length, dest))
  next.splice(dest, 0, ...slice)
  return next
}

export function moveGroupTo<T extends RankedLike>(rows: T[], fromIndex: number, toIndex: number, seatMode = 1): T[] {
  if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= rows.length || toIndex >= rows.length) {
    return rows
  }

  const from = teamIndexRange(rows, fromIndex, seatMode)
  const to = teamIndexRange(rows, toIndex, seatMode)
  if (from.start === to.start) return rows

  const dest = from.start < to.start ? to.end + 1 : to.start
  return moveSlice(rows, from.start, from.end, dest)
}
