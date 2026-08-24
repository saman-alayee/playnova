<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات پیامک | پنل مدیریت' })

interface SmsPattern {
  key: string
  title: string
  body_id: string
  variables: string
}

const provider = ref<'test' | 'melipayamak'>('test')
const smsRegisterVerify = ref(false)

const form = reactive({
  sms_username: '',
  sms_api_key: '',
  sms_sender: '',
})

const patterns = ref<SmsPattern[]>([
  { key: 'register', title: 'ثبت‌نام', body_id: '', variables: 'code' },
])

function addPattern() {
  patterns.value.push({ key: '', title: '', body_id: '', variables: 'code' })
}

function removePattern(index: number) {
  if (patterns.value.length > 1) {
    patterns.value.splice(index, 1)
  }
}

function onSubmit() {
  /* preview only */
}
</script>

<template>
  <div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-2 text-primary">تنظیمات پیامک (SMS)</h1>
    <p class="text-sm text-gray-500 mb-6">
      سرویس: ملی‌پیامک — <span class="text-gray-400">SendOtp</span> یا <span class="text-gray-400">BaseServiceNumber (پترن)</span>
    </p>

    <AdminApiNotice />

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-6">
      <form class="space-y-6" @submit.prevent="onSubmit">
        <div class="rounded-lg border border-dark-600 bg-dark-900/40 p-4 space-y-3">
          <label for="sms_provider" class="block text-gray-200 font-bold">حالت ارسال</label>
          <select
            id="sms_provider"
            v-model="provider"
            class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2.5 text-white outline-none focus:border-secondary"
            disabled
          >
            <option value="test">حالت تست — کد OTP روی صفحه (بدون اتصال به پنل)</option>
            <option value="melipayamak">ارسال واقعی — فعال‌سازی پنل ملی‌پیامک</option>
          </select>
          <p v-if="provider === 'test'" class="text-xs text-gray-500 leading-relaxed">
            در حالت تست می‌توانید اطلاعات پنل را پایین وارد و ذخیره کنید؛ تا زمانی که «ارسال واقعی» را انتخاب نکنید، پیامکی ارسال نمی‌شود.
          </p>
          <p v-else class="text-xs text-amber-400/90 leading-relaxed">
            با انتخاب این حالت، پس از ذخیره، OTP ثبت‌نام و بازیابی رمز از طریق API ملی‌پیامک ارسال می‌شود (نیاز به تکمیل فیلدهای پنل).
          </p>
        </div>

        <div class="rounded-lg border border-dark-600 bg-dark-900/40 p-4">
          <div class="flex items-center justify-between gap-4">
            <div class="text-right flex-1">
              <p class="text-sm text-gray-200 font-bold">تأیید موبایل بعد از ثبت‌نام</p>
              <p class="text-xs text-gray-500 mt-1">کاربر پس از فرم ثبت‌نام به صفحه وارد کردن کد ۶ رقمی هدایت می‌شود.</p>
            </div>
            <input v-model="smsRegisterVerify" type="checkbox" class="h-5 w-5 shrink-0 accent-green-500" disabled>
          </div>
        </div>

        <div class="border-t border-dark-600 pt-5 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-gray-200">اتصال به پنل ملی‌پیامک</h2>
            <span class="text-xs px-2 py-1 rounded-full bg-gray-800 text-gray-400 border border-gray-700">ناقص — برای ارسال واقعی پر کنید</span>
          </div>

          <div class="text-xs text-gray-500 bg-dark-900/60 border border-dark-600 rounded-lg p-3 leading-relaxed space-y-1">
            <p>
              از پنل
              <a href="https://login.payamak-panel.com/" target="_blank" rel="noopener" class="text-secondary hover:underline">payamak-panel.com</a>
              این موارد را بردارید:
            </p>
            <ul class="list-disc list-inside text-gray-400 space-y-0.5 mr-1">
              <li><strong class="text-gray-300">username</strong> — نام کاربری پنل</li>
              <li><strong class="text-gray-300">password</strong> — توکن REST (در API به‌جای رمز)</li>
              <li><strong class="text-gray-300">from</strong> — خط اختصاصی (برای SendOtp)</li>
              <li><strong class="text-gray-300">bodyId</strong> — کد قالب تأیید‌شده (برای خط خدماتی 5000…)</li>
              <li>خط <strong class="text-gray-300">5000…</strong> فقط با کد قالب کار می‌کند</li>
            </ul>
          </div>

          <div>
            <label for="sms_username" class="block text-gray-300 text-sm mb-1">نام کاربری پنل (username)</label>
            <input
              id="sms_username"
              v-model="form.sms_username"
              type="text"
              class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white outline-none focus:border-secondary"
              placeholder="مثال: 0912xxxxxxx یا نام کاربری پنل"
              dir="ltr"
              disabled
            >
          </div>

          <div>
            <label for="sms_api_key" class="block text-gray-300 text-sm mb-1">توکن REST / API Key (password)</label>
            <input
              id="sms_api_key"
              v-model="form.sms_api_key"
              type="password"
              autocomplete="new-password"
              class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white outline-none focus:border-secondary"
              dir="ltr"
              placeholder="توکن دریافتی از بخش API پنل"
              disabled
            >
          </div>

          <div>
            <label for="sms_sender" class="block text-gray-300 text-sm mb-1">خط فرستنده (from)</label>
            <input
              id="sms_sender"
              v-model="form.sms_sender"
              type="text"
              class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white outline-none focus:border-secondary"
              dir="ltr"
              placeholder="1000xxxxxxx یا 5000xxxxxxxxxx"
              disabled
            >
          </div>
        </div>

        <div class="border-t border-dark-600 pt-5 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 class="text-lg font-bold text-gray-200">قالب‌های پیامک (پترن)</h2>
              <p class="text-xs text-gray-500 mt-1">
                هر قالب یک bodyId و متغیرهای جداگانه دارد. کلید <code class="text-secondary">register</code> و <code class="text-secondary">reset</code> در سیستم استفاده می‌شوند.
              </p>
            </div>
            <button
              type="button"
              class="text-sm px-3 py-1.5 rounded-lg border border-secondary/50 text-secondary hover:bg-secondary/10"
              @click="addPattern"
            >
              + افزودن قالب
            </button>
          </div>

          <div class="space-y-3">
            <div
              v-for="(pattern, index) in patterns"
              :key="index"
              class="rounded-lg border border-dark-600 bg-dark-900/50 p-4 space-y-3"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-gray-500">قالب #{{ index + 1 }}</span>
                <button
                  v-if="patterns.length > 1"
                  type="button"
                  class="text-xs text-red-400 hover:underline"
                  @click="removePattern(index)"
                >
                  حذف
                </button>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-gray-300 text-xs mb-1">عنوان (نمایشی)</label>
                  <input
                    v-model="pattern.title"
                    type="text"
                    class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm outline-none focus:border-secondary"
                    placeholder="مثال: ثبت‌نام"
                    disabled
                  >
                </div>
                <div>
                  <label class="block text-gray-300 text-xs mb-1">کلید (key) — انگلیسی</label>
                  <input
                    v-model="pattern.key"
                    type="text"
                    class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm outline-none focus:border-secondary"
                    dir="ltr"
                    placeholder="register"
                    disabled
                  >
                </div>
                <div>
                  <label class="block text-gray-300 text-xs mb-1">کد قالب (bodyId)</label>
                  <input
                    v-model="pattern.body_id"
                    type="number"
                    class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm outline-none focus:border-secondary"
                    dir="ltr"
                    placeholder="509624"
                    min="1"
                    disabled
                  >
                </div>
                <div>
                  <label class="block text-gray-300 text-xs mb-1">متغیرها (با ; جدا)</label>
                  <input
                    v-model="pattern.variables"
                    type="text"
                    class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm outline-none focus:border-secondary"
                    dir="ltr"
                    placeholder="code"
                    disabled
                  >
                </div>
              </div>
              <p class="text-xs text-gray-500">
                متغیر <code class="text-secondary">code</code> = کد ۶ رقمی OTP. برای قالب چندمتغیره: <span dir="ltr">code;name</span>
              </p>
            </div>
          </div>
        </div>

        <button type="submit" class="w-full bg-success hover:opacity-90 text-white rounded-lg py-3 font-bold opacity-60 cursor-not-allowed" disabled>
          ذخیره تنظیمات
        </button>
      </form>

      <div class="border-t border-dark-600 pt-4 text-sm text-gray-500 space-y-1">
        <p>🔹 <span class="text-yellow-400/90">حالت تست</span>: کد OTP روی صفحه نمایش داده می‌شود؛ مناسب تا آماده شدن پنل.</p>
        <p>🔹 <span class="text-green-400/90">ارسال واقعی</span>: پس از پر کردن username، توکن و from، پیامک از ملی‌پیامک ارسال می‌شود.</p>
      </div>
    </div>
  </div>
</template>
