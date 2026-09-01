<script setup lang="ts">
import type { OccupiedSeatInfo } from '~/types/api'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const api = useApi()
const auth = useAuthStore()
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
const occupiedSeats = computed(() => data.value?.occupied_seats || {})

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
    await auth.fetchUser()
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
    <div v-if="pending" class="text-gray-500 py-10 text-center">در حال بارگذاری...</div>

    <div v-else-if="error || !data" class="text-gray-500 py-10 text-center">
      امکان انتخاب جایگاه وجود ندارد.
    </div>

    <div v-else-if="alreadySelected" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center">
      <p class="text-gray-400">جایگاه شما قبلاً ثبت شده است:</p>
      <p class="seat-page__selected-value" dir="ltr">{{ data.seat_label }}</p>
      <NuxtLink :to="`/tournaments/${id}`" class="text-secondary">بازگشت به مسابقه</NuxtLink>
    </div>

    <template v-else>
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
          <h1 class="text-2xl font-bold text-white">جایگاه‌ها</h1>
          <p v-if="data.tournament?.title" class="text-sm text-gray-400 mt-1">{{ data.tournament.title }}</p>
        </div>
        <div class="flex items-center gap-3">
          <NuxtLink :to="`/tournaments/${id}`" class="text-sm text-secondary">← بازگشت</NuxtLink>
          <button
            type="button"
            class="text-sm text-red-400"
            :disabled="cancelling"
            @click="cancelRegistration"
          >
            {{ cancelling ? '...' : 'انصراف از ثبت‌نام' }}
          </button>
        </div>
      </div>

      <div v-if="errors.length" class="seat-page__errors">
        <p v-for="(err, i) in errors" :key="i">{{ err }}</p>
      </div>

      <div class="seat-page__body">
        <TournamentSeatGrid
          :teams="teamsGrid"
          :occupied-seats="occupiedSeats as Record<number, OccupiedSeatInfo>"
          :seat-mode="seatMode"
          :selected-seat="pendingSeat"
          interactive
          @select="openConfirm"
        />
      </div>
    </template>

    <Teleport to="body">
      <div v-if="showModal" class="seat-modal" @click.self="closeConfirm">
        <div class="seat-modal__panel">
          <h2 class="seat-modal__title">تأیید جایگاه</h2>
          <p class="seat-modal__text">آیا این جایگاه را انتخاب می‌کنید؟</p>
          <p class="seat-modal__value" dir="ltr">{{ pendingLabel || '—' }}</p>
          <div class="seat-modal__buttons">
            <button type="button" class="seat-modal__btn seat-modal__btn--ghost" :disabled="loading" @click="closeConfirm">
              انصراف
            </button>
            <button type="button" class="seat-modal__btn seat-modal__btn--confirm" :disabled="loading" @click="confirmSeat">
              {{ loading ? 'در حال ثبت...' : 'تأیید' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.seat-page {
  min-width: 0;
  max-width: 100%;
}

.seat-page__selected-value {
  margin: 0.75rem 0;
  font-size: 2rem;
  font-weight: 900;
  color: #d4af37;
  font-family: ui-monospace, monospace;
}

.seat-page__errors {
  margin-bottom: 0.75rem;
  padding: 0.55rem 0.7rem;
  border: 1px solid #b91c1c;
  border-radius: 0.75rem;
  background: rgba(127, 29, 29, 0.35);
  color: #fca5a5;
  font-size: 0.8rem;
}

.seat-page__body {
  min-width: 0;
  max-width: 100%;
  overflow-x: auto;
  border: 1px solid rgba(197, 160, 89, 0.28);
  border-radius: 0.75rem;
  background: #050505;
  padding: 0.65rem;
}

.seat-modal {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(0, 0, 0, 0.9);
}

.seat-modal__panel {
  width: 100%;
  max-width: 22rem;
  padding: 1.25rem;
  text-align: center;
  border: 1px solid #c5a059;
  background: #000;
}

.seat-modal__title {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
  font-weight: 800;
  color: #d4af37;
}

.seat-modal__text {
  margin: 0;
  color: #d1d5db;
  font-size: 0.85rem;
}

.seat-modal__value {
  margin: 0.85rem 0 1rem;
  font-size: 2rem;
  font-weight: 900;
  color: #d4af37;
  font-family: ui-monospace, monospace;
}

.seat-modal__buttons {
  display: flex;
  gap: 0.5rem;
}

.seat-modal__btn {
  flex: 1;
  border: none;
  border-radius: 0.35rem;
  padding: 0.65rem;
  font-weight: 800;
  cursor: pointer;
}

.seat-modal__btn--ghost {
  background: #4b5563;
  color: #fff;
}

.seat-modal__btn--confirm {
  background: #16a34a;
  color: #fff;
}

.seat-modal__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
