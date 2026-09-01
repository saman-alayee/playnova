<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات هوش مصنوعی | PlayNova' })

const api = useApi()
const flash = useState('flash')
const { data, refresh } = await useAsyncData('admin-ai', () => api.admin.aiSettings())

const form = reactive({
  base_url: '',
  vision_model: 'gpt-4o',
  result_vision_model: 'gpt-4o',
  timeout: 120,
  api_key: '',
  is_active: true,
  clear_api_key: false,
})

const testing = ref(false)
const refreshingModels = ref(false)
const testResult = ref<{ ok: boolean; message: string } | null>(null)
const modelOptions = ref<string[]>([])

watch(data, (d) => {
  if (!d) return
  form.base_url = String(d.base_url || '')
  form.vision_model = String(d.vision_model || 'gpt-4o')
  form.result_vision_model = String(d.result_vision_model || d.vision_model || 'gpt-4o')
  form.timeout = Number(d.timeout || 120)
  form.is_active = !!d.is_active
  form.api_key = ''
  form.clear_api_key = false
  modelOptions.value = d.available_models?.length ? [...d.available_models] : [...(d.suggested_models || [])]
}, { immediate: true })

const apiKeySourceLabel = computed(() => {
  const source = data.value?.api_key_source
  if (source === 'database') return 'ذخیره‌شده در پنل'
  if (source === 'env') return 'از فایل .env'
  return 'تنظیم نشده'
})

function ensureModelInOptions(model: string) {
  if (model && !modelOptions.value.includes(model)) {
    modelOptions.value = [model, ...modelOptions.value]
  }
}

async function refreshModels() {
  refreshingModels.value = true
  try {
    const res = await api.admin.aiModels()
    if (res.models?.length) {
      modelOptions.value = res.models
      ensureModelInOptions(form.vision_model)
      ensureModelInOptions(form.result_vision_model)
    }
  } catch (e: unknown) {
    const err = e as { data?: { message?: string }; message?: string }
    flash.value = { error: err.data?.message || err.message || 'دریافت لیست مدل‌ها ناموفق بود.' }
  } finally {
    refreshingModels.value = false
  }
}

async function save() {
  const payload: Record<string, unknown> = {
    base_url: form.base_url,
    vision_model: form.vision_model,
    result_vision_model: form.result_vision_model,
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
        <div class="flex items-center justify-between mb-1">
          <label class="block text-sm text-gray-400">مدل Vision (تست اتصال)</label>
          <button
            type="button"
            class="text-xs text-secondary disabled:opacity-50"
            :disabled="refreshingModels || !data?.has_api_key"
            @click="refreshModels"
          >
            {{ refreshingModels ? 'در حال دریافت…' : 'بروزرسانی لیست مدل‌ها' }}
          </button>
        </div>
        <select
          v-model="form.vision_model"
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        >
          <option v-for="m in modelOptions" :key="'v-' + m" :value="m">{{ m }}</option>
        </select>
      </div>

      <div>
        <label class="block text-sm text-gray-400 mb-1">مدل تحلیل نتیجه مسابقه</label>
        <select
          v-model="form.result_vision_model"
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        >
          <option v-for="m in modelOptions" :key="'r-' + m" :value="m">{{ m }}</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">برای تحلیل اسکرین‌شات RANK در صفحه ثبت نتیجه مسابقه استفاده می‌شود.</p>
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
