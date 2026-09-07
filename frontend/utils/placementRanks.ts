export interface RankedLike {
  rank: number | null
}

export function compactRankMap(rows: RankedLike[]): Map<number, number> {
  const map = new Map<number, number>()
  let next = 1
  for (const row of rows) {
    const rank = row.rank && row.rank > 0 ? row.rank : 0
    if (rank < 1) continue
    if (!map.has(rank)) map.set(rank, next++)
  }
  return map
}

export function placementRankFor(rows: RankedLike[], index: number): number {
  const row = rows[index]
  if (!row?.rank || row.rank < 1) return 0
  return compactRankMap(rows).get(row.rank) ?? 0
}

export function teamIndexRange(rows: RankedLike[], index: number): { start: number; end: number } {
  const rank = rows[index]?.rank
  if (!rank || rank < 1) return { start: index, end: index }
  let start = index
  let end = index
  while (start > 0 && rows[start - 1].rank === rank) start--
  while (end < rows.length - 1 && rows[end + 1].rank === rank) end++
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

export function moveGroupTo<T extends RankedLike>(rows: T[], fromIndex: number, toIndex: number): T[] {
  if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= rows.length || toIndex >= rows.length) {
    return rows
  }

  const from = teamIndexRange(rows, fromIndex)
  const to = teamIndexRange(rows, toIndex)
  if (from.start === to.start) return rows

  const dest = from.start < to.start ? to.end + 1 : to.start
  return moveSlice(rows, from.start, from.end, dest)
}
