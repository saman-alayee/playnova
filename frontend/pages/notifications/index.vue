<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
useHead({ title: 'اعلانات | PlayNova' })

const api = useApi()
const auth = useAuthStore()
const { formatDateTime } = usePersianDateTime()

const { data, pending, refresh } = await useAsyncData('notifications', () => api.notifications.list(), {
  default: () => ({ notifications: [], news: [], unread_count: 0 }),
})

const notifications = computed(() => data.value?.notifications || [])
const news = computed(() => data.value?.news || [])

async function markRead(id: number) {
  await api.notifications.markRead(id)
  await refresh()
  await auth.fetchUser()
}

async function markAllRead() {
  await api.notifications.markAllRead()
  await refresh()
  await auth.fetchUser()
}

async function remove(id: number) {
  await api.notifications.delete(id)
  await refresh()
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">اعلانات</h1>
      <button
        v-if="notifications.length"
        type="button"
        class="text-sm text-secondary font-bold"
        @click="markAllRead"
      >
        علامت‌گذاری همه به‌عنوان خوانده‌شده
      </button>
    </div>

    <section v-if="news.length" class="mb-8">
      <h2 class="text-lg font-bold text-white mb-4">اخبار و اطلاعیه‌ها</h2>
      <div class="space-y-3">
        <article
          v-for="item in news"
          :key="`news-${item.id}`"
          class="bg-dark-800 border border-secondary/30 rounded-xl p-4"
        >
          <h3 class="font-bold text-white">{{ item.title }}</h3>
          <p class="text-sm text-gray-300 mt-2 whitespace-pre-line">{{ item.body }}</p>
          <p v-if="item.created_at" class="text-xs text-gray-500 mt-2">{{ formatDateTime(item.created_at_display || item.created_at) }}</p>
        </article>
      </div>
    </section>

    <h2 class="text-lg font-bold text-white mb-4">اعلان‌های شخصی</h2>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="!notifications.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      اعلانی وجود ندارد.
    </div>
    <div v-else class="space-y-3">
      <div
        v-for="n in notifications"
        :key="n.id"
        class="bg-dark-800 border rounded-xl p-4"
        :class="n.is_read ? 'border-dark-600 opacity-80' : 'border-primary/40'"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="font-bold text-white">{{ n.title }}</h3>
            <p class="text-sm text-gray-300 mt-1 whitespace-pre-line">{{ n.body || n.message }}</p>
            <p class="text-xs text-gray-500 mt-2">{{ formatDateTime(n.created_at_display || n.created_at) }}</p>
          </div>
          <div class="flex flex-col gap-2 shrink-0">
            <button v-if="!n.is_read" type="button" class="text-xs text-secondary" @click="markRead(n.id)">خواندم</button>
            <button type="button" class="text-xs text-red-400" @click="remove(n.id)">حذف</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
