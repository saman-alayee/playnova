<script setup lang="ts">
import type { Notification } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت پیام‌ها | PlayNova' })

const api = useApi()
const { data, refresh } = await useAsyncData('admin-broadcasts', () => api.admin.broadcasts())
const items = computed(() => (data.value?.items ?? []) as Notification[])

async function remove(id: number) {
  if (!confirm('حذف شود؟')) return
  await api.admin.deleteBroadcast(id)
  await refresh()
}
</script>

<template>
  <div>
    <div class="flex justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">پیام‌های همگانی</h1>
      <NuxtLink to="/admin/broadcast" class="text-sm text-secondary">ارسال جدید</NuxtLink>
    </div>
    <div v-for="n in items" :key="n.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4 mb-3">
      <h3 class="font-bold text-white">{{ n.title }}</h3>
      <p class="text-sm text-gray-400 mt-1">{{ n.message }}</p>
      <button type="button" class="text-xs text-red-400 mt-2" @click="remove(n.id)">حذف</button>
    </div>
  </div>
</template>
