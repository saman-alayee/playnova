<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'پیام همگانی | PlayNova' })

const api = useApi()
const flash = useState('flash')
const form = reactive({ title: '', message: '' })
const loading = ref(false)

async function submit() {
  loading.value = true
  try {
    await api.admin.sendBroadcast(form)
    flash.value = { success: 'پیام در صف ارسال قرار گرفت.' }
    form.title = ''
    form.message = ''
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6 text-white">ارسال پیام همگانی</h1>
    <form class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-3" @submit.prevent="submit">
      <input v-model="form.title" required placeholder="عنوان" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <textarea v-model="form.message" required rows="5" placeholder="متن پیام" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" />
      <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold" :disabled="loading">ارسال</button>
    </form>
    <NuxtLink to="/admin/broadcast-manage" class="inline-block mt-4 text-sm text-secondary">مدیریت پیام‌های قبلی →</NuxtLink>
  </div>
</template>
