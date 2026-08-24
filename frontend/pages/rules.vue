<script setup lang="ts">
useHead({ title: 'قوانین | PlayNova' })

const api = useApi()
const { data: sections, pending, error } = await useAsyncData('rules', () => api.rules(), {
  default: () => [],
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">قوانین و مقررات</h1>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-gray-400">
      بارگذاری قوانین ممکن نشد.
    </div>
    <div v-else-if="!sections?.length" class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-gray-500">
      قوانین به‌زودی منتشر می‌شود.
    </div>
    <div v-else class="space-y-4">
      <div
        v-for="(rule, index) in sections"
        :key="rule.id"
        class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-sm leading-8"
      >
        <h2 class="font-bold text-secondary mb-2">بخش {{ index + 1 }}</h2>
        <p class="text-gray-300 whitespace-pre-line">{{ rule.content }}</p>
      </div>
    </div>
  </div>
</template>
