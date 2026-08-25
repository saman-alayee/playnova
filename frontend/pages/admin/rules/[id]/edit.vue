<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const id = Number(route.params.id)

const { data: rules } = await useAsyncData('admin-rules-edit', () => api.admin.rules())
const rule = computed(() => rules.value?.find((r) => r.id === id))
const content = ref(rule.value?.content || '')

watch(rule, (r) => { if (r) content.value = r.content }, { immediate: true })

async function save() {
  await api.admin.updateRule(id, content.value)
  router.push('/admin/rules/manage')
}
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="text-2xl font-bold mb-6 text-white">ویرایش بخش قوانین</h1>
    <textarea v-model="content" rows="12" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white mb-4" />
    <button type="button" class="bg-success text-white rounded px-4 py-2 font-bold" @click="save">ذخیره</button>
  </div>
</template>
