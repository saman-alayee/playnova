<script setup lang="ts">
import type { RuleSection } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت قوانین | PlayNova' })

const api = useApi()

const { data: rules, pending, error } = await useAsyncData('admin-rules', () => api.rules(), {
  default: () => [] as RuleSection[],
})

const form = reactive({ content: '' })

function truncate(text: string, len = 150) {
  return text.length > len ? `${text.slice(0, len)}…` : text
}

function onSubmit() {
  /* preview only */
}
</script>

<template>
  <div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-primary">📜 مدیریت قوانین و مقررات</h1>

    <AdminApiNotice message="لیست بخش‌ها از API عمومی `/rules` خوانده می‌شود. افزودن، ویرایش و حذف نیاز به توسعه Admin API دارد." />

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
      <h2 class="font-bold text-lg mb-4 text-secondary">➕ افزودن بخش جدید</h2>
      <form class="space-y-3" @submit.prevent="onSubmit">
        <div>
          <label class="block text-gray-300 text-sm mb-1">متن قوانین</label>
          <textarea
            v-model="form.content"
            rows="6"
            required
            class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
            placeholder="متن قوانین را وارد کنید..."
          />
        </div>
        <button type="submit" class="bg-success hover:bg-green-700 text-white rounded px-6 py-2 font-bold transition opacity-60 cursor-not-allowed" disabled>
          ➕ افزودن بخش
        </button>
      </form>
    </div>

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
      <h2 class="font-bold text-lg mb-4 text-primary">📋 بخش‌های موجود ({{ rules?.length ?? 0 }})</h2>

      <div v-if="pending" class="text-gray-500 text-center py-6">در حال بارگذاری...</div>
      <div v-else-if="error" class="text-amber-200 text-center py-6">API `/rules` در دسترس نیست.</div>
      <p v-else-if="!rules?.length" class="text-gray-500 text-center py-6">هیچ بخشی ثبت نشده است.</p>
      <div v-else class="space-y-4">
        <div
          v-for="(rule, index) in rules"
          :key="rule.id"
          class="border border-gray-700 rounded-lg p-4 bg-dark-900/30"
        >
          <div class="flex flex-wrap justify-between items-start gap-3">
            <div class="flex-1">
              <p class="text-xs text-gray-500">بخش {{ index + 1 }}</p>
              <p class="text-sm text-gray-300 mt-1">{{ truncate(rule.content) }}</p>
            </div>
            <div class="flex gap-2">
              <span class="text-xs bg-secondary/20 text-secondary px-3 py-1 rounded opacity-50">✏️ ویرایش</span>
              <span class="text-xs bg-danger/20 text-danger px-3 py-1 rounded opacity-50">🗑️ حذف</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
