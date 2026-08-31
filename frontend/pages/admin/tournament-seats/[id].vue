<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'blank' })

const route = useRoute()
const api = useApi()
const id = computed(() => String(route.params.id))

const { data, pending, error } = await useAsyncData(
  () => `admin-seat-map-${id.value}`,
  () => api.admin.tournamentSeatMap(id.value),
)

useHead({
  title: computed(() => (data.value ? `جایگاه‌ها — ${data.value.tournament.title}` : 'نقشه جایگاه‌ها')),
})

const teamsGrid = computed(() => data.value?.teams_grid || [])
const seatMode = computed(() => data.value?.seat_mode || data.value?.tournament?.seat_mode || 1)
const occupiedSeats = computed(() => data.value?.occupied_seats || {})
</script>

<template>
  <div class="seat-admin">
    <div class="seat-admin__toolbar">
      <NuxtLink to="/admin/tournament-seats" class="seat-admin__back">بازگشت</NuxtLink>
      <h1 class="seat-admin__title">جایگاه‌ها</h1>
      <span class="seat-admin__meta">{{ data?.tournament?.title }}</span>
    </div>

    <div v-if="pending" class="seat-admin__state">در حال بارگذاری...</div>
    <div v-else-if="error" class="seat-admin__state seat-admin__state--error">{{ (error as Error).message }}</div>

    <div v-else class="seat-admin__body">
      <TournamentSeatGrid
        :teams="teamsGrid"
        :occupied-seats="occupiedSeats"
        :seat-mode="seatMode"
      />
    </div>
  </div>
</template>

<style scoped>
.seat-admin {
  direction: rtl;
  min-height: 100dvh;
  background: #000;
  color: #f5f5f5;
}

.seat-admin__toolbar {
  display: grid;
  grid-template-columns: auto 1fr;
  grid-template-areas:
    'back title'
    'meta meta';
  gap: 0.15rem 0.5rem;
  align-items: center;
  padding: 0.55rem 0.65rem;
  border-bottom: 1px solid rgba(197, 160, 89, 0.35);
}

.seat-admin__back {
  grid-area: back;
  color: #9ca3af;
  font-size: 0.72rem;
  text-decoration: none;
}

.seat-admin__title {
  grid-area: title;
  margin: 0;
  text-align: center;
  font-size: 1rem;
  font-weight: 800;
  color: #d4af37;
}

.seat-admin__meta {
  grid-area: meta;
  text-align: center;
  font-size: 0.72rem;
  color: #9ca3af;
}

.seat-admin__state {
  padding: 2rem 1rem;
  text-align: center;
  color: #9ca3af;
}

.seat-admin__state--error {
  color: #fca5a5;
}

.seat-admin__body {
  padding: 0.55rem;
  overflow-x: auto;
}
</style>
