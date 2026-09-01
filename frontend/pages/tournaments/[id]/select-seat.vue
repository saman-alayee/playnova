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
const pendingTeam = ref<number | null>(null)
const pendingLabel = ref('')
const teammateCodIds = ref<string[]>([])

const { data, pending, error, refresh } = await useAsyncData(
  () => `select-seat-${id.value}`,
  () => api.tournaments.selectSeat(id.value),
)

useHead(() => ({ title: `جایگاه‌ها | ${data.value?.tournament?.title || 'مسابقه'}` }))

const alreadySelected = computed(() => !!data.value?.seat_label && !data.value?.teams_grid)
const teamsGrid = computed(() => data.value?.teams_grid || [])
const seatMode = computed(() => data.value?.tournament?.seat_mode || 1)
const occupiedSeats = computed(() => data.value?.occupied_seats || {})
const reservationType = computed(() => data.value?.registration?.reservation_type || 'solo')
const isTeamReservation = computed(() => reservationType.value === 'team' && seatMode.value > 1)
const requiredInvites = computed(() => Math.max(0, seatMode.value - 1))

const teamFormValid = computed(() =>
  !isTeamReservation.value
  || (
    teammateCodIds.value.length === requiredInvites.value
    && teammateCodIds.value.every((codId) => codId.trim() !== '')
    && new Set(teammateCodIds.value.map((codId) => codId.trim())).size === teammateCodIds.value.length
  ),
)

function openConfirm(seatNumber: number, label: string) {
  errors.value = []

  if (isTeamReservation.value) {
    const teamRow = teamsGrid.value.find((team) =>
      team.slots.some((slot) => slot.seat_number === seatNumber),
    )

    if (!teamRow) return

    const teamTaken = teamRow.slots.some((slot) => occupiedSeats.value[slot.seat_number])
    if (teamTaken) {
      errors.value = ['این تیم دیگر خالی نیست.']
      return
    }

    pendingSeat.value = teamRow.slots[0]?.seat_number ?? seatNumber
    pendingTeam.value = teamRow.team
    pendingLabel.value = label
    teammateCodIds.value = Array.from({ length: requiredInvites.value }, () => '')
  } else {
    pendingSeat.value = seatNumber
    pendingTeam.value = null
    pendingLabel.value = label
  }

  showModal.value = true
}

function closeConfirm() {
  showModal.value = false
  pendingSeat.value = null
  pendingTeam.value = null
  pendingLabel.value = ''
  teammateCodIds.value = []
}

async function confirmSeat() {
  if (!pendingSeat.value) return
  loading.value = true
  errors.value = []

  try {
    if (isTeamReservation.value) {
      const ids = teammateCodIds.value.map((codId) => codId.trim())
      await api.tournaments.teamInvite(id.value, {
        seatNumber: pendingSeat.value,
        ...(ids.length === 1 ? { teammateCodId: ids[0] } : { teammateCodIds: ids }),
      })
      flash.value = { success: 'درخواست رزرو تیمی ارسال شد. تا تأیید هم‌تیمی‌ها، مبلغی کسر نمی‌شود.' }
      await auth.fetchUser()
      await navigateTo(`/tournaments/${id.value}`)
    } else {
      await api.tournaments.storeSeat(id.value, pendingSeat.value)
      flash.value = { success: 'جایگاه با موفقیت ثبت شد.' }
      await auth.fetchUser()
      await navigateTo(`/tournaments/${id.value}`)
    }
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
          <p v-if="isTeamReservation" class="text-xs text-amber-300/90 mt-1">
            رزرو تیمی — یک تیم خالی انتخاب کنید. تا تأیید نهایی هم‌تیمی‌ها، مبلغی کسر نمی‌شود و جایگاه اشغال نمی‌شود.
          </p>
          <p v-else class="text-xs text-gray-500 mt-1">
            تا قبل از تأیید نهایی، مبلغی از کیف پول کسر نمی‌شود.
          </p>
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
          :selected-team="pendingTeam"
          :team-select-mode="isTeamReservation"
          interactive
          @select="openConfirm"
        />
      </div>
    </template>

    <Teleport to="body">
      <div v-if="showModal" class="seat-modal" @click.self="closeConfirm">
        <div class="seat-modal__panel" :class="{ 'seat-modal__panel--wide': isTeamReservation }">
          <h2 class="seat-modal__title">تأیید {{ isTeamReservation ? 'تیم' : 'جایگاه' }}</h2>
          <p class="seat-modal__text">
            {{ isTeamReservation ? 'تیم انتخاب‌شده:' : 'آیا این جایگاه را انتخاب می‌کنید؟' }}
          </p>
          <p class="seat-modal__value" dir="ltr">{{ pendingLabel || '—' }}</p>

          <div v-if="isTeamReservation" class="seat-modal__team-form">
            <p class="seat-modal__hint">
              {{ requiredInvites === 1
                ? 'آیدی کالاف ۱ هم‌تیمی را وارد کنید:'
                : `آیدی کالاف ${requiredInvites} هم‌تیمی را وارد کنید:` }}
            </p>
            <input
              v-for="(_, index) in teammateCodIds"
              :key="index"
              v-model="teammateCodIds[index]"
              type="text"
              :placeholder="`آیدی کالاف هم‌تیمی ${index + 1}`"
              class="seat-modal__input"
              dir="ltr"
            >
            <p class="seat-modal__note">
              پس از ارسال، هم‌تیمی‌ها ۱۵ ثانیه فرصت تأیید دارند. تا آن زمان مبلغی کسر نمی‌شود.
            </p>
          </div>

          <div class="seat-modal__buttons">
            <button type="button" class="seat-modal__btn seat-modal__btn--ghost" :disabled="loading" @click="closeConfirm">
              انصراف
            </button>
            <button
              type="button"
              class="seat-modal__btn seat-modal__btn--confirm"
              :disabled="loading || !teamFormValid"
              @click="confirmSeat"
            >
              {{ loading ? 'در حال ثبت...' : (isTeamReservation ? 'ارسال درخواست تیمی' : 'تأیید و پرداخت') }}
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

.seat-modal__panel--wide {
  max-width: 26rem;
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
  font-size: 1.35rem;
  font-weight: 900;
  color: #d4af37;
  font-family: ui-monospace, monospace;
  line-height: 1.35;
}

.seat-modal__team-form {
  text-align: right;
  margin-bottom: 1rem;
}

.seat-modal__hint {
  margin: 0 0 0.5rem;
  color: #9ca3af;
  font-size: 0.8rem;
}

.seat-modal__input {
  width: 100%;
  margin-bottom: 0.45rem;
  border: 1px solid #4b5563;
  border-radius: 0.45rem;
  background: #111827;
  color: #fff;
  padding: 0.55rem 0.65rem;
  font-size: 0.85rem;
}

.seat-modal__note {
  margin: 0.35rem 0 0;
  color: #fbbf24;
  font-size: 0.72rem;
  line-height: 1.5;
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
