<script setup lang="ts">
import type { OccupiedSeatInfo } from '~/types/api'

definePageMeta({ middleware: 'auth', layout: false })

const route = useRoute()
const api = useApi()
const flash = useState('flash')

const id = computed(() => route.params.id as string)
const loading = ref(false)
const cancelling = ref(false)
const errors = ref<string[]>([])
const showModal = ref(false)
const pendingSeat = ref<number | null>(null)
const pendingLabel = ref('')

const { data, pending, error, refresh } = await useAsyncData(
  () => `select-seat-${id.value}`,
  () => api.tournaments.selectSeat(id.value),
)

useHead(() => ({ title: `انتخاب جایگاه | ${data.value?.tournament?.title || 'مسابقه'}` }))

const alreadySelected = computed(() => !!data.value?.seat_label && !data.value?.teams_grid)
const teamsGrid = computed(() => data.value?.teams_grid || [])
const seatMode = computed(() => data.value?.tournament?.seat_mode || 1)

const occupiedMap = computed(() => {
  const map = new Map<number, OccupiedSeatInfo>()
  const raw = data.value?.occupied_seats || {}
  for (const [key, value] of Object.entries(raw)) {
    const seatNumber = Number((value as OccupiedSeatInfo)?.seat_number ?? key)
    if (Number.isFinite(seatNumber)) {
      map.set(seatNumber, value as OccupiedSeatInfo)
    }
  }
  return map
})

function getOccupied(seatNumber: number) {
  return occupiedMap.value.get(seatNumber)
}

function openConfirm(seatNumber: number, label: string) {
  pendingSeat.value = seatNumber
  pendingLabel.value = label
  showModal.value = true
}

function closeConfirm() {
  showModal.value = false
  pendingSeat.value = null
  pendingLabel.value = ''
}

async function confirmSeat() {
  if (!pendingSeat.value) return
  loading.value = true
  errors.value = []
  try {
    await api.tournaments.storeSeat(id.value, pendingSeat.value)
    flash.value = { success: 'جایگاه با موفقیت ثبت شد.' }
    await navigateTo(`/tournaments/${id.value}`)
  } catch (e: unknown) {
    const err = e as Error
    errors.value = [err.message || 'ثبت جایگاه ناموفق بود.']
    closeConfirm()
    await refresh()
  } finally {
    loading.value = false
  }
}

async function cancelRegistration() {
  if (!confirm('از ثبت‌نام انصراف می‌دهید؟')) return
  cancelling.value = true
  errors.value = []
  try {
    await api.tournaments.cancelPending(id.value)
    flash.value = { success: 'از ثبت‌نام انصراف دادید.' }
    await navigateTo('/')
  } catch (e: unknown) {
    const err = e as Error
    errors.value = [err.message || 'انصراف ناموفق بود.']
  } finally {
    cancelling.value = false
  }
}

function avatarLetter(username?: string | null) {
  return (username?.charAt(0) || '?').toUpperCase()
}
</script>

<template>
  <div class="seat-page fixed inset-0 z-[9999] flex flex-col">
    <div class="border-b border-amber-900/40 bg-black/70 px-4 py-4 shrink-0">
      <h1 class="text-xl md:text-2xl font-bold text-[#d4af37] text-center">انتخاب جایگاه</h1>
      <p v-if="data?.tournament" class="text-center text-sm text-gray-400 mt-1">
        {{ data.tournament.title }} — {{ data.tournament.seat_mode_label || 'انفرادی' }}
      </p>
      <p class="text-center text-xs text-amber-400/90 mt-2">
        روی جایگاه خالی (مثلاً 2.1 یا 20.2) کلیک کنید و تأیید نمایید.
      </p>
      <p class="text-center mt-2 flex flex-wrap items-center justify-center gap-3">
        <NuxtLink to="/" class="text-xs text-gray-500 hover:text-gray-300 underline">بازگشت به صفحه اصلی</NuxtLink>
        <button
          type="button"
          class="text-xs text-red-400 hover:text-red-300 underline disabled:opacity-50"
          :disabled="cancelling || alreadySelected"
          @click="cancelRegistration"
        >
          {{ cancelling ? '...' : 'انصراف از ثبت‌نام' }}
        </button>
      </p>
    </div>

    <div class="flex-1 overflow-y-auto p-3 md:p-6">
      <div v-if="pending" class="text-center py-10 text-gray-500">در حال بارگذاری...</div>

      <div v-else-if="error || !data" class="max-w-6xl mx-auto bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
        امکان انتخاب جایگاه وجود ندارد.
      </div>

      <div v-else-if="alreadySelected" class="max-w-lg mx-auto bg-dark-800 border border-[#d4af37]/40 rounded-2xl p-8 text-center">
        <p class="text-gray-300 mb-2">جایگاه شما قبلاً ثبت شده است:</p>
        <p class="text-4xl font-black text-[#d4af37] font-mono" dir="ltr">{{ data.seat_label }}</p>
        <NuxtLink :to="`/tournaments/${id}`" class="inline-block mt-6 text-secondary hover:underline">بازگشت به مسابقه</NuxtLink>
      </div>

      <template v-else>
        <div v-if="errors.length" class="max-w-6xl mx-auto mb-4 rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-red-300 text-sm">
          <ul class="list-disc list-inside space-y-1">
            <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
          </ul>
        </div>

        <div class="team-grid max-w-6xl mx-auto">
          <div v-for="teamRow in teamsGrid" :key="teamRow.team" class="team-card">
            <div class="team-card__title">تیم {{ teamRow.team }}</div>
            <div
              class="team-card__slots"
              :style="{ gridTemplateColumns: `repeat(${seatMode}, minmax(0, 1fr))` }"
            >
              <template v-for="slot in teamRow.slots" :key="slot.seat_number">
                <div
                  v-if="getOccupied(slot.seat_number)"
                  class="seat-slot seat-slot--taken"
                >
                  <div class="seat-slot__top">نفر {{ slot.slot }}</div>
                  <div class="seat-slot__avatar">
                    {{ avatarLetter(getOccupied(slot.seat_number)?.user?.username) }}
                  </div>
                  <span class="seat-slot__code">{{ slot.label }}</span>
                  <div v-if="getOccupied(slot.seat_number)?.user?.cod_id" class="seat-slot__cod">
                    {{ getOccupied(slot.seat_number)?.user?.cod_id }}
                  </div>
                  <div class="seat-slot__user">{{ getOccupied(slot.seat_number)?.user?.username || '—' }}</div>
                  <div class="seat-slot__status">پر شده</div>
                </div>
                <button
                  v-else
                  type="button"
                  class="seat-slot"
                  :class="{ 'is-selected': pendingSeat === slot.seat_number && showModal }"
                  @click="openConfirm(slot.seat_number, slot.label)"
                >
                  <div class="seat-slot__top">نفر {{ slot.slot }}</div>
                  <svg class="seat-slot__icon" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                    <ellipse cx="32" cy="54" rx="18" ry="4" fill="rgba(212,175,55,0.2)" />
                    <circle cx="32" cy="22" r="11" fill="#64748b" />
                    <path d="M14 52c2-12 10-18 18-18s16 6 18 18" fill="#475569" />
                  </svg>
                  <span class="seat-slot__code">{{ slot.label }}</span>
                  <div class="seat-slot__status">خالی — کلیک</div>
                </button>
              </template>
            </div>
          </div>
        </div>
      </template>
    </div>

    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-[10000] flex items-center justify-center p-4"
        style="background: rgba(0,0,0,0.88);"
        @click.self="closeConfirm"
      >
        <div class="bg-dark-800 border border-[#d4af37]/50 rounded-2xl p-6 max-w-sm w-full shadow-2xl text-center">
          <h2 class="text-xl font-bold text-white mb-2">تأیید جایگاه</h2>
          <p class="text-gray-300 mb-1">آیا این جایگاه را انتخاب می‌کنید؟</p>
          <p class="text-4xl font-black text-[#d4af37] my-4 font-mono" dir="ltr">{{ pendingLabel || '—' }}</p>
          <p class="text-xs text-amber-400/90 mb-5">پس از تأیید، هزینه ثبت‌نام از کیف پول کسر و ثبت‌نام نهایی می‌شود.</p>
          <div class="flex gap-3">
            <button
              type="button"
              class="flex-1 bg-gray-600 hover:bg-gray-500 text-white rounded-lg py-3 font-bold"
              :disabled="loading"
              @click="closeConfirm"
            >
              انصراف
            </button>
            <button
              type="button"
              class="flex-1 bg-success hover:opacity-90 text-white rounded-lg py-3 font-bold"
              :disabled="loading"
              @click="confirmSeat"
            >
              {{ loading ? 'در حال ثبت...' : 'تأیید جایگاه' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.seat-page {
  background: radial-gradient(ellipse at top, #1a1508 0%, #050508 45%, #050508 100%);
}
.team-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  direction: ltr;
}
@media (min-width: 640px) {
  .team-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
}
@media (min-width: 1024px) {
  .team-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
}
.team-card {
  position: relative;
  background: linear-gradient(180deg, rgba(24, 20, 12, 0.95) 0%, rgba(10, 9, 8, 0.98) 100%);
  border: 1px solid rgba(212, 175, 55, 0.55);
  box-shadow: inset 0 0 0 1px rgba(212, 175, 55, 0.12), 0 8px 24px rgba(0, 0, 0, 0.45);
  padding: 0.45rem 0.5rem 0.55rem;
}
.team-card::before,
.team-card::after {
  content: '';
  position: absolute;
  width: 14px;
  height: 14px;
  border-color: rgba(212, 175, 55, 0.85);
  border-style: solid;
  pointer-events: none;
}
.team-card::before {
  top: -1px; left: -1px;
  border-width: 2px 0 0 2px;
}
.team-card::after {
  bottom: -1px; right: -1px;
  border-width: 0 2px 2px 0;
}
.team-card__title {
  text-align: center;
  color: #d4af37;
  font-weight: 800;
  font-size: 0.95rem;
  margin-bottom: 0.45rem;
  letter-spacing: 0.02em;
}
.team-card__slots {
  display: grid;
  gap: 0.35rem;
  direction: ltr;
}
.seat-slot {
  position: relative;
  min-height: 88px;
  border: 1px solid rgba(212, 175, 55, 0.35);
  background: rgba(0, 0, 0, 0.35);
  padding: 0.35rem 0.25rem 0.5rem;
  text-align: center;
  transition: border-color 0.2s, background 0.2s, transform 0.15s;
}
button.seat-slot {
  cursor: pointer;
}
button.seat-slot:hover {
  border-color: rgba(212, 175, 55, 0.85);
  background: rgba(212, 175, 55, 0.08);
  transform: translateY(-1px);
}
button.seat-slot.is-selected {
  border-color: #d4af37;
  background: rgba(212, 175, 55, 0.16);
  box-shadow: 0 0 0 1px rgba(212, 175, 55, 0.35);
}
.seat-slot--taken {
  opacity: 0.72;
  cursor: default;
}
.seat-slot__top {
  color: #d4af37;
  font-size: 0.65rem;
  font-weight: 700;
  margin-bottom: 0.15rem;
}
.seat-slot__avatar {
  width: 36px;
  height: 36px;
  margin: 0 auto 0.2rem;
  border-radius: 9999px;
  background: linear-gradient(135deg, #8B5CF6, #d4af37);
  color: #fff;
  font-weight: 800;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
.seat-slot__icon {
  width: 36px;
  height: 36px;
  margin: 0 auto 0.2rem;
  display: block;
}
.seat-slot__code {
  display: block;
  color: #f5f5f5;
  font-weight: 800;
  font-size: 0.95rem;
  letter-spacing: 0.04em;
  direction: ltr;
  font-family: ui-monospace, monospace;
}
.seat-slot__cod {
  font-size: 0.68rem;
  color: #d4af37;
  font-weight: 700;
  margin-top: 0.1rem;
  line-height: 1.25;
  word-break: break-word;
}
.seat-slot__user {
  font-size: 0.58rem;
  color: #9ca3af;
  margin-top: 0.1rem;
  line-height: 1.25;
  word-break: break-word;
}
.seat-slot__status {
  font-size: 0.58rem;
  color: #6b7280;
  margin-top: 0.1rem;
}
</style>
