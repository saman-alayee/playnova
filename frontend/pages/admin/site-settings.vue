<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات سایت | PlayNova' })

const api = useApi()
const flash = useState('flash')

const { data, pending, error, refresh } = await useAsyncData('admin-site-settings', () =>
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
  site_name: '',
  support_phone: '',
})

watch(
  () => data.value,
  (settings) => {
    if (!settings) return
    form.site_name = settings.site_name || ''
    form.support_phone = settings.support_phone || ''
    form.social_telegram = settings.social?.telegram || ''
    form.social_rubika = settings.social?.rubika || ''
    form.social_instagram = settings.social?.instagram || ''
  },
  { immediate: true },
)

const loading = ref(false)
const errors = ref<string[]>([])

async function submit() {
  loading.value = true
  errors.value = []
  try {
    await api.admin.updateSiteSettings({
      site_name: form.site_name,
      support_phone: form.support_phone,
      instagram: form.social_instagram,
      rubika: form.social_rubika,
      telegram: form.social_telegram,
    })
    flash.value = { success: 'تنظیمات ذخیره شد.' }
    await refresh()
  } catch (e: unknown) {
    const err = e as Error
    errors.value = [err.message || 'ذخیره ناموفق بود.']
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="text-2xl font-bold mb-6 text-white">تنظیمات سایت</h1>

    <div v-if="pending" class="text-gray-500 mb-4">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-amber-600/40 rounded-xl p-6 text-amber-200 mb-4">
      API `/api/v1/admin/settings/site` در دسترس نیست — فرم زیر پس از پیاده‌سازی backend فعال می‌شود.
    </div>

    <AdminApiNotice message="فیلدهای حریم خصوصی، درباره ما و تماس هنوز در Admin API موجود نیستند؛ فقط نام سایت، تلفن و شبکه‌های اجتماعی از API بارگذاری/ذخیره می‌شوند." />

    <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
      <ul class="list-disc list-inside space-y-1">
        <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
      </ul>
    </div>

    <form class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-4" @submit.prevent="submit">
      <div>
        <label class="text-sm text-gray-400">متن حریم خصوصی</label>
        <textarea v-model="form.privacy_content" rows="6" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 mt-1" />
      </div>
      <div>
        <label class="text-sm text-gray-400">متن درباره ما</label>
        <textarea v-model="form.about_content" rows="6" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 mt-1" />
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <input v-model="form.contact_email" type="email" placeholder="ایمیل تماس" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
        <input v-model="form.contact_phone" type="text" placeholder="تلفن" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
      </div>
      <textarea v-model="form.contact_address" rows="2" placeholder="آدرس" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2" />
      <h3 class="font-bold pt-2 text-white">شبکه‌های اجتماعی (منوی همبرگری)</h3>
      <div class="grid sm:grid-cols-3 gap-3">
        <input v-model="form.social_telegram" type="text" placeholder="تلگرام (@channel)" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
        <input v-model="form.social_rubika" type="text" placeholder="روبیکا (@id)" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
        <input v-model="form.social_instagram" type="text" placeholder="اینستاگرام" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
      </div>
      <h3 class="font-bold pt-2 text-white">کانال‌های اعلام نتایج</h3>
      <div class="grid sm:grid-cols-2 gap-3">
        <input v-model="form.results_telegram" type="text" placeholder="آیدی کانال تلگرام" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
        <input v-model="form.results_rubika" type="text" placeholder="آیدی کانال روبیکا" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
      </div>
      <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold" :disabled="loading">
        {{ loading ? '...' : 'ذخیره تنظیمات' }}
      </button>
    </form>
  </div>
</template>
