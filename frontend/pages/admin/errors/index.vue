<script setup lang="ts">
import type { ApiErrorLog } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'خطاهای API | PlayNova' })

const api = useApi()
const flash = useState<{ success?: string; error?: string } | null>('flash')
const { formatDateTime } = usePersianDateTime()

const status = ref('unresolved')
const search = ref('')
const page = ref(1)
const selected = ref<ApiErrorLog | null>(null)
const detailPending = ref(false)

const { data, pending, error, refresh } = usePageData(
  'admin-api-errors',
  () => api.admin.apiErrors({
    status: status.value,
    search: search.value || undefined,
    page: page.value,
  }),
  { watch: [page] },
)

const { data: stats, refresh: refreshStats } = usePageData(
  'admin-api-error-stats',
  () => api.admin.apiErrorStats(),
)

const errors = computed(() => data.value?.items ?? [])

async function openDetail(log: ApiErrorLog) {
  detailPending.value = true
  try {
    selected.value = await api.admin.apiError(log.id)
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    detailPending.value = false
  }
}

async function resolveOne(log: ApiErrorLog) {
  try {
    await api.admin.resolveApiError(log.id)
    flash.value = { success: 'خطا علامت‌گذاری شد.' }
    if (selected.value?.id === log.id) {
      selected.value = { ...selected.value, is_resolved: true }
    }
    await Promise.all([refresh(), refreshStats()])
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function resolveAll() {
  try {
    const result = await api.admin.resolveAllApiErrors()
    flash.value = { success: `${result.count} خطا علامت‌گذاری شد.` }
    selected.value = null
    await Promise.all([refresh(), refreshStats()])
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function deleteAll() {
  if (!import.meta.client) return
  const confirmed = window.confirm('همه خطاهای ثبت‌شده حذف شوند؟ این عمل قابل بازگشت نیست.')
  if (!confirmed) return

  try {
    const result = await api.admin.deleteAllApiErrors()
    flash.value = { success: `${result.count} خطا حذف شد.` }
    selected.value = null
    await Promise.all([refresh(), refreshStats()])
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

function applySearch() {
  page.value = 1
  refresh()
}

watch(status, () => {
  page.value = 1
  refresh()
})

function statusLabel(code: number) {
  if (code >= 500) return 'خطای سرور'
  if (code >= 400) return 'خطای کلاینت'
  return String(code)
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-white">خطاهای API بک‌اند</h1>
        <p v-if="stats" class="text-xs text-gray-500 mt-1">
          {{ stats.unresolved_count.toLocaleString('fa-IR') }} خطای بررسی‌نشده —
          {{ stats.last_24h_count.toLocaleString('fa-IR') }} خطا در ۲۴ ساعت اخیر
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          v-if="stats?.unresolved_count"
          type="button"
          class="text-xs bg-dark-700 hover:bg-dark-600 text-white px-3 py-1.5 rounded"
          @click="resolveAll"
        >
          علامت‌گذاری همه
        </button>
        <button
          v-if="errors.length || stats?.last_24h_count"
          type="button"
          class="text-xs bg-danger/90 hover:bg-danger text-white px-3 py-1.5 rounded"
          @click="deleteAll"
        >
          حذف همه
        </button>
        <NuxtLink to="/admin" class="text-sm text-secondary">← داشبورد</NuxtLink>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
      <button
        v-for="s in ['unresolved', 'resolved', 'all']"
        :key="s"
        type="button"
        class="text-xs px-3 py-1 rounded"
        :class="status === s ? 'bg-secondary text-white' : 'bg-dark-700 text-gray-400'"
        @click="status = s"
      >
        {{ s === 'unresolved' ? 'بررسی‌نشده' : s === 'resolved' ? 'بررسی‌شده' : 'همه' }}
      </button>
    </div>

    <form class="flex gap-2 mb-4" @submit.prevent="applySearch">
      <input
        v-model="search"
        type="text"
        placeholder="جستجو در endpoint، پیام یا exception..."
        class="bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white flex-1 min-w-[200px]"
      >
      <button type="submit" class="text-sm bg-primary text-white px-4 py-2 rounded-lg">جستجو</button>
    </form>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="text-red-400">{{ (error as Error).message }}</div>
    <div v-else-if="!errors.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      خطایی ثبت نشده است.
    </div>

    <div v-else class="grid lg:grid-cols-2 gap-4">
      <div class="space-y-3">
        <div
          v-for="log in errors"
          :key="log.id"
          class="bg-dark-800 border rounded-xl p-4 cursor-pointer transition"
          :class="selected?.id === log.id ? 'border-secondary' : 'border-dark-600 hover:border-dark-500'"
          @click="openDetail(log)"
        >
          <div class="flex flex-wrap justify-between gap-2 mb-2">
            <div class="flex items-center gap-2">
              <span class="text-xs font-mono px-2 py-0.5 rounded" :class="log.status_code >= 500 ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400'">
                {{ log.status_code }}
              </span>
              <span class="text-xs text-gray-500">{{ log.method }}</span>
              <span v-if="!log.is_resolved" class="text-[10px] bg-danger/20 text-danger px-1.5 py-0.5 rounded">جدید</span>
            </div>
            <span class="text-xs text-gray-500">{{ formatDateTime(log.created_at_display || log.created_at) }}</span>
          </div>
          <p class="font-mono text-xs text-secondary break-all mb-1">{{ log.endpoint }}</p>
          <p class="text-sm text-gray-300 line-clamp-2">{{ log.message }}</p>
          <p v-if="log.exception_class" class="text-xs text-gray-500 mt-2 truncate">{{ log.exception_class }}</p>
        </div>
        <AdminPagination v-model:page="page" :meta="data?.meta" />
      </div>

      <div class="bg-dark-800 border border-dark-600 rounded-xl p-4 lg:sticky lg:top-4 h-fit">
        <div v-if="detailPending" class="text-gray-500 text-sm">در حال بارگذاری جزئیات...</div>
        <div v-else-if="!selected" class="text-gray-500 text-sm text-center py-8">
          یک خطا را انتخاب کنید تا جزئیات نمایش داده شود.
        </div>
        <template v-else>
          <div class="flex flex-wrap justify-between gap-2 mb-4">
            <h2 class="font-bold text-white">جزئیات خطا #{{ selected.id }}</h2>
            <button
              v-if="!selected.is_resolved"
              type="button"
              class="text-xs bg-success text-white px-2 py-1 rounded"
              @click="resolveOne(selected)"
            >
              علامت‌گذاری بررسی‌شده
            </button>
          </div>

          <dl class="space-y-3 text-sm">
            <div>
              <dt class="text-gray-500 text-xs mb-1">وضعیت HTTP</dt>
              <dd class="text-white">{{ selected.status_code }} — {{ statusLabel(selected.status_code) }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 text-xs mb-1">Endpoint</dt>
              <dd class="font-mono text-secondary break-all">{{ selected.method }} {{ selected.endpoint }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 text-xs mb-1">پیام</dt>
              <dd class="text-gray-200 whitespace-pre-wrap">{{ selected.message }}</dd>
            </div>
            <div v-if="selected.exception_class">
              <dt class="text-gray-500 text-xs mb-1">Exception</dt>
              <dd class="font-mono text-xs text-yellow-400 break-all">{{ selected.exception_class }}</dd>
            </div>
            <div v-if="selected.user">
              <dt class="text-gray-500 text-xs mb-1">کاربر</dt>
              <dd class="text-gray-200">{{ selected.user.username || selected.user.email || selected.user.id }}</dd>
            </div>
            <div v-if="selected.ip_address">
              <dt class="text-gray-500 text-xs mb-1">IP</dt>
              <dd class="font-mono text-gray-300">{{ selected.ip_address }}</dd>
            </div>
            <div v-if="selected.context">
              <dt class="text-gray-500 text-xs mb-1">Context</dt>
              <dd>
                <pre class="text-xs bg-dark-900 border border-dark-700 rounded p-3 overflow-x-auto text-gray-300">{{ JSON.stringify(selected.context, null, 2) }}</pre>
              </dd>
            </div>
            <div v-if="selected.stack_trace">
              <dt class="text-gray-500 text-xs mb-1">Stack trace</dt>
              <dd>
                <pre class="text-xs bg-dark-900 border border-dark-700 rounded p-3 overflow-x-auto text-gray-400 max-h-64">{{ selected.stack_trace }}</pre>
              </dd>
            </div>
          </dl>
        </template>
      </div>
    </div>
  </div>
</template>
