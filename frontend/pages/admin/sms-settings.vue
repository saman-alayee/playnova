<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات پیامک | پنل مدیریت' })

interface SmsPattern {
  key: string
  title: string
  body_id: string
  variables: string
}

interface SmsSettingsResponse {
  sms_provider?: string
  sms_username?: string
  sms_sender?: string
  sms_patterns?: Array<{
    key?: string
    title?: string
    body_id?: number | string | null
    variables?: string
  }>
  sms_register_verify?: boolean
  has_api_key?: boolean
}

const api = useApi()
const flash = useState('flash')
const saving = ref(false)
const errors = ref<string[]>([])

const { data, pending, refresh } = await useAsyncData('admin-sms', () => api.admin.smsSettings())

const form = reactive({
  sms_provider: 'test' as 'test' | 'melipayamak',
  sms_username: '',
  sms_api_key: '',
  sms_sender: '',
  sms_register_verify: false,
})

const patterns = ref<SmsPattern[]>([])
const hasSavedApiKey = ref(false)

function defaultPatterns(): SmsPattern[] {
  return [
    { key: 'register', title: 'ثبت‌نام / تأیید موبایل', body_id: '', variables: 'code' },
    { key: 'reset', title: 'فراموشی رمز عبور', body_id: '', variables: 'code' },
  ]
}

function normalizePatterns(raw?: SmsSettingsResponse['sms_patterns']) {
  if (!raw?.length) return defaultPatterns()
  return raw.map((p) => ({
    key: String(p.key || ''),
    title: String(p.title || ''),
    body_id: p.body_id != null && p.body_id !== '' ? String(p.body_id) : '',
    variables: String(p.variables || 'code'),
  }))
}

watch(
  data,
  (d) => {
    const settings = d as SmsSettingsResponse | null
    if (!settings) return
    form.sms_provider = settings.sms_provider === 'melipayamak' ? 'melipayamak' : 'test'
    form.sms_username = String(settings.sms_username || '')
    form.sms_sender = String(settings.sms_sender || '')
    form.sms_register_verify = !!settings.sms_register_verify
    hasSavedApiKey.value = !!settings.has_api_key
    patterns.value = normalizePatterns(settings.sms_patterns)
  },
  { immediate: true },
)

const isMelipayamak = computed(() => form.sms_provider === 'melipayamak')

const panelSaved = computed(
  () =>
    isMelipayamak.value &&
    !!form.sms_username.trim() &&
    !!form.sms_sender.trim() &&
    (hasSavedApiKey.value || !!form.sms_api_key.trim()),
)

function addPattern() {
  patterns.value.push({ key: '', title: '', body_id: '', variables: 'code' })
}

function removePattern(index: number) {
  if (patterns.value.length <= 1) return
  patterns.value.splice(index, 1)
}

function patternHint(key: string) {
  if (key === 'register') return 'متغیر code = کد ۶ رقمی OTP ثبت‌نام'
  if (key === 'reset') return 'متغیر code = کد ۶ رقمی OTP بازیابی رمز'
  return 'متغیرها را با ; جدا کنید (مثلاً code)'
}

async function save() {
  saving.value = true
  errors.value = []
  try {
    const payload: Record<string, unknown> = {
      sms_provider: form.sms_provider,
      sms_username: form.sms_username.trim(),
      sms_sender: form.sms_sender.trim(),
      sms_register_verify: form.sms_register_verify,
      sms_patterns: patterns.value
        .filter((p) => p.key.trim())
        .map((p) => ({
          key: p.key.trim(),
          title: p.title.trim(),
          body_id: p.body_id.trim() ? Number(p.body_id) : null,
          variables: p.variables.trim() || 'code',
        })),
    }

    if (form.sms_api_key.trim()) {
      payload.sms_api_key = form.sms_api_key.trim()
    }

    await api.admin.updateSmsSettings(payload)
    form.sms_api_key = ''
    flash.value = {
      success:
        form.sms_provider === 'melipayamak'
          ? 'ارسال واقعی ملی‌پیامک فعال شد. تنظیمات پنل ذخیره شد.'
          : 'حالت تست فعال است. تنظیمات ذخیره شد.',
    }
    await refresh()
  } catch (e: unknown) {
    errors.value = [(e as Error).message || 'ذخیره تنظیمات ناموفق بود.']
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="sms-settings">
    <h1 class="sms-settings__title">تنظیمات پیامک</h1>

    <div v-if="errors.length" class="sms-settings__errors">
      <p v-for="(err, i) in errors" :key="i">{{ err }}</p>
    </div>

    <div v-if="pending" class="sms-settings__state">در حال بارگذاری...</div>

    <form v-else class="sms-settings__form" @submit.prevent="save">
      <section class="sms-card">
        <h2 class="sms-card__title">حالت ارسال</h2>
        <select v-model="form.sms_provider" class="sms-card__select">
          <option value="test">حالت تست — بدون ارسال واقعی پیامک</option>
          <option value="melipayamak">ارسال واقعی — فعال‌سازی پنل ملی‌پیامک</option>
        </select>
        <p v-if="isMelipayamak" class="sms-card__warn">
          با انتخاب این حالت، پس از ذخیره، OTP ثبت‌نام و بازیابی رمز از طریق API ملی‌پیامک ارسال می‌شود
          (نیاز به تکمیل فیلدهای پنل).
        </p>
      </section>

      <section class="sms-card">
        <div class="sms-card__row">
          <div class="sms-card__row-text">
            <h2 class="sms-card__title sms-card__title--inline">تأیید موبایل بعد از ثبت‌نام</h2>
            <p class="sms-card__hint">
              کاربر پس از فرم ثبت‌نام به صفحه وارد کردن کد ۶ رقمی هدایت می‌شود.
            </p>
          </div>
          <label class="sms-card__checkbox-wrap">
            <input v-model="form.sms_register_verify" type="checkbox" class="sms-card__checkbox">
          </label>
        </div>
      </section>

      <template v-if="isMelipayamak">
        <section class="sms-card">
          <div class="sms-card__header-row">
            <h2 class="sms-card__title sms-card__title--inline">اتصال به پنل ملی‌پیامک</h2>
            <span v-if="panelSaved" class="sms-card__badge">اطلاعات پنل ذخیره شده</span>
          </div>

          <div class="sms-card__info">
            <p class="sms-card__info-title">از پنل payamak-panel.com این موارد را بردارید:</p>
            <ul class="sms-card__info-list">
              <li><code>username</code> — نام کاربری پنل</li>
              <li><code>password</code> — توکن REST (در API به‌جای رمز)</li>
              <li><code>from</code> — خط اختصاصی (برای SendOtp)</li>
              <li><code>bodyId</code> — کد قالب تأییدشده (برای خط خدماتی ...5000)</li>
            </ul>
            <p class="sms-card__info-note">خط ...5000 فقط با کد قالب کار می‌کند.</p>
          </div>

          <label class="sms-field">
            <span class="sms-field__label">نام کاربری پنل (username)</span>
            <input
              v-model="form.sms_username"
              type="text"
              class="sms-field__input"
              dir="ltr"
              autocomplete="off"
            >
          </label>

          <label class="sms-field">
            <span class="sms-field__label">توکن (REST / API Key) (password)</span>
            <input
              v-model="form.sms_api_key"
              type="password"
              class="sms-field__input"
              dir="ltr"
              :placeholder="hasSavedApiKey ? '******** (برای تغییر، توکن جدید وارد کنید)' : 'توکن REST پنل'"
              autocomplete="new-password"
            >
            <p v-if="hasSavedApiKey && !form.sms_api_key" class="sms-field__ok">توکن قبلاً ذخیره شده است.</p>
          </label>

          <label class="sms-field">
            <span class="sms-field__label">خط فرستنده (from)</span>
            <input
              v-model="form.sms_sender"
              type="text"
              class="sms-field__input"
              dir="ltr"
              autocomplete="off"
            >
          </label>
        </section>

        <section class="sms-card">
          <div class="sms-card__header-row">
            <div>
              <h2 class="sms-card__title sms-card__title--inline">قالب‌های پیامک (پترن)</h2>
              <p class="sms-card__hint sms-card__hint--tight">
                هر قالب یک bodyId یکتا و متغیرهای مشخص دارد. کلیدهای register و reset در سیستم استفاده می‌شوند.
              </p>
            </div>
            <button type="button" class="sms-card__add-pattern" @click="addPattern">
              + افزودن قالب
            </button>
          </div>

          <div class="sms-patterns">
            <article v-for="(pattern, index) in patterns" :key="index" class="sms-pattern">
              <div class="sms-pattern__head">
                <button
                  type="button"
                  class="sms-pattern__remove"
                  :disabled="patterns.length <= 1"
                  @click="removePattern(index)"
                >
                  حذف
                </button>
                <span class="sms-pattern__index">قالب #{{ (index + 1).toLocaleString('fa-IR') }}</span>
              </div>

              <div class="sms-pattern__grid">
                <label class="sms-field">
                  <span class="sms-field__label">عنوان (نمایشی)</span>
                  <input v-model="pattern.title" type="text" class="sms-field__input">
                </label>
                <label class="sms-field">
                  <span class="sms-field__label">کلید (key) — انگلیسی</span>
                  <input v-model="pattern.key" type="text" class="sms-field__input" dir="ltr">
                </label>
                <label class="sms-field">
                  <span class="sms-field__label">کد قالب (bodyId)</span>
                  <input v-model="pattern.body_id" type="text" class="sms-field__input" dir="ltr" inputmode="numeric">
                </label>
                <label class="sms-field">
                  <span class="sms-field__label">متغیرها (با ; جدا)</span>
                  <input v-model="pattern.variables" type="text" class="sms-field__input" dir="ltr">
                </label>
              </div>

              <p class="sms-pattern__hint">{{ patternHint(pattern.key) }}</p>
            </article>
          </div>
        </section>
      </template>

      <button type="submit" class="sms-settings__save" :disabled="saving">
        {{ saving ? 'در حال ذخیره...' : 'ذخیره تنظیمات' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.sms-settings {
  max-width: 52rem;
}

.sms-settings__title {
  margin: 0 0 1.25rem;
  font-size: 1.5rem;
  font-weight: 800;
  color: #fff;
}

.sms-settings__errors {
  margin-bottom: 1rem;
  padding: 0.65rem 0.85rem;
  border-radius: 0.55rem;
  border: 1px solid rgba(248, 113, 113, 0.45);
  background: rgba(127, 29, 29, 0.25);
  color: #fecaca;
  font-size: 0.82rem;
}

.sms-settings__errors p {
  margin: 0;
}

.sms-settings__state {
  padding: 2rem 1rem;
  text-align: center;
  color: #9ca3af;
}

.sms-settings__form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.sms-card {
  padding: 1.15rem 1.1rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.85rem;
  background: rgba(17, 24, 39, 0.65);
}

.sms-card__title {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  font-weight: 800;
  color: #fff;
}

.sms-card__title--inline {
  margin-bottom: 0.35rem;
}

.sms-card__select {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.55rem;
  background: rgba(31, 41, 55, 0.9);
  color: #f3f4f6;
  font-size: 0.875rem;
  outline: none;
}

.sms-card__select:focus {
  border-color: rgba(139, 92, 246, 0.65);
}

.sms-card__warn {
  margin: 0.75rem 0 0;
  font-size: 0.78rem;
  line-height: 1.75;
  color: #fbbf24;
}

.sms-card__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.sms-card__row-text {
  flex: 1;
  min-width: 0;
}

.sms-card__hint {
  margin: 0;
  font-size: 0.78rem;
  line-height: 1.7;
  color: #9ca3af;
}

.sms-card__hint--tight {
  margin-top: 0.25rem;
}

.sms-card__checkbox-wrap {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  padding-top: 0.15rem;
}

.sms-card__checkbox {
  width: 1.15rem;
  height: 1.15rem;
  accent-color: #9333ea;
  cursor: pointer;
}

.sms-card__header-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.85rem;
}

.sms-card__badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.65rem;
  border-radius: 999px;
  background: rgba(22, 163, 74, 0.18);
  color: #86efac;
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
}

.sms-card__info {
  margin-bottom: 0.9rem;
  padding: 0.85rem 0.9rem;
  border-radius: 0.55rem;
  border: 1px solid rgba(55, 65, 81, 0.55);
  background: rgba(3, 7, 18, 0.55);
}

.sms-card__info-title {
  margin: 0 0 0.55rem;
  font-size: 0.82rem;
  color: #d1d5db;
}

.sms-card__info-list {
  margin: 0;
  padding: 0 1.1rem 0 0;
  font-size: 0.78rem;
  line-height: 1.85;
  color: #9ca3af;
}

.sms-card__info-list code {
  color: #c4b5fd;
  font-family: ui-monospace, monospace;
  font-size: 0.76rem;
}

.sms-card__info-note {
  margin: 0.55rem 0 0;
  font-size: 0.72rem;
  color: #6b7280;
}

.sms-card__add-pattern {
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.55rem;
  padding: 0.45rem 0.85rem;
  background: rgba(31, 41, 55, 0.65);
  color: #e5e7eb;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}

.sms-card__add-pattern:hover {
  background: rgba(55, 65, 81, 0.65);
  border-color: rgba(107, 114, 128, 0.75);
}

.sms-field {
  display: block;
  margin-bottom: 0.75rem;
}

.sms-field__label {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.78rem;
  color: #9ca3af;
}

.sms-field__input {
  width: 100%;
  padding: 0.6rem 0.85rem;
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.55rem;
  background: rgba(31, 41, 55, 0.9);
  color: #f3f4f6;
  font-size: 0.875rem;
  outline: none;
}

.sms-field__input:focus {
  border-color: rgba(139, 92, 246, 0.65);
}

.sms-field__ok {
  margin: 0.35rem 0 0;
  font-size: 0.72rem;
  color: #86efac;
}

.sms-patterns {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.sms-pattern {
  padding: 0.85rem;
  border: 1px solid rgba(55, 65, 81, 0.55);
  border-radius: 0.65rem;
  background: rgba(3, 7, 18, 0.45);
}

.sms-pattern__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.sms-pattern__remove {
  border: none;
  background: transparent;
  color: #f87171;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

.sms-pattern__remove:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.sms-pattern__index {
  font-size: 0.78rem;
  font-weight: 700;
  color: #c4b5fd;
}

.sms-pattern__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}

.sms-pattern__hint {
  margin: 0.55rem 0 0;
  font-size: 0.72rem;
  color: #6b7280;
  text-align: right;
}

.sms-settings__save {
  align-self: flex-start;
  margin-top: 0.25rem;
  border: none;
  border-radius: 0.55rem;
  padding: 0.65rem 1.35rem;
  background: #16a34a;
  color: #fff;
  font-size: 0.9rem;
  font-weight: 800;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
}

.sms-settings__save:hover:not(:disabled) {
  background: #15803d;
}

.sms-settings__save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 720px) {
  .sms-pattern__grid {
    grid-template-columns: 1fr;
  }

  .sms-card__row {
    flex-direction: column;
  }

  .sms-card__checkbox-wrap {
    align-self: flex-start;
  }
}
</style>
