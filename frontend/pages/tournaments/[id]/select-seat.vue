<script setup lang="ts">
import type { OccupiedSeatInfo } from '~/types/api'

definePageMeta({ middleware: 'auth' })

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

useHead(() => ({ title: `جایگاه‌ها | ${data.value?.tournament?.title || 'مسابقه'}` }))

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
</script>

<template>
  <div class="seat-page">
    <div v-if="pending" class="text-center py-10 text-gray-500">در حال بارگذاری...</div>

    <div v-else-if="error || !data" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      امکان انتخاب جایگاه وجود ندارد.
    </div>

    <div v-else-if="alreadySelected" class="max-w-lg mx-auto bg-dark-800 border border-[#d4af37]/40 rounded-2xl p-8 text-center">
      <p class="text-gray-300 mb-2">جایگاه شما قبلاً ثبت شده است:</p>
      <p class="text-4xl font-black text-[#d4af37] font-mono" dir="ltr">{{ data.seat_label }}</p>
      <NuxtLink :to="`/tournaments/${id}`" class="inline-block mt-6 text-secondary hover:underline">بازگشت به مسابقه</NuxtLink>
    </div>

    <template v-else>
      <div class="seat-page__toolbar">
        <p v-if="data.tournament" class="seat-page__subtitle">
          {{ data.tournament.title }}
          <span v-if="data.tournament.seat_mode_label"> — {{ data.tournament.seat_mode_label }}</span>
        </p>
        <button
          type="button"
          class="seat-page__cancel"
          :disabled="cancelling"
          @click="cancelRegistration"
        >
          {{ cancelling ? '...' : 'انصراف از ثبت‌نام' }}
        </button>
      </div>

      <div v-if="errors.length" class="mb-4 rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-red-300 text-sm">
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
        </ul>
      </div>

      <div class="team-grid">
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
                <div class="seat-slot__label">{{ slot.label }}</div>
                <div class="seat-slot__name">
                  {{ getOccupied(slot.seat_number)?.user?.username || '—' }}
                </div>
                <div v-if="getOccupied(slot.seat_number)?.user?.cod_id" class="seat-slot__cod">
                  {{ getOccupied(slot.seat_number)?.user?.cod_id }}
                </div>
              </div>
              <button
                v-else
                type="button"
                class="seat-slot seat-slot--empty"
                :class="{ 'is-selected': pendingSeat === slot.seat_number && showModal }"
                @click="openConfirm(slot.seat_number, slot.label)"
              >
                <div class="seat-slot__label">{{ slot.label }}</div>
                <div class="seat-slot__empty">خالی</div>
              </button>
            </template>
          </div>
        </div>
      </div>
    </template>

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
  direction: rtl;
}

.seat-page__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.seat-page__subtitle {
  margin: 0;
  font-size: 0.85rem;
  color: #9ca3af;
}

.seat-page__cancel {
  background: transparent;
  border: none;
  color: #ef4444;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  padding: 0;
}

.seat-page__cancel:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.team-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
  direction: ltr;
}

@media (min-width: 768px) {
  .team-grid {
    gap: 0.85rem;
  }
}

@media (min-width: 1024px) {
  .team-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (min-width: 1280px) {
  .team-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.team-card {
  border: 1px solid #d4af37;
  background: #000;
  padding: 0.5rem 0.55rem 0.6rem;
}

.team-card__title {
  text-align: center;
  color: #d4af37;
  font-weight: 800;
  font-size: 0.95rem;
  margin-bottom: 0.45rem;
}

.team-card__slots {
  display: grid;
  gap: 0.35rem;
  direction: ltr;
}

.seat-slot {
  min-height: 92px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  background: rgba(15, 15, 18, 0.95);
  padding: 0.45rem 0.35rem 0.55rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
}

button.seat-slot {
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

button.seat-slot:hover {
  border-color: rgba(212, 175, 55, 0.65);
  background: rgba(212, 175, 55, 0.06);
}

button.seat-slot.is-selected {
  border-color: #d4af37;
  background: rgba(212, 175, 55, 0.12);
}

.seat-slot--taken {
  opacity: 0.85;
}

.seat-slot__label {
  color: #d4af37;
  font-size: 0.72rem;
  font-weight: 700;
  margin-bottom: 0.35rem;
  direction: ltr;
  font-family: ui-monospace, monospace;
}

.seat-slot__name {
  color: #f5f5f5;
  font-size: 0.95rem;
  font-weight: 800;
  line-height: 1.35;
  word-break: break-word;
  margin-top: auto;
  margin-bottom: auto;
}

.seat-slot__cod {
  font-size: 0.68rem;
  color: #d4af37;
  font-weight: 600;
  line-height: 1.25;
  word-break: break-word;
  margin-top: 0.15rem;
}

.seat-slot__empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  font-size: 0.9rem;
  font-weight: 600;
}
</style>
