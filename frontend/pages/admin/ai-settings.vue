<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات هوش مصنوعی | PlayNova' })

const api = useApi()
const flash = useState('flash')
const { data, refresh } = await useAsyncData('admin-ai', () => api.admin.aiSettings())

const form = reactive({
  base_url: '',
  vision_model: 'gpt-4o',
  timeout: 120,
  api_key: '',
  is_active: true,
  clear_api_key: false,
})

const testing = ref(false)
const testResult = ref<{ ok: boolean; message: string } | null>(null)

watch(data, (d) => {
  if (!d) return
  form.base_url = String(d.base_url || '')
  form.vision_model = String(d.vision_model || 'gpt-4o')
  form.timeout = Number(d.timeout || 120)
  form.is_active = !!d.is_active
  form.api_key = ''
  form.clear_api_key = false
}, { immediate: true })

const apiKeySourceLabel = computed(() => {
  const source = data.value?.api_key_source
  if (source === 'database') return 'ذخیره‌شده در پنل'
  if (source === 'env') return 'از فایل .env'
  return 'تنظیم نشده'
})

async function save() {
  const payload: Record<string, unknown> = {
    base_url: form.base_url,
    vision_model: form.vision_model,
    timeout: form.timeout,
    is_active: form.is_active,
    clear_api_key: form.clear_api_key,
  }
  if (form.api_key.trim()) {
    payload.api_key = form.api_key.trim()
  }
  await api.admin.updateAiSettings(payload)
  flash.value = { success: 'تنظیمات هوش مصنوعی ذخیره شد.' }
  testResult.value = null
  await refresh()
}

async function testConnection() {
  testing.value = true
  testResult.value = null
  try {
    const res = await api.admin.testAiSettings()
    testResult.value = {
      ok: true,
      message: res.message || `اتصال موفق — مدل: ${res.model || form.vision_model}`,
    }
  } catch (e: unknown) {
    const err = e as { data?: { message?: string }; message?: string }
    testResult.value = {
      ok: false,
      message: err.data?.message || err.message || 'تست اتصال ناموفق بود.',
    }
  } finally {
    testing.value = false
  }
}
</script>

<template>
  <div class="max-w-xl">
    <h1 class="text-2xl font-bold mb-2 text-white">تنظیمات هوش مصنوعی (AvalAI)</h1>
    <p class="text-sm text-gray-400 mb-6">
      برای تشخیص خودکار نتیجه مسابقه از اسکرین‌شات. مقادیر پنل بر .env اولویت دارند.
    </p>

    <form class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-4" @submit.prevent="save">
      <label class="flex items-center gap-2 text-sm text-gray-300">
        <input v-model="form.is_active" type="checkbox">
        سرویس هوش مصنوعی فعال باشد
      </label>

      <div>
        <label class="block text-sm text-gray-400 mb-1">آدرس API</label>
        <input
          v-model="form.base_url"
          placeholder="https://api.avalai.ir/v1"
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        >
      </div>

      <div>
        <label class="block text-sm text-gray-400 mb-1">مدل Vision</label>
        <input
          v-model="form.vision_model"
          list="ai-models"
          placeholder="gpt-4o"
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        >
        <datalist id="ai-models">
          <option v-for="m in data?.suggested_models || []" :key="m" :value="m" />
        </datalist>
      </div>

      <div>
        <label class="block text-sm text-gray-400 mb-1">مهلت درخواست (ثانیه)</label>
        <input
          v-model.number="form.timeout"
          type="number"
          min="30"
          max="300"
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        >
      </div>

      <div>
        <label class="block text-sm text-gray-400 mb-1">کلید API</label>
        <input
          v-model="form.api_key"
          type="password"
          autocomplete="new-password"
          placeholder="در صورت تغییر وارد کنید"
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        >
        <p class="text-xs text-gray-500 mt-1">
          وضعیت: {{ data?.has_api_key ? 'تنظیم شده' : 'تنظیم نشده' }}
          ({{ apiKeySourceLabel }})
        </p>
        <label v-if="data?.api_key_source === 'database'" class="flex items-center gap-2 text-xs text-gray-400 mt-2">
          <input v-model="form.clear_api_key" type="checkbox">
          حذف کلید ذخیره‌شده در پنل
        </label>
      </div>

      <div class="flex flex-wrap gap-2 pt-2">
        <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold">ذخیره</button>
        <button
          type="button"
          class="bg-secondary text-white rounded px-4 py-2 font-bold disabled:opacity-50"
          :disabled="testing || !data?.has_api_key"
          @click="testConnection"
        >
          {{ testing ? 'در حال تست…' : 'تست اتصال' }}
        </button>
      </div>

      <p
        v-if="testResult"
        class="text-sm rounded px-3 py-2"
        :class="testResult.ok ? 'bg-green-900/40 text-green-300' : 'bg-red-900/40 text-red-300'"
      >
        {{ testResult.message }}
      </p>
    </form>
  </div>
</template>
