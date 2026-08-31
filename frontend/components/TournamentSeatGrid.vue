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

function onPick(seatNumber: number, label: string) {
  if (props.interactive) emit('select', seatNumber, label)
}
</script>

<template>
  <div class="seat-grid">
    <fieldset
      v-for="teamRow in teams"
      :key="teamRow.team"
      class="seat-grid__card"
    >
      <legend class="seat-grid__legend">{{ teamTitle(teamRow.team) }}</legend>
      <div
        class="seat-grid__slots"
        :style="{ gridTemplateColumns: `repeat(${seatMode}, minmax(0, 1fr))` }"
      >
        <template v-for="slot in teamRow.slots" :key="slot.seat_number">
          <div
            v-if="occupant(slot.seat_number)"
            class="seat-grid__slot seat-grid__slot--taken"
          >
            <div class="seat-grid__label">{{ slot.label }}</div>
            <div class="seat-grid__name">{{ occupant(slot.seat_number)?.username || '—' }}</div>
            <div class="seat-grid__cod">{{ occupant(slot.seat_number)?.cod_id || '—' }}</div>
          </div>
          <button
            v-else
            type="button"
            class="seat-grid__slot seat-grid__slot--empty"
            :class="{ 'seat-grid__slot--selected': selectedSeat === slot.seat_number }"
            :disabled="!interactive"
            @click="onPick(slot.seat_number, slot.label)"
          >
            <div class="seat-grid__label">{{ slot.label }}</div>
            <div class="seat-grid__empty">خالی</div>
          </button>
        </template>
      </div>
    </fieldset>
  </div>
</template>

<style scoped>
.seat-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.5rem 0.45rem;
  direction: ltr;
  width: 100%;
}

@media (max-width: 1100px) {
  .seat-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (max-width: 860px) {
  .seat-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 620px) {
  .seat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.seat-grid__card {
  margin: 0;
  min-width: 0;
  border: 1px solid #c5a059;
  background: #000;
  padding: 0.15rem 0.35rem 0.4rem;
}

.seat-grid__legend {
  width: auto;
  margin: 0 auto;
  padding: 0 0.4rem;
  color: #d4af37;
  font-weight: 800;
  font-size: 0.8rem;
  text-align: center;
}

.seat-grid__slots {
  display: grid;
  gap: 0.3rem;
}

.seat-grid__slot {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 4.25rem;
  padding: 0.3rem 0.15rem 0.35rem;
  text-align: center;
  background: #000;
  border: 1px solid rgba(197, 160, 89, 0.55);
}

.seat-grid__slot--empty {
  cursor: pointer;
}

.seat-grid__slot--empty:hover:not(:disabled) {
  border-color: #d4af37;
  background: rgba(197, 160, 89, 0.06);
}

.seat-grid__slot--empty:disabled {
  cursor: default;
}

.seat-grid__slot--selected {
  border-color: #d4af37;
  background: rgba(197, 160, 89, 0.12);
}

.seat-grid__slot--taken {
  opacity: 0.95;
}

.seat-grid__label {
  color: #d4af37;
  font-size: 0.68rem;
  font-weight: 700;
  font-family: ui-monospace, monospace;
  line-height: 1.2;
  margin-bottom: 0.2rem;
}

.seat-grid__name {
  color: #f0ead8;
  font-size: 0.76rem;
  font-weight: 800;
  line-height: 1.3;
  word-break: break-word;
  padding: 0 0.1rem;
}

.seat-grid__cod {
  margin-top: 0.12rem;
  color: #d4af37;
  font-size: 0.62rem;
  font-weight: 600;
  line-height: 1.25;
  word-break: break-word;
  padding: 0 0.1rem;
}

.seat-grid__empty {
  color: #6b7280;
  font-size: 0.72rem;
  font-weight: 600;
}
</style>
