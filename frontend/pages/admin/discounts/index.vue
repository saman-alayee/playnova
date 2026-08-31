<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'کدهای تخفیف | PlayNova' })

const api = useApi()
const flash = useState('flash')

interface Discount {
  id: number
  code: string
  type: string
  value: number
  used_count?: number
  usage_limit?: number | null
}

const page = ref(1)

const { data, refresh } = await useAsyncData(
  'admin-discounts',
  () => api.admin.discounts({ page: page.value }),
  { watch: [page] },
)
const discounts = computed(() => (data.value?.items ?? []) as Discount[])

const form = reactive({ code: '', type: 'percentage', value: '', usage_limit: '', expires_at: '' })

async function onSubmit() {
  try {
    await api.admin.createDiscount({
      code: form.code,
      type: form.type,
      value: Number(form.value),
      usage_limit: form.usage_limit ? Number(form.usage_limit) : 0,
      expires_at: form.expires_at || null,
    })
    flash.value = { success: 'کد تخفیف ایجاد شد.' }
    Object.assign(form, { code: '', value: '', usage_limit: '', expires_at: '' })
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function remove(id: number) {
  if (!confirm('حذف شود؟')) return
  await api.admin.deleteDiscount(id)
  await refresh()
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">مدیریت کدهای تخفیف</h1>
    <form class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6 grid sm:grid-cols-2 gap-3" @submit.prevent="onSubmit">
      <input v-model="form.code" required placeholder="کد تخفیف" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <select v-model="form.type" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
        <option value="percentage">درصدی</option>
        <option value="fixed">مبلغ ثابت</option>
      </select>
      <input v-model="form.value" type="number" required placeholder="مقدار" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <input v-model="form.usage_limit" type="number" placeholder="حداکثر استفاده" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <input v-model="form.expires_at" type="date" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <button type="submit" class="bg-success text-white rounded py-2 font-bold">ایجاد کد</button>
    </form>
    <div v-for="d in discounts" :key="d.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4 mb-2 flex justify-between">
      <span class="font-mono text-primary">{{ d.code }}</span>
      <button type="button" class="text-xs text-red-400" @click="remove(d.id)">حذف</button>
    </div>
    <AdminPagination v-model:page="page" :meta="data?.meta" />
  </div>
</template>
