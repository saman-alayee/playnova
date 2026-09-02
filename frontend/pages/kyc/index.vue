<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
useHead({ title: 'احراز هویت | PlayNova' })

const api = useApi()
const auth = useAuthStore()
const flash = useState('flash')
const { formatToman } = useFormatToman()

const { data, pending, refresh } = usePageData('kyc', () => api.kyc.show())

const documentFile = ref<File | null>(null)
const loading = ref(false)
const errors = ref<string[]>([])

const isVerified = computed(() => auth.user?.kyc_verified || !!auth.user?.kyc_verified_at)
const status = computed(() => data.value?.status ?? data.value?.submission?.status ?? null)
const rejectionReason = computed(() => data.value?.rejection_reason ?? data.value?.submission?.admin_note ?? null)
const canSubmit = computed(() => !status.value || status.value === 'rejected')

const statusLabels: Record<string, string> = {
  pending: 'در انتظار',
  approved: 'تأیید شده',
  rejected: 'رد شده',
}

const walletCap = computed(() => auth.user?.kyc_wallet_cap ?? 1_000_000)

function onDocumentChange(e: Event) {
  const input = e.target as HTMLInputElement
  documentFile.value = input.files?.[0] || null
}

async function submit() {
  if (!documentFile.value) {
    errors.value = ['لطفاً تصویر مدارک را انتخاب کنید.']
    return
  }
  loading.value = true
  errors.value = []
  const formData = new FormData()
  formData.append('document', documentFile.value)
  try {
    await api.kyc.store(formData)
    flash.value = { success: 'مدارک احراز هویت با موفقیت ارسال شد و در انتظار بررسی است.' }
    documentFile.value = null
    await refresh()
    await auth.fetchUser()
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: Record<string, string[]> } }
    errors.value = err.data?.errors
      ? Object.values(err.data.errors).flat()
      : [err.message || 'ارسال مدارک ناموفق بود.']
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-3xl mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-2 text-white">احراز هویت (KYC)</h1>
    <p class="text-xs text-gray-400 mb-4">تصویر با AES-256 رمزنگاری و در مسیر امن ذخیره می‌شود.</p>

    <PageLoading v-if="pending" />
    <template v-else>
      <div
        v-if="isVerified"
        class="mb-4 p-3 rounded-lg border border-green-700 bg-green-900/20 text-green-300 text-sm"
      >
        احراز هویت شما تأیید شده است. سقف واریز کیف پول برداشته شده است.
      </div>
      <div
        v-else
        class="mb-4 p-3 rounded-lg border border-amber-700/60 bg-amber-900/20 text-amber-200 text-sm"
      >
        تا قبل از تأیید احراز هویت، حداکثر موجودی کیف پول {{ formatToman(walletCap) }} است.
      </div>

      <div v-if="status" class="mb-4 p-3 rounded-lg border border-dark-600 bg-dark-900/50 text-sm">
        <p class="text-white">
          وضعیت:
          <strong>{{ statusLabels[status] || status }}</strong>
        </p>
        <p v-if="rejectionReason" class="text-gray-400 mt-1">{{ rejectionReason }}</p>
      </div>

      <template v-if="canSubmit">
        <div class="mb-5 rounded-xl overflow-hidden border border-secondary/30 bg-dark-900/40">
          <img src="/kyc-guide.png" alt="راهنمای احراز هویت PlayNova" class="w-full h-auto">
        </div>

        <div class="mb-4 text-sm text-gray-300 leading-relaxed bg-dark-900/50 border border-dark-600 rounded-lg p-4 space-y-3">
          <p class="font-bold text-secondary">نکات مهم</p>
          <ul class="list-disc list-inside text-gray-400 space-y-1 mr-1">
            <li>یک تصویر واحد ارسال کنید (کارت ملی + کارت بانکی + تعهدنامه در یک عکس)</li>
            <li>از برش دادن تصویر خودداری کنید</li>
            <li>اطلاعات کارت‌ها باید خوانا باشد</li>
            <li>تصویر باید واضح و با کیفیت باشد</li>
            <li>
              روی کارت بانکی،
              <span class="text-amber-300/90">CVV2 و تاریخ انقضا را با تکه کاغذ بپوشانید</span>
            </li>
          </ul>
        </div>

        <div class="mb-4 rounded-lg border border-secondary/30 bg-dark-900/60 p-4">
          <h2 class="text-sm font-bold text-secondary mb-3">تعهدنامه احراز هویت</h2>
          <div class="text-sm text-gray-300 leading-8 space-y-4">
            <p>اینجانب .................. با کد ملی ....................</p>
            <p>
              با انجام احراز هویت، صحت اطلاعات و مدارک ارائه‌شده را تأیید می‌کنم و متعهد می‌شوم از خدمات سایت
              مطابق قوانین و مقررات استفاده نمایم.
            </p>
            <p>
              اینجانب اقرار می‌کنم تمامی واریزهای مالی به حساب سایت با رضایت کامل، آگاهی و از حساب بانکی متعلق به
              خودم انجام می‌شود و مسئولیت هرگونه تخلف یا مغایرت در این خصوص بر عهده اینجانب است.
            </p>
            <div class="pt-2 space-y-2 text-gray-400">
              <p>تاریخ: ..................................</p>
              <p>امضا: ..................................</p>
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-4">
            این متن را روی کاغذ بنویسید یا چاپ کنید، نام و کد ملی خود را درج کنید، امضا و تاریخ بزنید و در کنار
            کارت ملی و کارت بانکی در یک عکس قرار دهید.
          </p>
        </div>

        <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
          <ul class="list-disc list-inside space-y-1">
            <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
          </ul>
        </div>

        <form class="space-y-3" @submit.prevent="submit">
          <div>
            <label class="block text-sm text-gray-300 font-bold mb-1">تصویر مدارک (یک فایل)</label>
            <p class="text-xs text-gray-400 mb-2 leading-relaxed">
              کارت ملی + کارت بانکی (با پوشاندن CVV2 و تاریخ انقضا) + تعهدنامه — همه در یک عکس.
              تصاویر بزرگ‌تر تا ۱۰ مگابایت پذیرفته می‌شوند و سایت قبل از ذخیره آن‌ها را فشرده می‌کند.
            </p>
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp"
              required
              class="w-full text-sm bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:bg-dark-600 file:text-gray-200"
              @change="onDocumentChange"
            >
          </div>
          <button
            type="submit"
            class="w-full bg-success hover:opacity-90 text-white rounded py-2 font-bold disabled:opacity-50"
            :disabled="loading"
          >
            {{ loading ? '...' : 'ارسال مدارک' }}
          </button>
        </form>
      </template>
    </template>
  </div>
</template>
