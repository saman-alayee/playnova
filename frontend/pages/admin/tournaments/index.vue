<script setup lang="ts">
import type { Tournament } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت مسابقات | PlayNova' })

const api = useApi()
const flash = useState<{ success?: string; error?: string } | null>('flash')
const page = ref(1)
const search = ref('')
const status = ref('all')
const sort = ref('newest')

const statusOptions = [
  { value: 'all', label: 'همه وضعیت‌ها' },
  { value: 'upcoming', label: 'آینده' },
  { value: 'active', label: 'فعال' },
  { value: 'ongoing', label: 'در حال برگزاری' },
  { value: 'ended', label: 'پایان یافته' },
  { value: 'cancelled', label: 'لغو شده' },
]

const sortOptions = [
  { value: 'newest', label: 'جدیدترین' },
  { value: 'start_date', label: 'تاریخ شروع' },
  { value: 'entry_fee', label: 'بیشترین ورودی' },
  { value: 'capacity', label: 'بیشترین ظرفیت' },
]

function queryParams() {
  const params: Record<string, string | number> = { page: page.value }
  if (search.value.trim()) params.search = search.value.trim()
  if (status.value !== 'all') params.status = status.value
  if (sort.value !== 'newest') params.sort = sort.value
  return params
}

const hasActiveFilters = computed(
  () => !!search.value.trim() || status.value !== 'all' || sort.value !== 'newest',
)

function applyFilters() {
  page.value = 1
  refresh()
}

function resetFilters() {
  search.value = ''
  status.value = 'all'
  sort.value = 'newest'
  page.value = 1
  refresh()
}

const { data, pending, error, refresh } = await useAsyncData(
  'admin-tournaments',
  () => api.admin.tournaments(queryParams()),
  { watch: [page] },
)

const tournaments = computed(() => data.value?.items ?? [])

const createForm = reactive({
  title: '',
  game: 'Call of Duty Mobile',
  league: 'intermediate',
  entry_fee: '',
  prize_pool: '',
  capacity: '',
  seat_mode: '2',
  start_date: '',
  description: '',
  game_login_info: '',
  prize_ranks: [
    { rank: 1, amount: '' as number | '' },
    { rank: 2, amount: '' as number | '' },
    { rank: 3, amount: '' as number | '' },
  ],
})

const creating = ref(false)
const actionLoading = ref<number | null>(null)
const winnerSelections = reactive<Record<number, number | ''>>({})
const statusSelections = reactive<Record<number, string>>({})

watch(tournaments, (list) => {
  for (const t of list) {
    if (!statusSelections[t.id]) statusSelections[t.id] = t.status
  }
}, { immediate: true })

async function createTournament() {
  if (!createForm.start_date) {
    flash.value = { error: 'تاریخ و ساعت شروع مسابقه را وارد کنید.' }
    return
  }
  creating.value = true
  try {
    const prizeRanks = createForm.prize_ranks
      .filter((row) => Number(row.amount) > 0)
      .map((row) => ({ rank: row.rank, amount: Number(row.amount) }))

    await api.admin.createTournament({
      ...createForm,
      entry_fee: Number(createForm.entry_fee),
      prize_pool: Number(createForm.prize_pool),
      capacity: Number(createForm.capacity),
      seat_mode: Number(createForm.seat_mode),
      prize_ranks: prizeRanks,
    })
    flash.value = { success: 'مسابقه ایجاد شد.' }
    Object.assign(createForm, {
      title: '', entry_fee: '', prize_pool: '', capacity: '', start_date: '', description: '', game_login_info: '',
      prize_ranks: [{ rank: 1, amount: '' }, { rank: 2, amount: '' }, { rank: 3, amount: '' }],
    })
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    creating.value = false
  }
}

async function updateStatus(t: Tournament) {
  actionLoading.value = t.id
  try {
    await api.admin.updateTournamentStatus(t.id, statusSelections[t.id] || t.status)
    flash.value = { success: 'وضعیت به‌روز شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    actionLoading.value = null
  }
}

async function recordResult(t: Tournament) {
  const winnerId = winnerSelections[t.id]
  if (!winnerId) return
  actionLoading.value = t.id
  try {
    await api.admin.recordTournamentResult(t.id, Number(winnerId))
    flash.value = { success: 'نتیجه ثبت شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    actionLoading.value = null
  }
}

async function payPrize(t: Tournament) {
  if (!confirm(`جایزه ${Number(t.prize_pool).toLocaleString('fa-IR')} تومان واریز شود؟`)) return
  actionLoading.value = t.id
  try {
    await api.admin.payTournamentPrize(t.id)
    flash.value = { success: 'جایزه واریز شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    actionLoading.value = null
  }
}

async function deleteTournament(t: Tournament) {
  if (!confirm('آیا از حذف این مسابقه مطمئن هستید؟')) return
  actionLoading.value = t.id
  try {
    await api.admin.deleteTournament(t.id)
    flash.value = { success: 'مسابقه حذف شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    actionLoading.value = null
  }
}

const participantsCache = reactive<Record<number, { user_id: number; username: string }[]>>({})

async function loadParticipants(tournamentId: number) {
  if (participantsCache[tournamentId]) return
  try {
    participantsCache[tournamentId] = await api.admin.tournamentParticipants(tournamentId)
  } catch {
    participantsCache[tournamentId] = []
  }
}

function prizePaid(t: Tournament): boolean {
  return !!(t.winner_id && (t as Tournament & { prize_paid?: boolean }).prize_paid)
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">مدیریت مسابقات</h1>
      <NuxtLink to="/admin" class="text-sm text-secondary">← داشبورد</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-red-600/40 rounded-xl p-6 text-red-300">
      {{ (error as Error).message || 'خطا در بارگذاری' }}
    </div>

    <template v-else>
      <AdminFilterBar
        v-model:search="search"
        search-placeholder="جستجو در عنوان، بازی یا توضیحات..."
        :show-reset="hasActiveFilters"
        @apply="applyFilters"
        @reset="resetFilters"
      >
        <template #filters>
          <AdminFilterField label="وضعیت">
            <template #control>
              <select v-model="status" @change="applyFilters">
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </template>
          </AdminFilterField>
          <AdminFilterField label="مرتب‌سازی">
            <template #control>
              <select v-model="sort" @change="applyFilters">
                <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </template>
          </AdminFilterField>
        </template>
      </AdminFilterBar>

      <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
        <h2 class="font-bold text-white mb-4">ایجاد مسابقه جدید</h2>
        <form class="grid sm:grid-cols-2 gap-3" @submit.prevent="createTournament">
          <input v-model="createForm.title" type="text" placeholder="عنوان مسابقه" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
          <input v-model="createForm.game" type="text" placeholder="نام بازی" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
          <select v-model="createForm.league" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
            <option value="beginner">مبتدی</option>
            <option value="intermediate">متوسط</option>
            <option value="professional">حرفه‌ای</option>
          </select>
          <input v-model="createForm.entry_fee" type="number" min="0" placeholder="مبلغ ورودی" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
          <input v-model="createForm.prize_pool" type="number" min="0" placeholder="بودجه جوایز (مجموع)" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
          <input v-model="createForm.capacity" type="number" min="1" placeholder="ظرفیت" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
          <select v-model="createForm.seat_mode" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
            <option value="1">چیدمان یک‌نفره</option>
            <option value="2">چیدمان دو‌نفره</option>
            <option value="4">چیدمان چهارنفره</option>
          </select>
          <div class="sm:col-span-2">
            <label class="block text-sm text-gray-400 mb-2">تاریخ و ساعت شروع (شمسی — وقت تهران)</label>
            <PersianDateTimeInput v-model="createForm.start_date" required />
          </div>
          <textarea v-model="createForm.description" placeholder="توضیحات" class="sm:col-span-2 bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" />
          <div class="sm:col-span-2">
            <AdminPrizeRanksEditor v-model="createForm.prize_ranks" :budget="Number(createForm.prize_pool || 0)" :seat-mode="Number(createForm.seat_mode || 1)" />
          </div>
          <textarea v-model="createForm.game_login_info" rows="2" placeholder="اطلاعات ورود به مسابقه (اختیاری)" class="sm:col-span-2 bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" />
          <button type="submit" class="sm:col-span-2 bg-success hover:bg-green-700 text-white rounded py-2 font-bold" :disabled="creating">
            {{ creating ? '...' : 'ایجاد مسابقه' }}
          </button>
        </form>
      </div>

      <div class="space-y-4">
        <div v-if="!tournaments.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
          مسابقه‌ای با این فیلترها یافت نشد.
        </div>
        <div v-for="t in tournaments" :key="t.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4">
          <div class="flex flex-wrap justify-between items-center gap-2 mb-2">
            <h3 class="font-bold text-white">{{ t.title }}</h3>
            <span class="text-xs px-2 py-1 rounded bg-dark-700 text-gray-300">{{ t.status_label || t.status }}</span>
          </div>
          <p class="text-xs text-gray-400 mb-3">
            ظرفیت: {{ t.registered_count }}/{{ t.capacity }} — جایزه: {{ Number(t.prize_pool).toLocaleString('fa-IR') }} تومان
            <span v-if="t.winner"> — 🏆 برنده: <span class="text-secondary font-bold">{{ (t.winner as { username?: string })?.username }}</span></span>
          </p>

          <div class="flex flex-wrap gap-2 items-center">
            <select v-model="statusSelections[t.id]" class="bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs text-white">
              <option value="upcoming">آینده</option>
              <option value="active">فعال</option>
              <option value="ongoing">در حال برگزاری</option>
              <option value="ended">پایان یافته</option>
              <option value="cancelled">لغو شده</option>
            </select>
            <button class="text-xs bg-success text-white px-2 py-1 rounded font-bold" :disabled="actionLoading === t.id" @click="updateStatus(t)">بروزرسانی وضعیت</button>

            <NuxtLink :to="`/admin/tournament-seats/${t.id}`" class="text-xs bg-secondary text-white px-2 py-1 rounded font-bold">🗺️ جایگاه‌ها</NuxtLink>
            <NuxtLink :to="`/admin/tournaments/${t.id}/result`" class="text-xs bg-purple-600 text-white px-2 py-1 rounded font-bold">🤖 AI</NuxtLink>

            <template v-if="t.status !== 'ended'">
              <select
                v-model="winnerSelections[t.id]"
                class="bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs w-40 text-white"
                @focus="loadParticipants(t.id)"
              >
                <option value="">انتخاب برنده...</option>
                <option v-for="p in participantsCache[t.id] || []" :key="p.user_id" :value="p.user_id">{{ p.username }}</option>
              </select>
              <button class="text-xs bg-success text-white px-2 py-1 rounded font-bold" :disabled="actionLoading === t.id || !winnerSelections[t.id]" @click="recordResult(t)">ثبت نتیجه</button>
            </template>

            <NuxtLink
              v-if="t.winner_id"
              :to="`/admin/tournaments/${t.id}/prizes`"
              class="text-xs bg-secondary text-white px-2 py-1 rounded font-bold inline-block"
            >
              💰 تأیید جوایز
            </NuxtLink>

            <NuxtLink :to="`/admin/tournaments/${t.id}/edit`" class="text-xs bg-secondary/20 text-secondary px-2 py-1 rounded font-bold">✏️ ویرایش</NuxtLink>
            <button class="text-xs bg-danger text-white px-2 py-1 rounded font-bold" :disabled="actionLoading === t.id" @click="deleteTournament(t)">🗑️ حذف</button>
          </div>
        </div>
      </div>
      <AdminPagination v-model:page="page" :meta="data?.meta" />
    </template>
  </div>
</template>
