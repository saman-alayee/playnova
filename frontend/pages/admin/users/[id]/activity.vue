<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })

const route = useRoute()
const api = useApi()
const { formatDateTime } = usePersianDateTime()
const userId = Number(route.params.id)

interface ActivityLog {
  id: number
  category: string
  action: string
  description?: string
  metadata?: Record<string, unknown>
  actor?: { username?: string }
  created_at?: string
}

const { data, pending } = await useAsyncData(`admin-user-activity-${userId}`, () =>
  api.admin.userActivity(userId),
)

const logs = computed(() => (data.value?.items ?? []) as ActivityLog[])

useHead({ title: 'تاریخچه کاربر | PlayNova' })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">تاریخچه فعالیت کاربر #{{ userId }}</h1>
      <NuxtLink to="/admin/users" class="text-sm text-secondary">← کاربران</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="!logs.length" class="text-gray-500">فعالیتی ثبت نشده.</div>
    <div v-else class="space-y-2">
      <div v-for="log in logs" :key="log.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4 text-sm">
        <div class="flex flex-wrap justify-between gap-2 mb-1">
          <span class="text-secondary font-bold">{{ log.category }} / {{ log.action }}</span>
          <span class="text-xs text-gray-500">{{ formatDateTime(log.created_at_display || log.created_at) }}</span>
        </div>
        <p v-if="log.description" class="text-gray-300">{{ log.description }}</p>
        <p v-if="log.actor?.username" class="text-xs text-gray-500 mt-1">توسط: {{ log.actor.username }}</p>
      </div>
    </div>
  </div>
</template>
