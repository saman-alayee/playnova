<script setup lang="ts">
import type { AdminBroadcastCampaign, Notification } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت اعلان‌ها | PlayNova' })

const api = useApi()
const flash = useState<{ success?: string; error?: string } | null>('flash')
const { formatDateTime } = usePersianDateTime()

const tab = ref<'broadcast' | 'personal'>('broadcast')
const broadcastPage = ref(1)
const personalPage = ref(1)
const editing = ref<AdminBroadcastCampaign | Notification | null>(null)
const editForm = reactive({ title: '', message: '' })
const saving = ref(false)

const selectedBroadcasts = ref<string[]>([])
const selectedPersonal = ref<number[]>([])

const { data: broadcastData, pending: broadcastPending, refresh: refreshBroadcasts } = await useAsyncData(
  'admin-broadcast-campaigns',
  () => api.admin.broadcasts({ page: broadcastPage.value }),
  { watch: [broadcastPage] },
)

const { data: personalData, pending: personalPending, refresh: refreshPersonal } = await useAsyncData(
  'admin-personal-notifications',
  () => api.admin.personalNotifications({ page: personalPage.value }),
  { watch: [personalPage] },
)

const broadcasts = computed(() => broadcastData.value?.items ?? [])
const personalItems = computed(() => personalData.value?.items ?? [])

const allBroadcastsSelected = computed({
  get: () => broadcasts.value.length > 0 && selectedBroadcasts.value.length === broadcasts.value.length,
  set: (value: boolean) => {
    selectedBroadcasts.value = value ? broadcasts.value.map((item) => item.group_id) : []
  },
})

const allPersonalSelected = computed({
  get: () => personalItems.value.length > 0 && selectedPersonal.value.length === personalItems.value.length,
  set: (value: boolean) => {
    selectedPersonal.value = value ? personalItems.value.map((item) => item.id) : []
  },
})

function openEdit(item: AdminBroadcastCampaign | Notification) {
  editing.value = item
  editForm.title = item.title
  editForm.message = item.message || ''
}

function closeEdit() {
  editing.value = null
  editForm.title = ''
  editForm.message = ''
}

async function saveEdit() {
  if (!editing.value) return
  saving.value = true
  try {
    if (tab.value === 'broadcast' && 'group_id' in editing.value) {
      await api.admin.updateBroadcast(editing.value.group_id, editForm)
    } else {
      await api.admin.updatePersonalNotification(editing.value.id, editForm)
    }
    flash.value = { success: 'اعلان ویرایش شد.' }
    closeEdit()
    await refreshCurrent()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    saving.value = false
  }
}

async function refreshCurrent() {
  if (tab.value === 'broadcast') {
    await refreshBroadcasts()
    selectedBroadcasts.value = []
  } else {
    await refreshPersonal()
    selectedPersonal.value = []
  }
}

async function removeBroadcast(groupId: string) {
  if (!confirm('این اعلان برای همه کاربران حذف شود؟')) return
  try {
    const result = await api.admin.deleteBroadcast(groupId)
    flash.value = { success: `${result.deleted_count} اعلان حذف شد.` }
    await refreshBroadcasts()
    selectedBroadcasts.value = selectedBroadcasts.value.filter((id) => id !== groupId)
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function removePersonal(id: number) {
  if (!confirm('حذف شود؟')) return
  try {
    await api.admin.deletePersonalNotification(id)
    flash.value = { success: 'اعلان حذف شد.' }
    await refreshPersonal()
    selectedPersonal.value = selectedPersonal.value.filter((itemId) => itemId !== id)
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function bulkDeleteBroadcasts() {
  if (!selectedBroadcasts.value.length) return
  if (!confirm(`حذف ${selectedBroadcasts.value.length} اعلان کلی؟`)) return
  try {
    const result = await api.admin.bulkDeleteBroadcasts(selectedBroadcasts.value)
    flash.value = { success: `${result.deleted_count} اعلان حذف شد.` }
    selectedBroadcasts.value = []
    await refreshBroadcasts()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function bulkDeletePersonal() {
  if (!selectedPersonal.value.length) return
  if (!confirm(`حذف ${selectedPersonal.value.length} اعلان شخصی؟`)) return
  try {
    const result = await api.admin.bulkDeletePersonalNotifications(selectedPersonal.value)
    flash.value = { success: `${result.deleted_count} اعلان حذف شد.` }
    selectedPersonal.value = []
    await refreshPersonal()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

function toggleBroadcast(groupId: string, checked: boolean) {
  if (checked) {
    if (!selectedBroadcasts.value.includes(groupId)) {
      selectedBroadcasts.value.push(groupId)
    }
    return
  }
  selectedBroadcasts.value = selectedBroadcasts.value.filter((id) => id !== groupId)
}

function togglePersonal(id: number, checked: boolean) {
  if (checked) {
    if (!selectedPersonal.value.includes(id)) {
      selectedPersonal.value.push(id)
    }
    return
  }
  selectedPersonal.value = selectedPersonal.value.filter((itemId) => itemId !== id)
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <h1 class="text-2xl font-bold text-white">مدیریت اعلان‌ها</h1>
      <NuxtLink to="/admin/broadcast" class="text-sm text-secondary">ارسال جدید</NuxtLink>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
      <button
        type="button"
        class="text-sm px-4 py-2 rounded-lg"
        :class="tab === 'broadcast' ? 'bg-secondary text-white' : 'bg-dark-700 text-gray-400'"
        @click="tab = 'broadcast'"
      >
        اعلان‌های کلی
      </button>
      <button
        type="button"
        class="text-sm px-4 py-2 rounded-lg"
        :class="tab === 'personal' ? 'bg-secondary text-white' : 'bg-dark-700 text-gray-400'"
        @click="tab = 'personal'"
      >
        اعلان‌های شخصی
      </button>
    </div>

    <div v-if="tab === 'broadcast'" class="mb-4 flex flex-wrap items-center gap-3">
      <label class="flex items-center gap-2 text-sm text-gray-300">
        <input v-model="allBroadcastsSelected" type="checkbox" class="rounded">
        انتخاب همه
      </label>
      <button
        v-if="selectedBroadcasts.length"
        type="button"
        class="text-xs bg-danger text-white px-3 py-1.5 rounded"
        @click="bulkDeleteBroadcasts"
      >
        حذف انتخاب‌شده‌ها ({{ selectedBroadcasts.length }})
      </button>
    </div>

    <div v-else class="mb-4 flex flex-wrap items-center gap-3">
      <label class="flex items-center gap-2 text-sm text-gray-300">
        <input v-model="allPersonalSelected" type="checkbox" class="rounded">
        انتخاب همه
      </label>
      <button
        v-if="selectedPersonal.length"
        type="button"
        class="text-xs bg-danger text-white px-3 py-1.5 rounded"
        @click="bulkDeletePersonal"
      >
        حذف انتخاب‌شده‌ها ({{ selectedPersonal.length }})
      </button>
    </div>

    <div v-if="tab === 'broadcast'">
      <div v-if="broadcastPending" class="text-gray-500">در حال بارگذاری...</div>
      <div v-else-if="!broadcasts.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
        اعلان کلی ثبت نشده است.
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="item in broadcasts"
          :key="item.group_id"
          class="bg-dark-800 border border-dark-600 rounded-xl p-4"
        >
          <div class="flex gap-3">
            <input
              type="checkbox"
              class="mt-1 rounded"
              :checked="selectedBroadcasts.includes(item.group_id)"
              @change="toggleBroadcast(item.group_id, ($event.target as HTMLInputElement).checked)"
            >
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap justify-between gap-2 mb-2">
                <h3 class="font-bold text-white">{{ item.title }}</h3>
                <span class="text-xs text-gray-500">{{ formatDateTime(item.created_at_display || item.created_at) }}</span>
              </div>
              <p class="text-sm text-gray-400 whitespace-pre-wrap">{{ item.message }}</p>
              <p class="text-xs text-gray-500 mt-2">
                {{ item.recipient_count.toLocaleString('fa-IR') }} کاربر
              </p>
              <div class="flex gap-3 mt-3">
                <button type="button" class="text-xs text-secondary" @click="openEdit(item)">ویرایش</button>
                <button type="button" class="text-xs text-red-400" @click="removeBroadcast(item.group_id)">حذف</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <AdminPagination v-model:page="broadcastPage" :meta="broadcastData?.meta" />
    </div>

    <div v-else>
      <div v-if="personalPending" class="text-gray-500">در حال بارگذاری...</div>
      <div v-else-if="!personalItems.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
        اعلان شخصی ثبت نشده است.
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="item in personalItems"
          :key="item.id"
          class="bg-dark-800 border border-dark-600 rounded-xl p-4"
        >
          <div class="flex gap-3">
            <input
              type="checkbox"
              class="mt-1 rounded"
              :checked="selectedPersonal.includes(item.id)"
              @change="togglePersonal(item.id, ($event.target as HTMLInputElement).checked)"
            >
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap justify-between gap-2 mb-2">
                <h3 class="font-bold text-white">{{ item.title }}</h3>
                <span class="text-xs text-gray-500">{{ formatDateTime(item.created_at_display || item.created_at) }}</span>
              </div>
              <p class="text-sm text-gray-400 whitespace-pre-wrap">{{ item.message }}</p>
              <p v-if="item.user" class="text-xs text-gray-500 mt-2">
                گیرنده: {{ item.user.username || item.user.mobile || item.user.email }}
              </p>
              <div class="flex gap-3 mt-3">
                <button type="button" class="text-xs text-secondary" @click="openEdit(item)">ویرایش</button>
                <button type="button" class="text-xs text-red-400" @click="removePersonal(item.id)">حذف</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <AdminPagination v-model:page="personalPage" :meta="personalData?.meta" />
    </div>

    <div
      v-if="editing"
      class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4"
      @click.self="closeEdit"
    >
      <form class="bg-dark-800 border border-dark-600 rounded-xl p-6 w-full max-w-lg space-y-3" @submit.prevent="saveEdit">
        <h2 class="text-lg font-bold text-white mb-2">ویرایش اعلان</h2>
        <input
          v-model="editForm.title"
          required
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        >
        <textarea
          v-model="editForm.message"
          required
          rows="5"
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        />
        <div class="flex gap-2 justify-end">
          <button type="button" class="px-4 py-2 text-sm text-gray-400" @click="closeEdit">انصراف</button>
          <button type="submit" class="px-4 py-2 text-sm bg-secondary text-white rounded" :disabled="saving">
            ذخیره
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
