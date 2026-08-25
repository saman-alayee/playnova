<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'درگاه پرداخت | PlayNova' })

const api = useApi()
const flash = useState('flash')
const { data, refresh } = await useAsyncData('admin-payment', () => api.admin.paymentGateway())

const form = reactive({ merchant_id: '', is_active: false, sandbox: true })
watch(data, (d) => {
  if (!d) return
  form.merchant_id = d.merchant_id || ''
  form.is_active = !!d.is_active
  form.sandbox = d.sandbox !== false
}, { immediate: true })

async function save() {
  await api.admin.updatePaymentGateway(form)
  flash.value = { success: 'ذخیره شد.' }
  await refresh()
}

async function test() {
  const r = await api.admin.testPaymentGateway()
  flash.value = { success: (r as { message?: string })?.message || 'پیکربندی معتبر است.' }
}
</script>

<template>
  <div class="max-w-lg">
    <h1 class="text-2xl font-bold mb-6 text-white">درگاه پرداخت زیبال</h1>
    <form class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-3" @submit.prevent="save">
      <input v-model="form.merchant_id" placeholder="مرچنت کد" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" dir="ltr">
      <label class="flex items-center gap-2 text-sm text-gray-300"><input v-model="form.is_active" type="checkbox"> فعال</label>
      <label class="flex items-center gap-2 text-sm text-gray-300"><input v-model="form.sandbox" type="checkbox"> Sandbox</label>
      <div class="flex gap-2">
        <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold">ذخیره</button>
        <button type="button" class="bg-secondary text-white rounded px-4 py-2 text-sm" @click="test">تست</button>
      </div>
    </form>
  </div>
</template>
