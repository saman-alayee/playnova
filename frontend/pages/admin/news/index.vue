<script setup lang="ts">
import type { NewsItem } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'اخبار | PlayNova' })

const api = useApi()
const flash = useState('flash')
const page = ref(1)
const search = ref('')

function queryParams() {
  const params: Record<string, string | number> = { page: page.value }
  if (search.value.trim()) params.search = search.value.trim()
  return params
}

const hasActiveFilters = computed(() => !!search.value.trim())

function applyFilters() {
  page.value = 1
  refresh()
}

function resetFilters() {
  search.value = ''
  page.value = 1
  refresh()
}

const { data, refresh } = usePageData(
  'admin-news',
  () => api.admin.news(queryParams()),
  { watch: [page] },
)
const items = computed(() => (data.value?.items ?? []) as NewsItem[])

const form = reactive({ title: '', content: '' })
const imageFile = ref<File | null>(null)

async function submit() {
  const fd = new FormData()
  fd.append('title', form.title)
  fd.append('content', form.content)
  if (imageFile.value) fd.append('image', imageFile.value)
  try {
    await api.admin.createNews(fd)
    flash.value = { success: 'خبر منتشر شد.' }
    form.title = ''
    form.content = ''
    imageFile.value = null
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function remove(id: number) {
  if (!confirm('حذف شود؟')) return
  await api.admin.deleteNews(id)
  await refresh()
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">مدیریت اخبار</h1>
    <AdminFilterBar
      v-model:search="search"
      search-placeholder="جستجو در عنوان یا متن خبر..."
      :show-reset="hasActiveFilters"
      @apply="applyFilters"
      @reset="resetFilters"
    />
    <form class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6 space-y-3" @submit.prevent="submit">
      <input v-model="form.title" required placeholder="عنوان" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <textarea v-model="form.content" required rows="4" placeholder="متن خبر" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" />
      <input type="file" accept="image/*" class="text-sm text-gray-400" @change="imageFile = ($event.target as HTMLInputElement).files?.[0] ?? null">
      <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold">انتشار</button>
    </form>
    <div v-if="!items.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500 mb-2">
      خبری با این فیلترها یافت نشد.
    </div>
    <div v-for="n in items" :key="n.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4 mb-2 flex justify-between">
      <span class="text-white">{{ n.title }}</span>
      <button type="button" class="text-xs text-red-400" @click="remove(n.id)">حذف</button>
    </div>
    <AdminPagination v-model:page="page" :meta="data?.meta" />
  </div>
</template>
