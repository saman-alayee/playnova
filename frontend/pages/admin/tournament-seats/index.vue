<script setup lang="ts">
import type { Tournament } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'جایگاه‌های مسابقات | PlayNova' })

const api = useApi()
const { data, pending } = await useAsyncData('admin-tournament-seats-list', () => api.admin.tournamentSeats())
const tournaments = computed(() => (data.value ?? []) as Tournament[])
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">نقشه جایگاه‌های مسابقات</h1>
    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else class="space-y-2">
      <NuxtLink
        v-for="t in tournaments"
        :key="t.id"
        :to="`/admin/tournament-seats/${t.id}`"
        class="block bg-dark-800 border border-dark-600 rounded-xl p-4 hover:border-secondary/40"
      >
        <span class="font-bold text-white">{{ t.title }}</span>
        <span class="text-xs text-gray-400 mr-2">{{ t.status_label || t.status }}</span>
      </NuxtLink>
    </div>
  </div>
</template>
