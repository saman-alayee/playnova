<script setup lang="ts">
useHead({ title: 'ارتباط با ما | PlayNova' })

const api = useApi()
const auth = useAuthStore()
const { data, pending, error } = await useAsyncData('contact', () => api.pages.contact())

const supportPhone = computed(() => data.value?.content || auth.settings?.support_phone)
</script>

<template>
  <div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-white text-center">{{ data?.title || 'ارتباط با ما' }}</h1>

    <div v-if="pending" class="text-center text-gray-500">در حال بارگذاری...</div>
    <div v-else class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-center space-y-4">
      <p class="text-sm text-gray-300 leading-7 whitespace-pre-line">
        {{ data?.body || 'برای پشتیبانی با ما در ارتباط باشید.' }}
      </p>
      <a
        v-if="supportPhone"
        :href="`tel:${String(supportPhone).replace(/\s+/g, '')}`"
        dir="ltr"
        class="inline-flex items-center justify-center gap-2 bg-secondary hover:opacity-90 text-white font-bold px-6 py-3 rounded-xl"
      >
        📞 {{ supportPhone }}
      </a>
      <NuxtLink to="/tickets" class="block text-primary text-sm font-bold">سوالات متداول</NuxtLink>
    </div>
  </div>
</template>
