<script setup lang="ts">
import type { Tournament } from '~/types/api'

const props = defineProps<{
  tournament: Tournament
  regCount?: number
  capacity?: number
  class?: string
}>()

const regCount = computed(() => {
  if (props.regCount !== undefined) return props.regCount
  const count = props.tournament.registrations_count ?? props.tournament.registered_count ?? 0
  return Math.max(0, Number(count))
})

const capacity = computed(() => {
  if (props.capacity !== undefined) return props.capacity
  return Math.max(1, Number(props.tournament.capacity ?? 1))
})

function formatNumber(n: number) {
  return n.toLocaleString('fa-IR')
}
</script>

<template>
  <div class="tournament-stats" :class="props.class || 'text-xs space-y-1.5 mb-2'">
    <div class="flex justify-between gap-2">
      <span class="text-gray-400">ورودی:</span>
      <span class="font-bold text-white">{{ formatNumber(Number(tournament.entry_fee)) }} تومان</span>
    </div>
    <div class="flex justify-between gap-2">
      <span class="text-gray-400">جایزه:</span>
      <span class="font-bold text-secondary">{{ formatNumber(Number(tournament.prize_pool)) }} تومان</span>
    </div>
    <div class="flex justify-between gap-2">
      <span class="text-gray-400">ظرفیت:</span>
      <span class="font-bold text-white">
        <span dir="ltr" class="inline-block">{{ formatNumber(regCount) }}/{{ formatNumber(capacity) }}</span>
      </span>
    </div>
  </div>
</template>
