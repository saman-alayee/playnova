<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
useHead({ title: 'احراز هویت | PlayNova' })

const api = useApi()
const flash = useState('flash')

const { data, pending, refresh } = await useAsyncData('kyc', () => api.kyc.show())

const nationalId = ref('')
const frontFile = ref<File | null>(null)
const backFile = ref<File | null>(null)
const loading = ref(false)
const errors = ref<string[]>([])

async function submit() {
  if (!frontFile.value || !backFile.value) {
    errors.value = ['هر دو تصویر کارت ملی الزامی است.']
    return
  }
  loading.value = true
  errors.value = []
  const formData = new FormData()
  formData.append('national_id', nationalId.value)
  formData.append('document_front', frontFile.value)
  formData.append('document_back', backFile.value)
  try {
    await api.kyc.store(formData)
    flash.value = { success: 'مدارک با موفقیت ارسال شد.' }
    await refresh()
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: Record<string, string[]> } }
    errors.value = err.data?.errors
      ? Object.values(err.data.errors).flat()
      : [err.message || 'ارسال مدارک ناموفق بود.']
  } finally {
    loading.value = false
  }
}

function onFrontChange(e: Event) {
  const input = e.target as HTMLInputElement
  frontFile.value = input.files?.[0] || null
}

function onBackChange(e: Event) {
  const input = e.target as HTMLInputElement
  backFile.value = input.files?.[0] || null
}
</script>

<template>
  <div class="max-w-xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-white">احراز هویت</h1>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else class="bg-dark-800 border border-dark-600 rounded-xl p-6">
      <div v-if="data?.status === 'approved'" class="text-success font-bold mb-4">
        ✅ احراز هویت شما تأیید شده است.
      </div>
      <div v-else-if="data?.status === 'pending'" class="text-amber-300 font-bold mb-4">
        ⏳ مدارک شما در انتظار بررسی است.
      </div>
      <div v-else-if="data?.status === 'rejected'" class="text-danger font-bold mb-4">
        ❌ مدارک رد شده: {{ data.rejection_reason || 'لطفاً مجدداً ارسال کنید.' }}
      </div>

      <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
        </ul>
      </div>

      <form v-if="data?.status !== 'approved' && data?.status !== 'pending'" class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-sm mb-1 text-gray-400">کد ملی</label>
          <input v-model="nationalId" type="text" required inputmode="numeric">
        </div>
        <div>
          <label class="block text-sm mb-1 text-gray-400">تصویر روی کارت ملی</label>
          <input type="file" accept="image/*" required @change="onFrontChange">
        </div>
        <div>
          <label class="block text-sm mb-1 text-gray-400">تصویر پشت کارت ملی</label>
          <input type="file" accept="image/*" required @change="onBackChange">
        </div>
        <button type="submit" class="btn-glow-primary rounded py-2 w-full" :disabled="loading">
          {{ loading ? '...' : 'ارسال مدارک' }}
        </button>
      </form>
    </div>
  </div>
</template>
