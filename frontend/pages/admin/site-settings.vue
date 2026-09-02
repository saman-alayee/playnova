<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات سایت | PlayNova' })

const api = useApi()
const flash = useState('flash')

const { data, pending, error, refresh } = usePageData('admin-site-settings', () =>
  api.admin.siteSettings(),
)

const form = reactive({
  privacy_content: '',
  about_content: '',
  contact_email: '',
  contact_phone: '',
  contact_address: '',
  social_telegram: '',
  social_rubika: '',
  social_instagram: '',
  results_telegram: '',
  results_rubika: '',
})

watch(data, (settings) => {
  if (!settings) return
  Object.assign(form, {
    privacy_content: settings.privacy_content || '',
    about_content: settings.about_content || '',
    contact_email: settings.contact_email || '',
    contact_phone: settings.contact_phone || '',
    contact_address: settings.contact_address || '',
    social_telegram: settings.social_telegram || '',
    social_rubika: settings.social_rubika || '',
    social_instagram: settings.social_instagram || '',
    results_telegram: settings.results_telegram || '',
    results_rubika: settings.results_rubika || '',
  })
}, { immediate: true })

const loading = ref(false)

async function submit() {
  loading.value = true
  try {
    await api.admin.updateSiteSettings({ ...form })
    flash.value = { success: 'تنظیمات ذخیره شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="text-2xl font-bold mb-6 text-white">تنظیمات سایت</h1>

    <div v-if="pending" class="text-gray-500 mb-4">در حال بارگذاری...</div>
    <div v-else-if="error" class="text-red-400 mb-4">{{ (error as Error).message }}</div>

    <form v-else class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-4" @submit.prevent="submit">
      <div>
        <label class="text-sm text-gray-400">متن حریم خصوصی</label>
        <textarea v-model="form.privacy_content" rows="6" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 mt-1 text-white" />
      </div>
      <div>
        <label class="text-sm text-gray-400">متن درباره ما</label>
        <textarea v-model="form.about_content" rows="6" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 mt-1 text-white" />
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <input v-model="form.contact_email" type="email" placeholder="ایمیل تماس" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
        <input v-model="form.contact_phone" type="text" placeholder="تلفن" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      </div>
      <textarea v-model="form.contact_address" rows="2" placeholder="آدرس" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" />
      <h3 class="font-bold pt-2 text-white">شبکه‌های اجتماعی</h3>
      <div class="grid sm:grid-cols-3 gap-3">
        <input v-model="form.social_telegram" type="text" placeholder="تلگرام" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
        <input v-model="form.social_rubika" type="text" placeholder="روبیکا" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
        <input v-model="form.social_instagram" type="text" placeholder="اینستاگرام" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      </div>
      <h3 class="font-bold pt-2 text-white">کانال‌های اعلام نتایج</h3>
      <div class="grid sm:grid-cols-2 gap-3">
        <input v-model="form.results_telegram" type="text" placeholder="تلگرام" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
        <input v-model="form.results_rubika" type="text" placeholder="روبیکا" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      </div>
      <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold" :disabled="loading">
        {{ loading ? '...' : 'ذخیره تنظیمات' }}
      </button>
    </form>
  </div>
</template>
