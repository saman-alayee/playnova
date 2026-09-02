<script setup lang="ts">
import type { OccupiedSeatInfo } from '~/types/api'
import { toPersianDigits } from '~/utils/jalali'

interface GridTeam {
  team: number
  slots: Array<{ seat_number: number; label: string; slot: number }>
}

interface Occupant {
  username?: string | null
  cod_id?: string | null
}

const props = defineProps<{
  teams: GridTeam[]
  occupiedSeats: Record<string | number, OccupiedSeatInfo | Occupant>
  seatMode: number
  interactive?: boolean
  selectedSeat?: number | null
  selectedTeam?: number | null
  teamSelectMode?: boolean
}>()

const emit = defineEmits<{
  select: [seatNumber: number, label: string]
}>()

function occupant(seatNumber: number): Occupant | null {
  const raw = props.occupiedSeats[seatNumber] ?? props.occupiedSeats[String(seatNumber)]
  if (!raw) return null
  if ('user' in raw && raw.user) {
    return { username: raw.user.username, cod_id: raw.user.cod_id }
  }
  return { username: (raw as Occupant).username, cod_id: (raw as Occupant).cod_id }
}

function teamTitle(team: number) {
  return `تیم ${toPersianDigits(team)}`
}

function teamIsFullyEmpty(teamRow: GridTeam): boolean {
  return teamRow.slots.every((slot) => !occupant(slot.seat_number))
}

function slotIsSelected(teamRow: GridTeam, seatNumber: number): boolean {
  if (props.teamSelectMode && props.selectedTeam === teamRow.team) {
    return true
  }

  return props.selectedSeat === seatNumber
}

function avatarLetter(username?: string | null) {
  return (username?.charAt(0) || '?').toUpperCase()
}

function onPick(teamRow: GridTeam, seatNumber: number, label: string) {
  if (!props.interactive) return

  if (props.teamSelectMode) {
    if (!teamIsFullyEmpty(teamRow)) return
    const first = teamRow.slots[0]
    const teamLabel = `${teamTitle(teamRow.team)} — ${teamRow.slots.map((s) => s.label).join(' / ')}`
    emit('select', first.seat_number, teamLabel)
    return
  }

  emit('select', seatNumber, label)
}
</script>

<template>
  <div class="team-grid">
    <div
      v-for="teamRow in teams"
      :key="teamRow.team"
      class="team-card"
      :class="{ 'is-selected': teamSelectMode && selectedTeam === teamRow.team }"
    >
      <div class="team-card__title">{{ teamTitle(teamRow.team) }}</div>
      <div
        class="team-card__slots"
        :style="{ gridTemplateColumns: `repeat(${seatMode}, minmax(0, 1fr))` }"
      >
        <template v-for="slot in teamRow.slots" :key="slot.seat_number">
          <div
            v-if="occupant(slot.seat_number)"
            class="seat-slot seat-slot--taken"
          >
            <div class="seat-slot__top">نفر {{ toPersianDigits(slot.slot) }}</div>
            <div class="seat-slot__avatar">
              {{ avatarLetter(occupant(slot.seat_number)?.username) }}
            </div>
            <div class="seat-slot__user">{{ occupant(slot.seat_number)?.username || '—' }}</div>
            <div class="seat-slot__status seat-slot__status--taken">پر شده</div>
          </div>
          <button
            v-else
            type="button"
            class="seat-slot"
            :class="{ 'is-selected': slotIsSelected(teamRow, slot.seat_number) }"
            :disabled="!interactive || (teamSelectMode && !teamIsFullyEmpty(teamRow))"
            @click="onPick(teamRow, slot.seat_number, slot.label)"
          >
            <div class="seat-slot__top">نفر {{ toPersianDigits(slot.slot) }}</div>
            <svg class="seat-slot__icon" viewBox="0 0 64 64" fill="none" aria-hidden="true">
              <ellipse cx="32" cy="54" rx="18" ry="4" fill="rgba(212,175,55,0.2)" />
              <circle cx="32" cy="22" r="11" fill="#64748b" />
              <path d="M14 52c2-12 10-18 18-18s16 6 18 18" fill="#475569" />
            </svg>
            <span class="seat-slot__code">{{ slot.label }}</span>
            <div class="seat-slot__status">خالی — کلیک</div>
          </button>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.team-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  direction: ltr;
}

@media (min-width: 640px) {
  .team-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
  }
}

@media (min-width: 1024px) {
  .team-grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }
}

.team-card {
  position: relative;
  background: linear-gradient(180deg, rgba(24, 20, 12, 0.95) 0%, rgba(10, 9, 8, 0.98) 100%);
  border: 1px solid rgba(212, 175, 55, 0.55);
  box-shadow: inset 0 0 0 1px rgba(212, 175, 55, 0.12), 0 8px 24px rgba(0, 0, 0, 0.45);
  padding: 0.45rem 0.5rem 0.55rem;
}

.team-card.is-selected {
  border-color: #d4af37;
  box-shadow: 0 0 0 1px rgba(212, 175, 55, 0.45);
}

.team-card::before,
.team-card::after {
  content: '';
  position: absolute;
  width: 14px;
  height: 14px;
  border-color: rgba(212, 175, 55, 0.85);
  border-style: solid;
  pointer-events: none;
}

.team-card::before {
  top: -1px;
  left: -1px;
  border-width: 2px 0 0 2px;
}

.team-card::after {
  bottom: -1px;
  right: -1px;
  border-width: 0 2px 2px 0;
}

.team-card__title {
  text-align: center;
  color: #d4af37;
  font-weight: 800;
  font-size: 0.95rem;
  margin-bottom: 0.45rem;
}

.team-card__slots {
  display: grid;
  gap: 0.35rem;
  direction: ltr;
}

.seat-slot {
  position: relative;
  min-height: 88px;
  border: 1px solid rgba(212, 175, 55, 0.35);
  background: rgba(0, 0, 0, 0.35);
  padding: 0.35rem 0.25rem 0.5rem;
  text-align: center;
  transition: border-color 0.2s, background 0.2s, transform 0.15s;
}

button.seat-slot {
  cursor: pointer;
  width: 100%;
}

button.seat-slot:hover:not(:disabled) {
  border-color: rgba(212, 175, 55, 0.85);
  background: rgba(212, 175, 55, 0.08);
  transform: translateY(-1px);
}

button.seat-slot.is-selected {
  border-color: #d4af37;
  background: rgba(212, 175, 55, 0.16);
  box-shadow: 0 0 0 1px rgba(212, 175, 55, 0.35);
}

button.seat-slot:disabled {
  cursor: default;
  opacity: 0.45;
}

.seat-slot--taken {
  opacity: 0.92;
  cursor: default;
}

.seat-slot__top {
  color: #d4af37;
  font-size: 0.65rem;
  font-weight: 700;
  margin-bottom: 0.15rem;
}

.seat-slot__avatar {
  width: 36px;
  height: 36px;
  margin: 0 auto 0.2rem;
  border-radius: 9999px;
  background: linear-gradient(135deg, #8B5CF6, #d4af37);
  color: #fff;
  font-weight: 800;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.seat-slot__icon {
  width: 36px;
  height: 36px;
  margin: 0 auto 0.2rem;
  display: block;
}

.seat-slot__code {
  display: block;
  color: #f5f5f5;
  font-weight: 800;
  font-size: 0.95rem;
  letter-spacing: 0.04em;
  direction: ltr;
  font-family: ui-monospace, monospace;
}

.seat-slot__user {
  font-size: 0.68rem;
  color: #e5e7eb;
  margin-top: 0.1rem;
  line-height: 1.25;
  word-break: break-word;
  font-weight: 700;
}

.seat-slot__status {
  font-size: 0.58rem;
  color: #9ca3af;
  margin-top: 0.1rem;
}

.seat-slot__status--taken {
  color: #f59e0b;
}
</style>
