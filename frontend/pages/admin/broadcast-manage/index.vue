<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت پیام‌های همگانی | PlayNova' })

interface BroadcastMessage {
  id: number
  title: string
  message: string
  created_at: string
}

const notifications = ref<BroadcastMessage[]>([])

function truncate(text: string, len = 50) {
  return text.length > len ? `${text.slice(0, len)}…` : text
}

function formatDate(date: string) {
  return new Date(date).toLocaleString('fa-IR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div class="max-w-6xl mx-auto">
    <h2 class="text-2xl font-bold mb-6 text-primary">📢 مدیریت پیام‌های همگانی</h2>

    <AdminApiNotice />

    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700 overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-dark-900/50 border-b border-gray-700">
          <tr>
            <th class="text-right py-3 px-4 text-gray-400">عنوان</th>
            <th class="text-right py-3 px-4 text-gray-400">متن</th>
            <th class="text-center py-3 px-4 text-gray-400">تاریخ ارسال</th>
            <th class="text-center py-3 px-4 text-gray-400">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!notifications.length">
            <td colspan="4" class="py-8 text-center text-gray-500">هیچ پیام همگانی ارسال نشده است.</td>
          </tr>
          <tr
            v-for="notif in notifications"
            :key="notif.id"
            class="border-b border-gray-700/50 hover:bg-gray-800/30 transition"
          >
            <td class="py-3 px-4 font-bold text-white">{{ notif.title }}</td>
            <td class="py-3 px-4 text-gray-300">{{ truncate(notif.message) }}</td>
            <td class="py-3 px-4 text-center text-gray-400">{{ formatDate(notif.created_at) }}</td>
            <td class="py-3 px-4 text-center">
              <span class="text-secondary text-sm ml-2 opacity-50">✏️ ویرایش</span>
              <span class="text-danger text-sm opacity-50">🗑️ حذف</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
