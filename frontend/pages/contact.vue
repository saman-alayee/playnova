<script setup lang="ts">
useHead({ title: 'ارتباط با ما | PlayNova' })

const api = useApi()
const { data, pending, error } = usePageData('contact', () => api.pages.contact())

const email = computed(() => data.value?.email?.trim() || '')
const phone = computed(() => data.value?.phone?.trim() || '')
const phoneHref = computed(() => phone.value.replace(/\s+/g, ''))
</script>

<template>
  <div class="max-w-2xl mx-auto">
    <PageLoading v-if="pending" />
    <div v-else-if="error" class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-center text-gray-400">
      بارگذاری اطلاعات تماس ممکن نشد.
    </div>
    <div v-else class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-4">
      <h1 class="text-2xl font-bold mb-2 text-white">ارتباط با ما</h1>
      <p class="text-sm text-gray-400">
        برای پاسخ سوالات رایج، ابتدا بخش سوالات متداول را ببینید.
      </p>
      <div class="space-y-2 text-sm">
        <p v-if="email">
          <span class="text-gray-500">ایمیل:</span>
          <a :href="`mailto:${email}`" class="text-secondary">{{ email }}</a>
        </p>
        <p v-if="phone">
          <span class="text-gray-500">تلفن:</span>
          <a :href="`tel:${phoneHref}`" dir="ltr" class="text-secondary">{{ phone }}</a>
        </p>
      </div>
      <!-- بخش تیکت پشتیبانی بسته شد.
      <NuxtLink to="/support" class="inline-block mt-4 btn-glow-primary text-sm px-4 py-2 rounded-xl">
        ثبت تیکت پشتیبانی
      </NuxtLink>
      -->
      <NuxtLink to="/tickets" class="inline-block mt-4 text-sm text-secondary hover:underline">
        مشاهده سوالات متداول
      </NuxtLink>
    </div>
  </div>
</template>
