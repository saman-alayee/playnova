<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات SMS | PlayNova' })

const api = useApi()
const flash = useState('flash')
const { data, refresh } = await useAsyncData('admin-sms', () => api.admin.smsSettings())

const form = reactive({
  sms_provider: 'test',
  sms_username: '',
  sms_api_key: '',
  sms_sender: '',
  sms_register_verify: false,
})

watch(data, (d) => {
  if (!d) return
  form.sms_provider = String(d.sms_provider || 'test')
  form.sms_username = String(d.sms_username || '')
  form.sms_sender = String(d.sms_sender || '')
  form.sms_register_verify = !!d.sms_register_verify
}, { immediate: true })

async function save() {
  await api.admin.updateSmsSettings({ ...form })
  flash.value = { success: 'ذخیره شد.' }
  await refresh()
}
</script>

<template>
  <div class="max-w-lg">
    <h1 class="text-2xl font-bold mb-6 text-white">تنظیمات SMS</h1>
    <form class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-3" @submit.prevent="save">
      <select v-model="form.sms_provider" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
        <option value="test">تست</option>
        <option value="melipayamak">ملی‌پیامک</option>
      </select>
      <input v-model="form.sms_username" placeholder="نام کاربری" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <input v-model="form.sms_api_key" placeholder="توکن API (در صورت تغییر)" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <input v-model="form.sms_sender" placeholder="خط فرستنده" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <label class="flex items-center gap-2 text-sm text-gray-300"><input v-model="form.sms_register_verify" type="checkbox"> تأیید موبایل در ثبت‌نام</label>
      <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold">ذخیره</button>
    </form>
  </div>
</template>
