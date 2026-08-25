<script setup lang="ts">
import type { RuleSection } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت قوانین | PlayNova' })

const api = useApi()
const flash = useState('flash')
const { data, refresh } = await useAsyncData('admin-rules', () => api.admin.rules())
const rules = computed(() => (data.value ?? []) as RuleSection[])
const newContent = ref('')

async function addRule() {
  if (!newContent.value.trim()) return
  await api.admin.createRule(newContent.value)
  newContent.value = ''
  flash.value = { success: 'بخش اضافه شد.' }
  await refresh()
}

async function remove(id: number) {
  if (!confirm('حذف شود؟')) return
  await api.admin.deleteRule(id)
  await refresh()
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">مدیریت قوانین</h1>
    <form class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6" @submit.prevent="addRule">
      <textarea v-model="newContent" rows="4" required placeholder="متن بخش جدید..." class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white mb-3" />
      <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold">افزودن بخش</button>
    </form>
    <div v-for="r in rules" :key="r.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4 mb-3">
      <div class="prose prose-invert max-w-none text-sm mb-2" v-html="r.content" />
      <NuxtLink :to="`/admin/rules/${r.id}/edit`" class="text-xs text-secondary mr-2">ویرایش</NuxtLink>
      <button type="button" class="text-xs text-red-400" @click="remove(r.id)">حذف</button>
    </div>
  </div>
</template>
