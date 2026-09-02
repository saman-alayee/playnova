<script setup lang="ts">
import type { AvalAiCredit } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات هوش مصنوعی | PlayNova' })

interface AiModelOption {
  id: string
  label_fa: string
  note_fa: string
  recommended_for: string[]
}

interface AiModelCategory {
  id: string
  label: string
  description: string
  models: AiModelOption[]
}

const api = useApi()
const flash = useState('flash')
const { formatToman } = useFormatToman()
const { formatDateTime } = usePersianDateTime()
const { data, refresh } = usePageData('admin-ai', () => api.admin.aiSettings())

const form = reactive({
  base_url: '',
  vision_model: 'gpt-4o',
  result_vision_model: 'gpt-5.5',
  timeout: 120,
  api_key: '',
  is_active: true,
  clear_api_key: false,
})

const testing = ref(false)
const refreshingModels = ref(false)
const refreshingCredit = ref(false)
const testResult = ref<{ ok: boolean; message: string } | null>(null)
const modelCategories = ref<AiModelCategory[]>([])
const credit = ref<AvalAiCredit | null>(null)
const creditError = ref<string | null>(null)

watch(data, (d) => {
  if (!d) return
  form.base_url = String(d.base_url || '')
  form.vision_model = String(d.vision_model || 'gpt-4o')
  form.result_vision_model = String(d.result_vision_model || d.recommended_result_model || 'gpt-5.5')
  form.timeout = Number(d.timeout || 120)
  form.is_active = !!d.is_active
  form.api_key = ''
  form.clear_api_key = false
  modelCategories.value = d.model_categories?.length
    ? [...d.model_categories]
    : buildCategoriesFromFlat(d.available_models || d.suggested_models || [])
  credit.value = d.credit || null
  creditError.value = d.credit_error || null
}, { immediate: true })

function buildCategoriesFromFlat(models: string[]): AiModelCategory[] {
  if (!models.length) return []
  return [{
    id: 'other',
    label: 'همه مدل‌ها',
    description: 'لیست دریافت‌شده از API',
    models: models.map((id) => ({
      id,
      label_fa: id,
      note_fa: '',
      recommended_for: [],
    })),
  }]
}

function ensureModelInCategories(model: string) {
  if (!model) return
  const exists = modelCategories.value.some((cat) => cat.models.some((m) => m.id === model))
  if (!exists) {
    const other = modelCategories.value.find((c) => c.id === 'other')
    if (other) {
      other.models.unshift({ id: model, label_fa: model, note_fa: 'مدل انتخاب‌شده', recommended_for: [] })
    } else {
      modelCategories.value.push({
        id: 'other',
        label: 'سایر مدل‌ها',
        description: '',
        models: [{ id: model, label_fa: model, note_fa: '', recommended_for: [] }],
      })
    }
  }
}

function modelOptionLabel(model: AiModelOption, forResult = false) {
  const parts = [model.label_fa !== model.id ? `${model.label_fa} (${model.id})` : model.id]
  if (model.note_fa) parts.push(`— ${model.note_fa}`)
  if (forResult && model.recommended_for.includes('result')) {
    parts.push('★ پیشنهاد تحلیل نتیجه')
  } else if (model.recommended_for.includes('vision') && !forResult) {
    parts.push('✓ مناسب تست')
  }
  return parts.join(' ')
}

function tierBadgeClass(tierId: string) {
  const map: Record<string, string> = {
    economy: 'ai-tier--economy',
    balanced: 'ai-tier--balanced',
    premium: 'ai-tier--premium',
    other: 'ai-tier--other',
  }
  return map[tierId] || 'ai-tier--other'
}

const apiKeySourceLabel = computed(() => {
  const source = data.value?.api_key_source
  if (source === 'database') return 'ذخیره‌شده در پنل'
  if (source === 'env') return 'از فایل .env'
  return 'تنظیم نشده'
})

async function refreshCredit() {
  if (!data.value?.has_api_key) return
  refreshingCredit.value = true
  try {
    credit.value = await api.admin.aiCredit()
    creditError.value = null
  } catch (e: unknown) {
    const err = e as { data?: { message?: string }; message?: string }
    creditError.value = err.data?.message || err.message || 'دریافت موجودی ناموفق بود.'
  } finally {
    refreshingCredit.value = false
  }
}

const creditLow = computed(() => {
  const remaining = credit.value?.remaining_irt ?? 0
  return remaining > 0 && remaining < 100000
})

async function refreshModels() {
  refreshingModels.value = true
  try {
    const res = await api.admin.aiModels()
    if (res.model_categories?.length) {
      modelCategories.value = res.model_categories
    } else if (res.models?.length) {
      modelCategories.value = buildCategoriesFromFlat(res.models)
    }
    ensureModelInCategories(form.vision_model)
    ensureModelInCategories(form.result_vision_model)
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
  <div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-2 text-white">تنظیمات هوش مصنوعی (AvalAI)</h1>
    <p class="text-sm text-gray-400 mb-4">
      برای تشخیص خودکار نتیجه مسابقه از اسکرین‌شات. مقادیر پنل بر .env اولویت دارند.
    </p>

    <section class="ai-credit mb-6">
      <div class="ai-credit__head">
        <h2 class="ai-credit__title">موجودی اعتبار AvalAI</h2>
        <button
          type="button"
          class="text-xs text-secondary disabled:opacity-50"
          :disabled="refreshingCredit || !data?.has_api_key"
          @click="refreshCredit"
        >
          {{ refreshingCredit ? 'در حال بروزرسانی…' : 'بروزرسانی موجودی' }}
        </button>
      </div>

      <p v-if="!data" class="text-sm text-gray-400">در حال دریافت موجودی…</p>
      <p v-else-if="!data.has_api_key" class="text-sm text-gray-400">
        برای دیدن موجودی، ابتدا کلید API را ذخیره کنید.
      </p>
      <p v-else-if="creditError && !credit" class="text-sm text-red-300">
        {{ creditError }}
      </p>
      <div v-else-if="credit" class="ai-credit__body">
        <div class="ai-credit__stats">
          <div>
            <p class="ai-credit__label">باقی‌مانده</p>
            <p class="ai-credit__value" :class="{ 'ai-credit__value--low': creditLow }">
              {{ formatToman(credit.remaining_irt) }}
            </p>
          </div>
          <div>
            <p class="ai-credit__label">سطح حساب</p>
            <p class="ai-credit__value">{{ credit.account_tier.toLocaleString('fa-IR') }} از ۵</p>
          </div>
          <div v-if="credit.remaining_unit > 0">
            <p class="ai-credit__label">واحد (دلار)</p>
            <p class="ai-credit__value" dir="ltr">{{ credit.remaining_unit.toFixed(4) }}</p>
          </div>
        </div>
        <p v-if="creditLow" class="text-xs text-amber-300 mt-3">
          موجودی کمتر از ۱۰۰٬۰۰۰ تومان است. برای تحلیل نتیجه مسابقه شارژ کنید.
        </p>
        <p v-if="creditError" class="text-xs text-red-300 mt-2">{{ creditError }}</p>

        <div v-if="credit.packages.length" class="ai-credit__sources">
          <h3>بسته‌های فعال</h3>
          <ul>
            <li v-for="pkg in credit.packages" :key="'p-' + pkg.id">
              <span class="ai-credit__source-name">{{ pkg.name || 'بسته' }}</span>
              <span>{{ formatToman(pkg.remaining_irt) }} از {{ formatToman(pkg.amount_irt) }}</span>
              <span v-if="pkg.end_date" class="text-gray-500">تا {{ formatDateTime(pkg.end_date) }}</span>
            </li>
          </ul>
        </div>
        <div v-if="credit.grants.length" class="ai-credit__sources">
          <h3>گرنت‌های فعال</h3>
          <ul>
            <li v-for="grant in credit.grants" :key="'g-' + grant.id">
              <span class="ai-credit__source-name">{{ grant.description || grant.name || 'گرنت' }}</span>
              <span>{{ formatToman(grant.remaining_irt) }} از {{ formatToman(grant.amount_irt) }}</span>
              <span v-if="grant.end_date" class="text-gray-500">تا {{ formatDateTime(grant.end_date) }}</span>
            </li>
          </ul>
        </div>
      </div>
      <p v-else class="text-sm text-gray-400">در حال دریافت موجودی…</p>
    </section>

    <div v-if="modelCategories.length" class="ai-tier-guide mb-6">
      <h2 class="ai-tier-guide__title">راهنمای دسته‌بندی مدل‌ها</h2>
      <div class="ai-tier-guide__grid">
        <div
          v-for="cat in modelCategories"
          :key="cat.id"
          class="ai-tier-card"
          :class="tierBadgeClass(cat.id)"
        >
          <h3 class="ai-tier-card__label">{{ cat.label }}</h3>
          <p class="ai-tier-card__desc">{{ cat.description }}</p>
          <p class="ai-tier-card__count">{{ cat.models.length.toLocaleString('fa-IR') }} مدل</p>
        </div>
      </div>
    </div>

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
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white text-sm"
        >
          <optgroup
            v-for="cat in modelCategories"
            :key="'v-' + cat.id"
            :label="cat.label"
          >
            <option
              v-for="m in cat.models"
              :key="'v-' + m.id"
              :value="m.id"
            >
              {{ modelOptionLabel(m) }}
            </option>
          </optgroup>
        </select>
        <p class="text-xs text-gray-500 mt-1">برای تست اتصال، مدل‌های «ارزان» یا «متعادل» کافی است.</p>
      </div>

      <div>
        <label class="block text-sm text-gray-400 mb-1">مدل تحلیل نتیجه مسابقه</label>
        <select
          v-model="form.result_vision_model"
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white text-sm"
        >
          <optgroup
            v-for="cat in modelCategories"
            :key="'r-' + cat.id"
            :label="cat.label"
          >
            <option
              v-for="m in cat.models"
              :key="'r-' + m.id"
              :value="m.id"
            >
              {{ modelOptionLabel(m, true) }}
            </option>
          </optgroup>
        </select>
        <p class="text-xs text-gray-500 mt-1">
          برای خواندن همه رتبه‌های جایزه از اسکرین‌شات، مدل «پریمیوم» توصیه می‌شود (مثلاً {{ data?.recommended_result_model || 'gpt-5.5' }}).
        </p>
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

<style scoped>
.ai-credit {
  background: rgba(17, 24, 39, 0.7);
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.75rem;
  padding: 1rem 1.1rem;
}

.ai-credit__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.ai-credit__title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 800;
  color: #e5e7eb;
}

.ai-credit__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr));
  gap: 0.75rem;
}

.ai-credit__label {
  margin: 0 0 0.2rem;
  font-size: 0.7rem;
  color: #9ca3af;
}

.ai-credit__value {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  color: #86efac;
}

.ai-credit__value--low {
  color: #fcd34d;
}

.ai-credit__sources {
  margin-top: 0.85rem;
}

.ai-credit__sources h3 {
  margin: 0 0 0.4rem;
  font-size: 0.75rem;
  font-weight: 800;
  color: #d1d5db;
}

.ai-credit__sources ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.4rem;
}

.ai-credit__sources li {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.75rem;
  font-size: 0.75rem;
  color: #9ca3af;
}

.ai-credit__source-name {
  color: #e5e7eb;
  font-weight: 700;
}

.ai-tier-guide__title {
  margin: 0 0 0.65rem;
  font-size: 0.9rem;
  font-weight: 800;
  color: #e5e7eb;
}

.ai-tier-guide__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
  gap: 0.5rem;
}

.ai-tier-card {
  border-radius: 0.65rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  padding: 0.65rem 0.75rem;
  background: rgba(17, 24, 39, 0.55);
}

.ai-tier-card__label {
  margin: 0 0 0.25rem;
  font-size: 0.78rem;
  font-weight: 800;
}

.ai-tier-card__desc {
  margin: 0;
  font-size: 0.68rem;
  line-height: 1.55;
  color: #9ca3af;
}

.ai-tier-card__count {
  margin: 0.35rem 0 0;
  font-size: 0.65rem;
  color: #6b7280;
}

.ai-tier--economy {
  border-color: rgba(34, 197, 94, 0.45);
}

.ai-tier--economy .ai-tier-card__label {
  color: #86efac;
}

.ai-tier--balanced {
  border-color: rgba(59, 130, 246, 0.45);
}

.ai-tier--balanced .ai-tier-card__label {
  color: #93c5fd;
}

.ai-tier--premium {
  border-color: rgba(251, 191, 36, 0.5);
}

.ai-tier--premium .ai-tier-card__label {
  color: #fcd34d;
}

.ai-tier--other {
  border-color: rgba(107, 114, 128, 0.45);
}

.ai-tier--other .ai-tier-card__label {
  color: #d1d5db;
}
</style>
