<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات دعوت | پنل مدیریت' })

const form = reactive({
  bonus_percent: 5,
})

function onSubmit() {
  /* preview only */
}
</script>

<template>
  <div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-primary">🔗 تنظیمات سیستم دعوت</h1>

    <AdminApiNotice />

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="block text-gray-300 text-sm mb-1">درصد پاداش معرف (از اولین شارژ کاربر معرف)</label>
          <input
            v-model.number="form.bonus_percent"
            type="number"
            class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white"
            min="0"
            max="100"
            step="0.5"
            required
            disabled
          >
          <p class="text-xs text-gray-500 mt-1">مثلاً ۵ به معنای ۵٪ از مبلغ اولین شارژ کاربر معرف</p>
        </div>
        <button type="submit" class="w-full bg-success hover:opacity-90 text-white rounded py-2 font-bold opacity-60 cursor-not-allowed" disabled>
          💾 ذخیره تنظیمات
        </button>
      </form>
      <div class="mt-4 text-center text-sm text-gray-500">
        <p>🔹 این درصد به کاربری که دوست خود را معرفی کرده، از اولین شارژ دوستش تعلق می‌گیرد.</p>
      </div>
    </div>
  </div>
</template>
