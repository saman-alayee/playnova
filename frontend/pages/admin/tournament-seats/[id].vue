<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })

const route = useRoute()
const api = useApi()
const id = computed(() => String(route.params.id))

const { data, pending, error } = await useAsyncData(
  () => `admin-seat-map-${id.value}`,
  () => api.admin.tournamentSeatMap(id.value),
)

useHead({ title: computed(() => data.value ? `جایگاه‌ها — ${data.value.tournament.title}` : 'نقشه جایگاه‌ها') })

const seats = computed(() => {
  const cap = data.value?.capacity ?? 0
  const occupied = data.value?.occupied_seats ?? {}
  return Array.from({ length: cap }, (_, i) => {
    const num = i + 1
    return { number: num, occupant: occupied[num] ?? null }
  })
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">نقشه جایگاه‌ها</h1>
      <NuxtLink to="/admin/tournaments" class="text-sm text-secondary">← مسابقات</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="text-red-400">{{ (error as Error).message }}</div>
    <template v-else-if="data">
      <p class="text-gray-400 mb-4">{{ data.tournament.title }}</p>
      <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2">
        <div
          v-for="seat in seats"
          :key="seat.number"
          class="border rounded-lg p-2 text-center text-xs min-h-[64px] flex flex-col justify-center"
          :class="seat.occupant ? 'border-secondary bg-secondary/10' : 'border-dark-600 bg-dark-800 text-gray-500'"
        >
          <span class="font-bold">#{{ seat.number }}</span>
          <span v-if="seat.occupant" class="text-secondary truncate">{{ seat.occupant.username }}</span>
          <span v-else>خالی</span>
        </div>
      </div>
    </template>
  </div>
</template>
