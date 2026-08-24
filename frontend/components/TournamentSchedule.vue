<script setup lang="ts">
import type { Tournament } from '~/types/api'

const props = defineProps<{
  tournament: Tournament
  class?: string
}>()

const formatted = computed(() => {
  if (!props.tournament.start_date) return null
  const date = new Date(props.tournament.start_date)
  return {
    date: date.toLocaleDateString('fa-IR'),
    time: date.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' }),
  }
})
</script>

<template>
  <div v-if="formatted" class="tournament-schedule" :class="props.class">
    <div class="tournament-schedule__date">
      <span class="tournament-schedule__date-label">🗓 تاریخ برگزاری</span>
      <span class="tournament-schedule__date-value">{{ formatted.date }}</span>
    </div>
    <div class="tournament-schedule__time">
      <div class="tournament-schedule__time-label">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <circle cx="12" cy="12" r="9" stroke-width="1.8" />
          <path d="M12 7v5l3 2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        ساعت ورود به بازی
      </div>
      <div class="tournament-schedule__time-value">{{ formatted.time }}</div>
    </div>
  </div>
</template>
