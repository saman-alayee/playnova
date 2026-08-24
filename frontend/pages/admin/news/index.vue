<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت اخبار | PlayNova' })

interface NewsItem {
  id: number
  title: string
  content: string
}

const newsItems = ref<NewsItem[]>([])

const form = reactive({
  title: '',
  content: '',
})

function truncate(text: string, len = 120) {
  return text.length > len ? `${text.slice(0, len)}…` : text
}

function onSubmit() {
  /* preview only */
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">مدیریت اخبار</h1>

    <AdminApiNotice />

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
      <h2 class="font-bold mb-4 text-white">افزودن خبر جدید</h2>
      <form class="space-y-3" @submit.prevent="onSubmit">
        <input
          v-model="form.title"
          type="text"
          placeholder="عنوان خبر"
          required
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
        >
        <textarea
          v-model="form.content"
          rows="4"
          placeholder="متن خبر"
          required
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
        />
        <input type="file" accept="image/*" class="w-full text-sm text-gray-400" disabled>
        <button type="submit" class="bg-success hover:bg-green-700 text-white rounded px-4 py-2 font-bold transition opacity-60 cursor-not-allowed" disabled>
          انتشار خبر
        </button>
      </form>
    </div>

    <div v-if="!newsItems.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      خبری یافت نشد — پس از توسعه Admin API لیست اینجا نمایش داده می‌شود.
    </div>
    <div v-else class="space-y-3">
      <div
        v-for="n in newsItems"
        :key="n.id"
        class="bg-dark-800 border border-dark-600 rounded-xl p-4 flex justify-between items-start gap-3"
      >
        <div>
          <h3 class="font-bold text-white">{{ n.title }}</h3>
          <p class="text-xs text-gray-400 mt-1">{{ truncate(n.content) }}</p>
        </div>
        <button type="button" class="text-xs text-red-400 opacity-50 cursor-not-allowed whitespace-nowrap" disabled>حذف</button>
      </div>
    </div>
  </div>
</template>
