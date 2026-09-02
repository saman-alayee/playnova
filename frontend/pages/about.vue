<script setup lang="ts">
useHead({ title: 'درباره ما | PlayNova' })

const api = useApi()
const { data, pending, error } = usePageData('about', () => api.pages.about())
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">{{ data?.title || 'درباره PlayNova' }}</h1>
    <PageLoading v-if="pending" />
    <div v-else-if="error" class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-gray-400">
      بارگذاری صفحه ممکن نشد.
    </div>
    <div v-else class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-sm leading-8 whitespace-pre-line text-gray-300">
      {{ data?.content || data?.body || 'PlayNova پلتفرم برگزاری مسابقات آنلاین Call of Duty Mobile است.' }}
    </div>
  </div>
</template>
