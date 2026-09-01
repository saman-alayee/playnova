<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })

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
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-white">جایگاه‌ها</h1>
        <p v-if="data?.tournament?.title" class="text-sm text-gray-400 mt-1">{{ data.tournament.title }}</p>
      </div>
      <NuxtLink to="/admin/tournament-seats" class="text-sm text-secondary">← بازگشت</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500 py-10 text-center">در حال بارگذاری...</div>
    <div v-else-if="error" class="text-red-400 py-10 text-center">{{ (error as Error).message }}</div>

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
  min-width: 0;
  max-width: 100%;
}

.seat-admin__body {
  min-width: 0;
  max-width: 100%;
  overflow-x: auto;
  border: 1px solid rgba(197, 160, 89, 0.28);
  border-radius: 0.75rem;
  background: #050505;
  padding: 0.65rem;
}
</style>
