import type { OccupiedSeatInfo, SeatGridTeam } from '~/types/api'
import { toPersianDigits } from '~/utils/jalali'

export interface SeatTeamMember {
  seat_label: string
  seat_number: number
  username: string
  cod_id: string
  is_me: boolean
}

export function useSeatTeamInfo(
  teamsGrid: Ref<SeatGridTeam[]>,
  occupiedSeats: Ref<Record<string | number, OccupiedSeatInfo>>,
  mySeatNumber: Ref<number | null | undefined>,
  myUserId: Ref<number | null | undefined>,
  myTeamOverride?: Ref<number | null | undefined>,
) {
  const myTeamNumber = computed(() => {
    if (myTeamOverride?.value) {
      return myTeamOverride.value
    }

    const seat = mySeatNumber.value
    if (!seat) return null

    const row = teamsGrid.value.find((team) =>
      team.slots.some((slot) => slot.seat_number === seat),
    )

    return row?.team ?? null
  })

  const teamMembers = computed((): SeatTeamMember[] => {
    const team = myTeamNumber.value
    if (!team) return []

    const row = teamsGrid.value.find((item) => item.team === team)
    if (!row) return []

    return row.slots
      .map((slot) => {
        const occupied = occupiedSeats.value[slot.seat_number]
          ?? occupiedSeats.value[String(slot.seat_number)]
        if (!occupied) return null

        const userId = occupied.user?.id
        const isMe = (userId != null && userId === myUserId.value)
          || slot.seat_number === mySeatNumber.value

        return {
          seat_label: slot.label,
          seat_number: slot.seat_number,
          username: occupied.user?.username || '—',
          cod_id: occupied.user?.cod_id || '—',
          is_me: isMe,
        }
      })
      .filter((member): member is SeatTeamMember => member !== null)
  })

  const teammates = computed(() => teamMembers.value.filter((member) => !member.is_me))
  const me = computed(() => teamMembers.value.find((member) => member.is_me) ?? null)
  const myTeamLabel = computed(() =>
    myTeamNumber.value ? `تیم ${toPersianDigits(myTeamNumber.value)}` : null,
  )

  return { myTeamNumber, teamMembers, teammates, me, myTeamLabel }
}
