<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })

const route = useRoute()
const api = useApi()
const { formatDateTime } = usePersianDateTime()
const userId = Number(route.params.id)
const page = ref(1)
const search = ref('')
const category = ref('all')

const categoryOptions = [
  { value: 'all', label: 'همه دسته‌ها' },
  { value: 'wallet', label: 'کیف پول' },
  { value: 'tournament', label: 'مسابقه' },
  { value: 'profile', label: 'پروفایل' },
  { value: 'auth', label: 'ورود و احراز' },
]

function queryParams() {
  const params: Record<string, string | number> = { page: page.value }
  if (search.value.trim()) params.search = search.value.trim()
  if (category.value !== 'all') params.category = category.value
  return params
}

const hasActiveFilters = computed(() => !!search.value.trim() || category.value !== 'all')

function applyFilters() {
  page.value = 1
  refresh()
}

function resetFilters() {
  search.value = ''
  category.value = 'all'
  page.value = 1
  refresh()
}

interface ActivityLog {
  id: number
  category: string
  action: string
  description?: string
  metadata?: Record<string, unknown>
  actor?: { username?: string }
  created_at?: string
}

const { data, pending, refresh } = await useAsyncData(
  `admin-user-activity-${userId}`,
  () => api.admin.userActivity(userId, queryParams()),
  { watch: [page] },
)

const logs = computed(() => (data.value?.items ?? []) as ActivityLog[])

useHead({ title: 'تاریخچه کاربر | PlayNova' })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">تاریخچه فعالیت کاربر #{{ userId }}</h1>
      <NuxtLink to="/admin/users" class="text-sm text-secondary">← کاربران</NuxtLink>
    </div>

    <AdminFilterBar
      v-model:search="search"
      search-placeholder="جستجو در عملیات یا توضیحات..."
      :show-reset="hasActiveFilters"
      @apply="applyFilters"
      @reset="resetFilters"
    >
      <template #filters>
        <AdminFilterField label="دسته">
          <template #control>
            <select v-model="category" @change="applyFilters">
              <option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </template>
        </AdminFilterField>
      </template>
    </AdminFilterBar>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="!logs.length" class="text-gray-500">فعالیتی با این فیلترها یافت نشد.</div>
    <div v-else class="space-y-2">
      <div v-for="log in logs" :key="log.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4 text-sm">
        <div class="flex flex-wrap justify-between gap-2 mb-1">
          <span class="text-secondary font-bold">{{ log.category }} / {{ log.action }}</span>
          <span class="text-xs text-gray-500">{{ formatDateTime(log.created_at_display || log.created_at) }}</span>
        </div>
        <p v-if="log.description" class="text-gray-300">{{ log.description }}</p>
        <p v-if="log.actor?.username" class="text-xs text-gray-500 mt-1">توسط: {{ log.actor.username }}</p>
      </div>
      <AdminPagination v-model:page="page" :meta="data?.meta" />
    </div>
  </div>
</template>
