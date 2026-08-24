<script setup lang="ts">
useHead({ title: 'حریم خصوصی | PlayNova' })

const api = useApi()
const { data, pending, error } = await useAsyncData('privacy', () => api.pages.privacy())
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">{{ data?.title || 'حریم خصوصی' }}</h1>
    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-gray-400">
      بارگذاری صفحه ممکن نشد.
    </div>
    <div v-else class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-sm leading-8 whitespace-pre-line text-gray-300">
      {{ data?.content || data?.body || 'متن حریم خصوصی به‌زودی منتشر می‌شود.' }}
    </div>
  </div>
</template>
