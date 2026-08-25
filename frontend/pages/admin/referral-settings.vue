<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تنظیمات دعوت | PlayNova' })

const api = useApi()
const flash = useState('flash')
const { data, refresh } = await useAsyncData('admin-referral', () => api.admin.referralSettings())
const bonus = ref(5)
watch(data, (d) => { if (d) bonus.value = d.bonus_percent }, { immediate: true })

async function save() {
  await api.admin.updateReferralSettings(bonus.value)
  flash.value = { success: 'ذخیره شد.' }
  await refresh()
}
</script>

<template>
  <div class="max-w-md">
    <h1 class="text-2xl font-bold mb-6 text-white">تنظیمات دعوت</h1>
    <form class="bg-dark-800 border border-dark-600 rounded-xl p-6" @submit.prevent="save">
      <label class="text-sm text-gray-400">درصد پاداش دعوت</label>
      <input v-model.number="bonus" type="number" min="0" max="100" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 mt-1 text-white mb-4">
      <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold">ذخیره</button>
    </form>
  </div>
</template>
