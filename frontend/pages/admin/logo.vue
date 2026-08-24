<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تغییر لوگو | پنل مدیریت' })

const api = useApi()

const { data: settings } = await useAsyncData('admin-logo-settings', () => api.admin.siteSettings(), {
  default: () => null,
})

const logoUrl = computed(() => settings.value?.logo_url || null)

function onSubmit() {
  /* preview only */
}
</script>

<template>
  <div class="max-w-2xl mx-auto">
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700">
      <h2 class="text-2xl font-bold text-center mb-6 text-primary">🖼️ تغییر لوگوی سایت</h2>

      <AdminApiNotice />

      <div class="text-center mb-6">
        <p class="text-gray-400 text-sm mb-2">لوگوی فعلی:</p>
        <div class="inline-block bg-dark-900/50 p-4 rounded-lg border border-gray-700">
          <img
            v-if="logoUrl"
            :src="logoUrl"
            class="h-16 md:h-20 object-contain"
            alt="لوگو"
          >
          <div
            v-else
            class="w-16 h-16 bg-gradient-to-br from-primary to-secondary rounded-xl flex items-center justify-center font-bold text-2xl text-white mx-auto"
          >
            PN
          </div>
        </div>
      </div>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="block text-gray-300 text-sm mb-2">آپلود لوگوی جدید (png, jpg, svg - حداکثر ۲ مگابایت)</label>
          <input
            type="file"
            accept="image/*"
            class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary"
            disabled
          >
        </div>
        <button type="submit" class="w-full btn-glow-success py-3 rounded-lg text-white font-bold opacity-60 cursor-not-allowed" disabled>
          💾 ذخیره لوگو
        </button>
      </form>

      <div class="mt-4 text-center">
        <button type="button" class="text-danger opacity-50 cursor-not-allowed text-sm" disabled>
          🗑️ حذف لوگو و بازگشت به حالت پیش‌فرض
        </button>
      </div>
    </div>
  </div>
</template>
