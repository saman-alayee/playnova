<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'کدهای تخفیف | PlayNova' })

interface Discount {
  id: number
  code: string
  type: 'percentage' | 'fixed'
  value: number
  used_count: number
  usage_limit: number | null
  expires_at?: string | null
}

const discounts = ref<Discount[]>([])

const form = reactive({
  code: '',
  type: 'percentage' as 'percentage' | 'fixed',
  value: '',
  usage_limit: '',
  expires_at: '',
})

function formatValue(d: Discount) {
  return d.type === 'percentage'
    ? `${d.value}%`
    : `${Number(d.value).toLocaleString('fa-IR')} تومان`
}

function onSubmit() {
  /* preview only */
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">مدیریت کدهای تخفیف</h1>

    <AdminApiNotice />

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
      <h2 class="font-bold mb-4 text-white">ایجاد کد تخفیف جدید</h2>
      <form class="grid sm:grid-cols-2 gap-3" @submit.prevent="onSubmit">
        <input
          v-model="form.code"
          type="text"
          placeholder="کد تخفیف"
          required
          class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
        >
        <select v-model="form.type" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
          <option value="percentage">درصدی</option>
          <option value="fixed">مبلغ ثابت</option>
        </select>
        <input
          v-model="form.value"
          type="number"
          placeholder="مقدار"
          required
          class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
        >
        <input
          v-model="form.usage_limit"
          type="number"
          placeholder="حداکثر تعداد استفاده (۰=نامحدود)"
          class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
        >
        <input
          v-model="form.expires_at"
          type="date"
          class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
        >
        <button type="submit" class="bg-success hover:opacity-90 text-white rounded py-2 font-bold opacity-60 cursor-not-allowed" disabled>
          ایجاد کد
        </button>
      </form>
    </div>

    <div v-if="!discounts.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      کد تخفیفی یافت نشد — پس از توسعه Admin API لیست اینجا نمایش داده می‌شود.
    </div>
    <div v-else class="space-y-2">
      <div
        v-for="d in discounts"
        :key="d.id"
        class="bg-dark-800 border border-dark-600 rounded-xl p-4 flex justify-between items-center"
      >
        <div>
          <span class="font-mono font-bold text-primary">{{ d.code }}</span>
          <span class="text-xs text-gray-400 mr-2">
            {{ formatValue(d) }} — استفاده: {{ d.used_count }}/{{ d.usage_limit || '∞' }}
          </span>
        </div>
        <button type="button" class="text-xs text-red-400 opacity-50 cursor-not-allowed" disabled>حذف</button>
      </div>
    </div>
  </div>
</template>
