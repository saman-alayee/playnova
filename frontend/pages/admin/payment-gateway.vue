<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات درگاه پرداخت زیبال | PlayNova' })

interface PaymentGatewaySettings {
  merchant_id?: string
  is_active?: boolean
  sandbox?: boolean
  callback_url?: string
  server_ip?: string | null
  detected_server_ip?: string | null
  has_api_key?: boolean
}

const api = useApi()
const flash = useState('flash')
const saving = ref(false)
const testing = ref(false)
const errors = ref<string[]>([])

const { data, pending, refresh } = usePageData('admin-payment', () => api.admin.paymentGateway())

const form = reactive({
  merchant_id: '',
  is_active: false,
  sandbox: true,
  zibal_api_key: '',
  zibal_server_ip: '',
})

const meta = reactive({
  callback_url: 'https://playnova.ir/wallet/callback',
  has_api_key: false,
})

watch(
  data,
  (d) => {
    const settings = d as PaymentGatewaySettings | null
    if (!settings) return
    form.merchant_id = settings.merchant_id || ''
    form.is_active = !!settings.is_active
    form.sandbox = settings.sandbox !== false
    form.zibal_server_ip = settings.server_ip || settings.detected_server_ip || ''
    meta.callback_url = settings.callback_url || meta.callback_url
    meta.has_api_key = !!settings.has_api_key
  },
  { immediate: true },
)

async function save() {
  saving.value = true
  errors.value = []
  try {
    const payload: Record<string, unknown> = {
      merchant_id: form.merchant_id.trim(),
      is_active: form.is_active,
      sandbox: form.sandbox,
      zibal_server_ip: form.zibal_server_ip.trim(),
    }
    if (form.zibal_api_key.trim()) {
      payload.zibal_api_key = form.zibal_api_key.trim()
    }
    await api.admin.updatePaymentGateway(payload)
    form.zibal_api_key = ''
    flash.value = { success: 'تنظیمات درگاه پرداخت زیبال با موفقیت ذخیره شد.' }
    await refresh()
  } catch (e: unknown) {
    errors.value = [(e as Error).message || 'ذخیره تنظیمات ناموفق بود.']
  } finally {
    saving.value = false
  }
}

async function testConnection() {
  testing.value = true
  errors.value = []
  try {
    const r = await api.admin.testPaymentGateway()
    flash.value = { success: (r as { message?: string })?.message || 'پیکربندی زیبال معتبر است.' }
  } catch (e: unknown) {
    errors.value = [(e as Error).message || 'تست اتصال ناموفق بود.']
  } finally {
    testing.value = false
  }
}
</script>

<template>
  <div class="gateway-page">
    <div class="gateway-card">
      <h1 class="gateway-card__title">تنظیمات درگاه پرداخت زیبال</h1>

      <div v-if="errors.length" class="gateway-card__errors">
        <p v-for="(err, i) in errors" :key="i">{{ err }}</p>
      </div>

      <div v-if="pending" class="gateway-card__state">در حال بارگذاری...</div>

      <form v-else class="gateway-form" @submit.prevent="save">
        <div class="gateway-alert">
          <p class="gateway-alert__title">برای جلوگیری از خطای «درگاه‌ها پاسخگو نیستند»:</p>
          <ul class="gateway-alert__list">
            <li>در پنل زیبال، درگاه باید تأیید و فعال باشد.</li>
            <li>دامنه <code>playnova.ir</code> باید ثبت شده باشد.</li>
            <li>IP سرور (پایین صفحه) را در پنل زیبال در بخش «IPهای مجاز» ثبت کنید.</li>
            <li>تا فعال شدن درگاه واقعی، حالت Sandbox را روشن نگه دارید.</li>
          </ul>
        </div>

        <div class="gateway-toggle-card">
          <div class="gateway-toggle-row">
            <div class="gateway-toggle-row__text">
              <p class="gateway-toggle-row__label">فعال‌سازی درگاه واقعی</p>
              <p class="gateway-toggle-row__hint">در حالت غیرفعال، شارژ کیف پول شبیه‌سازی می‌شود.</p>
            </div>
            <label class="gateway-switch">
              <input v-model="form.is_active" type="checkbox" class="gateway-switch__input">
              <span class="gateway-switch__track" />
            </label>
          </div>
        </div>

        <div class="gateway-toggle-card">
          <div class="gateway-toggle-row">
            <div class="gateway-toggle-row__text">
              <p class="gateway-toggle-row__label">محیط تست (Sandbox)</p>
              <p class="gateway-toggle-row__hint">در حالت تست از مرچنت zibal استفاده می‌شود.</p>
            </div>
            <label class="gateway-switch">
              <input v-model="form.sandbox" type="checkbox" class="gateway-switch__input">
              <span class="gateway-switch__track" />
            </label>
          </div>
        </div>

        <div class="gateway-field-block">
          <p class="gateway-field-block__lead">مرچنت کد را از پنل زیبال ← درگاه پرداخت کپی کنید.</p>
          <label class="gateway-field">
            <span class="gateway-field__label">مرچنت کد (Merchant ID)</span>
            <input
              v-model="form.merchant_id"
              type="text"
              class="gateway-field__input gateway-field__input--mono"
              dir="ltr"
              autocomplete="off"
            >
          </label>
          <p class="gateway-field__hint">در Sandbox لازم نیست — خودکار zibal استفاده می‌شود.</p>
        </div>

        <label class="gateway-field">
          <span class="gateway-field__label">کلید API (اختیاری — برای IPG لازم نیست)</span>
          <input
            v-model="form.zibal_api_key"
            type="password"
            class="gateway-field__input"
            dir="ltr"
            :placeholder="meta.has_api_key ? '******** (برای تغییر، توکن جدید وارد کنید)' : 'زیبال REST توکن'"
            autocomplete="new-password"
          >
          <p v-if="meta.has_api_key && !form.zibal_api_key" class="gateway-field__ok">توکن قبلاً ذخیره شده است.</p>
        </label>

        <label class="gateway-field">
          <span class="gateway-field__label">آی‌پی سرور (برای ثبت در پنل زیبال)</span>
          <input
            v-model="form.zibal_server_ip"
            type="text"
            class="gateway-field__input gateway-field__input--mono"
            dir="ltr"
            autocomplete="off"
          >
          <p class="gateway-field__hint">این IP را در پنل زیبال ← آی‌پی‌های مجاز ثبت کنید.</p>
        </label>

        <button type="submit" class="gateway-form__save" :disabled="saving">
          {{ saving ? 'در حال ذخیره...' : 'ذخیره تنظیمات' }}
        </button>

        <button
          type="button"
          class="gateway-form__test"
          :disabled="testing"
          @click="testConnection"
        >
          {{ testing ? 'در حال تست...' : 'تست اتصال به زیبال' }}
        </button>

        <div class="gateway-footer">
          <p>
            آدرس بازگشت (Callback):
            <a :href="meta.callback_url" target="_blank" rel="noopener noreferrer" class="gateway-footer__link" dir="ltr">
              {{ meta.callback_url }}
            </a>
          </p>
          <p>مبلغ‌ها به ریال ارسال می‌شوند (تومان × ۱۰).</p>
          <p>
            مستندات:
            <a href="https://help.zibal.ir/IPG" target="_blank" rel="noopener noreferrer" class="gateway-footer__link">
              help.zibal.ir/IPG
            </a>
          </p>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.gateway-page {
  max-width: 40rem;
  margin: 0 auto;
}

.gateway-card {
  padding: 1.35rem 1.2rem 1.2rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.85rem;
  background: rgba(17, 24, 39, 0.65);
}

.gateway-card__title {
  margin: 0 0 1.15rem;
  text-align: center;
  font-size: 1.25rem;
  font-weight: 800;
  color: #fff;
}

.gateway-card__errors {
  margin-bottom: 0.85rem;
  padding: 0.65rem 0.85rem;
  border-radius: 0.55rem;
  border: 1px solid rgba(248, 113, 113, 0.45);
  background: rgba(127, 29, 29, 0.25);
  color: #fecaca;
  font-size: 0.82rem;
}

.gateway-card__errors p {
  margin: 0;
}

.gateway-card__state {
  padding: 2rem 1rem;
  text-align: center;
  color: #9ca3af;
}

.gateway-form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.gateway-alert {
  padding: 0.85rem 0.9rem;
  border: 1px solid rgba(217, 119, 6, 0.45);
  border-radius: 0.65rem;
  background: rgba(120, 53, 15, 0.12);
}

.gateway-alert__title {
  margin: 0 0 0.55rem;
  font-size: 0.82rem;
  font-weight: 700;
  color: #fbbf24;
}

.gateway-alert__list {
  margin: 0;
  padding: 0 1.1rem 0 0;
  font-size: 0.78rem;
  line-height: 1.85;
  color: #fcd34d;
}

.gateway-alert__list code {
  font-family: ui-monospace, monospace;
  color: #fde68a;
}

.gateway-toggle-card {
  padding: 0.85rem 0.9rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.65rem;
  background: rgba(3, 7, 18, 0.35);
}

.gateway-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.gateway-toggle-row__text {
  min-width: 0;
}

.gateway-toggle-row__label {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 700;
  color: #fff;
}

.gateway-toggle-row__hint {
  margin: 0.2rem 0 0;
  font-size: 0.72rem;
  line-height: 1.6;
  color: #9ca3af;
}

.gateway-switch {
  position: relative;
  flex-shrink: 0;
  display: inline-flex;
  cursor: pointer;
}

.gateway-switch__input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.gateway-switch__track {
  width: 2.65rem;
  height: 1.45rem;
  border-radius: 999px;
  background: #4b5563;
  transition: background 0.2s;
  position: relative;
}

.gateway-switch__track::after {
  content: '';
  position: absolute;
  top: 0.18rem;
  left: 0.18rem;
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 50%;
  background: #fff;
  transition: transform 0.2s;
}

.gateway-switch__input:checked + .gateway-switch__track {
  background: #16a34a;
}

.gateway-switch__input:checked + .gateway-switch__track::after {
  transform: translateX(1.2rem);
}

.gateway-field-block {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.gateway-field-block__lead {
  margin: 0;
  font-size: 0.78rem;
  color: #9ca3af;
}

.gateway-field {
  display: block;
}

.gateway-field__label {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.78rem;
  color: #d1d5db;
}

.gateway-field__input {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.55rem;
  background: rgba(31, 41, 55, 0.9);
  color: #f3f4f6;
  font-size: 0.875rem;
  outline: none;
}

.gateway-field__input--mono {
  font-family: ui-monospace, monospace;
  font-size: 0.82rem;
}

.gateway-field__input:focus {
  border-color: rgba(139, 92, 246, 0.65);
}

.gateway-field__hint {
  margin: 0.35rem 0 0;
  font-size: 0.72rem;
  color: #6b7280;
}

.gateway-field__ok {
  margin: 0.35rem 0 0;
  font-size: 0.72rem;
  color: #86efac;
}

.gateway-form__save {
  width: 100%;
  margin-top: 0.25rem;
  border: none;
  border-radius: 0.55rem;
  padding: 0.7rem 1rem;
  background: #16a34a;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 800;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
}

.gateway-form__save:hover:not(:disabled) {
  background: #15803d;
}

.gateway-form__save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.gateway-form__test {
  width: 100%;
  border: none;
  background: transparent;
  color: #fff;
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
  padding: 0.35rem 0;
}

.gateway-form__test:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.gateway-footer {
  margin-top: 0.35rem;
  padding-top: 0.85rem;
  border-top: 1px solid rgba(55, 65, 81, 0.45);
  font-size: 0.72rem;
  line-height: 1.85;
  color: #6b7280;
}

.gateway-footer p {
  margin: 0;
}

.gateway-footer__link {
  color: #93c5fd;
  text-decoration: none;
}

.gateway-footer__link:hover {
  text-decoration: underline;
}
</style>
