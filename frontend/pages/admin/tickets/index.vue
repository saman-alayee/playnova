<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت تیکت‌ها | PlayNova' })

interface Ticket {
  id: number
  subject: string
  message: string
  priority: string
  status: string
  user?: { username?: string }
}

const tickets = ref<Ticket[]>([])

const statusLabels: Record<string, string> = {
  open: 'باز',
  in_progress: 'در حال بررسی',
  resolved: 'حل شده',
  closed: 'بسته شده',
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">مدیریت تیکت‌های پشتیبانی</h1>

    <AdminApiNotice />

    <div v-if="!tickets.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      تیکتی یافت نشد — پس از توسعه Admin API لیست اینجا نمایش داده می‌شود.
    </div>
    <div v-else class="space-y-4">
      <div
        v-for="ticket in tickets"
        :key="ticket.id"
        class="bg-dark-800 border border-dark-600 rounded-xl p-4"
      >
        <div class="flex justify-between items-start mb-2">
          <div>
            <h3 class="font-bold text-white">{{ ticket.subject }}</h3>
            <p class="text-xs text-gray-400">
              از طرف: {{ ticket.user?.username ?? '—' }} — اولویت: {{ ticket.priority }}
            </p>
          </div>
          <div class="flex gap-2 items-center">
            <select
              :value="ticket.status"
              disabled
              class="bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs text-white opacity-60"
            >
              <option value="open">{{ statusLabels.open }}</option>
              <option value="in_progress">{{ statusLabels.in_progress }}</option>
              <option value="resolved">{{ statusLabels.resolved }}</option>
              <option value="closed">{{ statusLabels.closed }}</option>
            </select>
            <button type="button" class="text-xs bg-success text-white px-2 py-1 rounded font-bold opacity-50 cursor-not-allowed" disabled>
              بروزرسانی
            </button>
          </div>
        </div>
        <p class="text-sm text-gray-300 mb-3">{{ ticket.message }}</p>
        <span class="text-xs text-secondary opacity-50">مشاهده گفتگو و پاسخ →</span>
      </div>
    </div>
  </div>
</template>
