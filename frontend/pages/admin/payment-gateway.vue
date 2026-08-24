<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات درگاه پرداخت | پنل مدیریت' })

const form = reactive({
  is_active: false,
  sandbox: true,
  merchant_id: '',
  api_key: '',
  server_ip: '',
})

const callbackUrl = '/wallet/callback'

function onSubmit() {
  /* preview only */
}

function onTest() {
  /* preview only */
}
</script>

<template>
  <div class="max-w-2xl mx-auto">
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700">
      <h2 class="text-2xl font-bold text-center mb-6 text-primary">تنظیمات درگاه پرداخت زیبال</h2>

      <AdminApiNotice />

      <div class="mb-4 rounded-lg border border-amber-700/50 bg-amber-900/20 px-4 py-3 text-sm text-amber-100 space-y-2">
        <p><strong>برای جلوگیری از خطای «درگاه‌ها پاسخگو نیستند»:</strong></p>
        <ul class="list-disc list-inside text-amber-200/90 space-y-1">
          <li>در پنل زیبال، درگاه باید <strong>تأیید و فعال</strong> باشد.</li>
          <li>دامنه <span class="font-mono" dir="ltr">playnova.ir</span> باید ثبت شده باشد.</li>
          <li>IP سرور (پایین) را در پنل زیبال → آی‌پی‌های مجاز ثبت کنید.</li>
          <li>تا قبل از فعال شدن درگاه واقعی، <strong>Sandbox</strong> را روشن بگذارید.</li>
        </ul>
      </div>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div class="flex items-center justify-between bg-dark-900/50 p-4 rounded-lg border border-gray-700">
          <div>
            <label class="text-gray-300 font-bold">فعال‌سازی درگاه واقعی</label>
            <p class="text-sm text-gray-500">در حالت غیرفعال، شارژ کیف پول شبیه‌سازی می‌شود.</p>
          </div>
          <label class="relative inline-flex items-center cursor-not-allowed opacity-60">
            <input v-model="form.is_active" type="checkbox" class="sr-only peer" disabled>
            <div class="w-11 h-6 bg-gray-600 rounded-full peer peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full" />
          </label>
        </div>

        <div class="flex items-center justify-between bg-dark-900/50 p-4 rounded-lg border border-gray-700">
          <div>
            <label class="text-gray-300 font-bold">محیط تست (Sandbox)</label>
            <p class="text-sm text-gray-500">در حالت تست از مرچنت <span class="font-mono" dir="ltr">zibal</span> استفاده می‌شود.</p>
          </div>
          <label class="relative inline-flex items-center cursor-not-allowed opacity-60">
            <input v-model="form.sandbox" type="checkbox" class="sr-only peer" disabled>
            <div class="w-11 h-6 bg-gray-600 rounded-full peer peer-checked:bg-amber-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full" />
          </label>
        </div>

        <div class="border-t border-gray-700 pt-4 space-y-4">
          <p class="text-sm text-gray-400">مرچنت کد را از پنل زیبال → <strong class="text-gray-300">درگاه پرداخت</strong> کپی کنید.</p>

          <div>
            <label class="block text-gray-300 text-sm mb-1">مرچنت کد (Merchant ID)</label>
            <input
              v-model="form.merchant_id"
              type="text"
              placeholder="6a6c95ca6eca8591ace1b9cc"
              class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary font-mono"
              dir="ltr"
              disabled
            >
            <p class="text-xs text-gray-500 mt-1">در Sandbox لازم نیست — خودکار <span class="font-mono">zibal</span> استفاده می‌شود.</p>
          </div>

          <div>
            <label class="block text-gray-300 text-sm mb-1">کلید API (اختیاری — برای IPG لازم نیست)</label>
            <input
              v-model="form.api_key"
              type="text"
              placeholder="توکن REST زیبال"
              class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary font-mono"
              dir="ltr"
              disabled
            >
          </div>

          <div>
            <label class="block text-gray-300 text-sm mb-1">آی‌پی سرور (برای ثبت در پنل زیبال)</label>
            <input
              v-model="form.server_ip"
              type="text"
              placeholder="185.x.x.x"
              class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary font-mono"
              dir="ltr"
              disabled
            >
            <p class="text-xs text-gray-500 mt-1">این IP را در پنل زیبال → آی‌پی‌های مجاز ثبت کنید.</p>
          </div>
        </div>

        <button type="submit" class="w-full bg-success hover:opacity-90 py-3 rounded-lg text-white font-bold opacity-60 cursor-not-allowed" disabled>
          ذخیره تنظیمات
        </button>
      </form>

      <form class="mt-3" @submit.prevent="onTest">
        <button type="submit" class="w-full bg-secondary hover:opacity-90 py-3 rounded-lg text-white font-bold opacity-60 cursor-not-allowed" disabled>
          تست اتصال به زیبال
        </button>
      </form>

      <div class="mt-4 text-sm text-gray-500 space-y-1">
        <p>آدرس بازگشت (Callback): <span class="font-mono text-gray-300" dir="ltr">{{ callbackUrl }}</span></p>
        <p>مبلغ‌ها به <strong>ریال</strong> ارسال می‌شوند (تومان × ۱۰).</p>
        <p>
          مستندات:
          <a class="text-primary hover:underline" href="https://help.zibal.ir/ipg" target="_blank" rel="noopener">help.zibal.ir/ipg</a>
        </p>
      </div>
    </div>
  </div>
</template>
