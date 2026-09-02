<script setup lang="ts">
import type { Tournament } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const flash = useState<{ success?: string; error?: string } | null>('flash')

const id = computed(() => String(route.params.id))

const { data: tournament, pending, error } = usePageData(
  () => `admin-tournament-edit-${id.value}`,
  () => api.admin.tournament(id.value),
)

useHead({ title: computed(() => tournament.value ? `ویرایش — ${tournament.value.title}` : 'ویرایش مسابقه') })

const form = reactive({
  title: '',
  game: 'Call of Duty Mobile',
  league: 'intermediate',
  description: '',
  entry_fee: 0,
  prize_pool: 0,
  capacity: 0,
  seat_mode: 2,
  start_date: '',
  end_date: '',
  status: 'upcoming',
  winner_id: null as number | null,
  game_login_info: '',
  prize_ranks: [
    { rank: 1, amount: '' as number | '' },
    { rank: 2, amount: '' as number | '' },
    { rank: 3, amount: '' as number | '' },
  ],
})

watch(tournament, (t) => {
  if (!t) return
  Object.assign(form, {
    title: t.title,
    game: t.game || 'Call of Duty Mobile',
    league: t.league || 'intermediate',
    description: t.description || '',
    entry_fee: t.entry_fee,
    prize_pool: t.prize_pool,
    capacity: t.capacity,
    seat_mode: t.seat_mode || 2,
    start_date: t.start_date || '',
    end_date: t.end_date ? t.end_date.slice(0, 16) : '',
    status: t.status,
    winner_id: t.winner_id ?? null,
    game_login_info: t.game_login_info || '',
    prize_ranks: t.prize_ranks?.length
      ? t.prize_ranks.map((row) => ({ rank: row.rank, amount: row.amount }))
      : [{ rank: 1, amount: '' }, { rank: 2, amount: '' }, { rank: 3, amount: '' }],
  })
}, { immediate: true })

const saving = ref(false)

async function submit() {
  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      ...form,
      prize_ranks: form.prize_ranks
        .filter((row) => Number(row.amount) > 0)
        .map((row) => ({ rank: row.rank, amount: Number(row.amount) })),
    }
    if (!payload.end_date) delete payload.end_date
    await api.admin.updateTournament(id.value, payload)
    flash.value = { success: 'مسابقه به‌روزرسانی شد.' }
    router.push('/admin/tournaments')
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-3xl">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">ویرایش مسابقه</h1>
      <NuxtLink to="/admin/tournaments" class="text-sm text-secondary">← بازگشت</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="text-red-400">{{ (error as Error).message }}</div>

    <form v-else class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-3" @submit.prevent="submit">
      <input v-model="form.title" required class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" placeholder="عنوان">
      <input v-model="form.game" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" placeholder="بازی">
      <select v-model="form.league" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
        <option value="beginner">مبتدی</option>
        <option value="intermediate">متوسط</option>
        <option value="professional">حرفه‌ای</option>
      </select>
      <div class="grid sm:grid-cols-2 gap-3">
        <input v-model.number="form.entry_fee" type="number" min="0" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" placeholder="ورودی">
        <input v-model.number="form.prize_pool" type="number" min="0" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" placeholder="بودجه جوایز">
        <input v-model.number="form.capacity" type="number" min="1" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" placeholder="ظرفیت">
        <select v-model.number="form.seat_mode" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
          <option :value="1">۱ نفره</option>
          <option :value="2">۲ نفره</option>
          <option :value="4">۴ نفره</option>
        </select>
      </div>
      <div>
        <label class="block text-sm text-gray-400 mb-2">تاریخ و ساعت شروع (شمسی — وقت تهران)</label>
        <PersianDateTimeInput v-model="form.start_date" required />
      </div>
      <select v-model="form.status" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
        <option value="upcoming">آینده</option>
        <option value="active">فعال</option>
        <option value="ongoing">در حال برگزاری</option>
        <option value="ended">پایان یافته</option>
        <option value="cancelled">لغو شده</option>
      </select>
      <textarea v-model="form.description" rows="3" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" placeholder="توضیحات" />
      <AdminPrizeRanksEditor v-model="form.prize_ranks" :budget="Number(form.prize_pool || 0)" :seat-mode="Number(form.seat_mode || 1)" />
      <textarea v-model="form.game_login_info" rows="3" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white" placeholder="اطلاعات ورود" />
      <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold" :disabled="saving">{{ saving ? '...' : 'ذخیره' }}</button>
    </form>
  </div>
</template>
