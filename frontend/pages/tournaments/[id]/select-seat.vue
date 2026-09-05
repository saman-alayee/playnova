<script setup lang="ts">
import type { OccupiedSeatInfo, SeatSelectionData } from '~/types/api'

definePageMeta({ middleware: 'auth', ssr: false })

const route = useRoute()
const api = useApi()
const auth = useAuthStore()
const flash = useState('flash')
const { clearRegisterBodyLock } = useModals()
const loadingIndicator = useLoadingIndicator()

const id = computed(() => route.params.id as string)
const loading = ref(false)
const cancelling = ref(false)
const errors = ref<string[]>([])
const showModal = ref(false)
const pendingSeat = ref<number | null>(null)
const pendingTeam = ref<number | null>(null)
const pendingLabel = ref('')
const teammateCodIds = ref<string[]>([])

const data = ref<SeatSelectionData | null>(null)
const pending = ref(true)
const loadErrorMessage = ref<string | null>(null)

async function refresh() {
  pending.value = true
  loadErrorMessage.value = null
  loadingIndicator.start()
  try {
    if (!auth.initialized) {
      await auth.init()
    }
    data.value = await api.tournaments.selectSeat(id.value)
  } catch (e: unknown) {
    data.value = null
    const err = e as { message?: string; data?: { message?: string } }
    loadErrorMessage.value = err.data?.message || err.message || 'امکان انتخاب جایگاه وجود ندارد.'
  } finally {
    pending.value = false
    loadingIndicator.finish()
  }
}

watch(() => route.params.id, () => {
  void refresh()
})

onMounted(() => {
  clearRegisterBodyLock()
  void refresh()
})

useHead(() => ({ title: `انتخاب جایگاه | ${data.value?.tournament?.title || 'مسابقه'}` }))

const loadError = computed(() => {
  if (pending.value) return null
  return loadErrorMessage.value
})

const viewOnly = computed(() => !!data.value?.registration?.seat_number)
const teamsGrid = computed(() => data.value?.teams_grid || [])
const seatMode = computed(() => data.value?.tournament?.seat_mode || 1)
const occupiedSeats = computed(() => data.value?.occupied_seats || {})
const mySeatNumber = computed(() => data.value?.registration?.seat_number ?? null)
const myTeamOverride = computed(() => data.value?.my_team ?? null)
const myUserId = computed(() => auth.user?.id ?? null)

const { myTeamLabel, teammates, me } = useSeatTeamInfo(
  teamsGrid,
  occupiedSeats,
  mySeatNumber,
  myUserId,
  myTeamOverride,
)

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
      await navigateTo('/')
    } else {
      await api.tournaments.storeSeat(id.value, pendingSeat.value)
      flash.value = { success: 'جایگاه با موفقیت ثبت شد.' }
      await auth.fetchUser()
      await navigateTo('/')
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
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <NuxtLink :to="viewOnly ? `/tournaments/${id}` : '/'" class="text-sm text-secondary">← بازگشت</NuxtLink>
      <button
        v-if="data && !viewOnly && !loadError"
        type="button"
        class="text-sm text-red-400 hover:text-red-300 disabled:opacity-50"
        :disabled="cancelling"
        @click="cancelRegistration"
      >
        {{ cancelling ? '...' : 'انصراف از ثبت‌نام' }}
      </button>
    </div>

    <h1 class="text-2xl font-bold text-center text-primary mb-2">
      {{ viewOnly ? 'نقشه جایگاه‌ها' : 'انتخاب جایگاه' }}
    </h1>
    <p v-if="data?.tournament" class="text-center text-sm text-gray-400">
      {{ data.tournament.title }} — {{ data.tournament.seat_mode_label || 'انفرادی' }}
    </p>
    <p v-if="viewOnly" class="text-center text-xs text-gray-400 mt-2 mb-4">
      جایگاه شما و بقیه بازیکن‌ها روی نقشه مشخص است. هم‌تیمی‌ها با رنگ جدا دیده می‌شوند.
    </p>
    <p v-else class="text-center text-xs text-amber-400/90 mt-2 mb-4">
      روی جایگاه خالی (مثلاً 2.1 یا 20.2) کلیک کنید و تأیید نمایید.
    </p>
    <p v-if="data && !viewOnly && !loadError" class="seat-page__banner">
      برای تکمیل ثبت‌نام، جایگاه خود را انتخاب و تأیید کنید.
    </p>

    <PageLoading v-if="pending" />

    <div v-else-if="loadError" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center space-y-3">
      <p class="text-red-300">{{ loadError }}</p>
      <button type="button" class="text-secondary text-sm font-bold underline" @click="refresh">تلاش مجدد</button>
      <NuxtLink to="/wallet" class="inline-block text-secondary text-sm font-bold underline">شارژ کیف پول</NuxtLink>
      <NuxtLink :to="`/tournaments/${id}`" class="block text-sm text-gray-400">بازگشت به مسابقه</NuxtLink>
    </div>

    <template v-else-if="data">
      <p v-if="viewOnly" class="seat-page__legend">
        <span class="seat-page__legend-item seat-page__legend-item--me">جایگاه شما</span>
        <span v-if="seatMode > 1" class="seat-page__legend-item seat-page__legend-item--team">هم‌تیمی</span>
        <span class="seat-page__legend-item">بقیه بازیکن‌ها</span>
      </p>

      <div v-if="viewOnly && (me || teammates.length)" class="seat-page__team-summary">
        <p class="seat-page__team-summary-title">{{ myTeamLabel || 'تیم شما' }}</p>
        <ul class="seat-page__team-list">
          <li v-if="me">
            <span class="seat-page__team-badge seat-page__team-badge--me">شما</span>
            <span>{{ me.username }}</span>
            <span class="seat-page__team-cod" dir="ltr">{{ me.cod_id }}</span>
            <span class="seat-page__team-seat" dir="ltr">{{ me.seat_label }}</span>
          </li>
          <li v-for="member in teammates" :key="member.seat_number">
            <span class="seat-page__team-badge">هم‌تیمی</span>
            <span>{{ member.username }}</span>
            <span class="seat-page__team-cod" dir="ltr">{{ member.cod_id }}</span>
            <span class="seat-page__team-seat" dir="ltr">{{ member.seat_label }}</span>
          </li>
        </ul>
      </div>

      <div v-if="errors.length" class="seat-page__errors">
        <p v-for="(err, i) in errors" :key="i">{{ err }}</p>
      </div>

      <div v-if="teamsGrid.length" class="seat-page__body">
        <TournamentSeatGrid
          :teams="teamsGrid"
          :occupied-seats="occupiedSeats as Record<number, OccupiedSeatInfo>"
          :seat-mode="seatMode"
          :selected-seat="pendingSeat"
          :selected-team="pendingTeam"
          :team-select-mode="isTeamReservation"
          :my-seat-number="mySeatNumber"
          :my-team-number="myTeamOverride"
          :interactive="!viewOnly"
          @select="openConfirm"
        />
      </div>
    </template>

    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="closeConfirm">
        <div class="modal-panel seat-modal-panel" :class="{ 'seat-modal-panel--wide': isTeamReservation }" @click.stop>
          <h2 class="modal-panel__title">تأیید {{ isTeamReservation ? 'تیم' : 'جایگاه' }}</h2>
          <p class="text-sm text-gray-300 text-center">
            {{ isTeamReservation ? 'تیم انتخاب‌شده:' : 'آیا این جایگاه را انتخاب می‌کنید؟' }}
          </p>
          <p class="seat-page__selected-value" dir="ltr">{{ pendingLabel || '—' }}</p>

          <div v-if="isTeamReservation" class="seat-modal-form">
            <p class="text-sm text-gray-400 mb-2">
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
              class="seat-modal-form__input"
              dir="ltr"
            >
            <p class="text-xs text-amber-300 mt-2">
              پس از ارسال، هم‌تیمی‌ها ۱۵ ثانیه فرصت تأیید دارند. تا آن زمان مبلغی کسر نمی‌شود.
            </p>
          </div>

          <div class="flex gap-3 mt-4">
            <button
              type="button"
              class="btn-glow-success flex-1 rounded-lg py-2 text-sm font-bold disabled:opacity-50"
              :disabled="loading || !teamFormValid"
              @click="confirmSeat"
            >
              {{ loading ? 'در حال ثبت...' : (isTeamReservation ? 'ارسال درخواست تیمی' : 'تأیید و پرداخت') }}
            </button>
            <button
              type="button"
              class="bg-gray-600 text-white rounded-lg px-4 py-2 text-sm font-bold"
              :disabled="loading"
              @click="closeConfirm"
            >
              انصراف
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.seat-page__legend {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.45rem;
  margin: 0 auto 0.85rem;
}

.seat-page__legend-item {
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: rgba(55, 65, 81, 0.8);
  color: #d1d5db;
  font-size: 0.7rem;
  font-weight: 700;
}

.seat-page__legend-item--me {
  background: rgba(34, 197, 94, 0.25);
  color: #86efac;
}

.seat-page__legend-item--team {
  background: rgba(56, 189, 248, 0.2);
  color: #7dd3fc;
}

.seat-page__banner {
  margin: 0 auto 1rem;
  max-width: 42rem;
  padding: 0.55rem 0.75rem;
  border: 1px solid rgba(139, 92, 246, 0.35);
  border-radius: 0.75rem;
  background: rgba(139, 92, 246, 0.08);
  color: #c4b5fd;
  font-size: 0.8rem;
  text-align: center;
}

.seat-page__selected-value {
  margin: 0.75rem 0 1rem;
  font-size: 1.75rem;
  font-weight: 900;
  color: #d4af37;
  font-family: ui-monospace, monospace;
  text-align: center;
}

.seat-page__team-summary {
  margin-bottom: 0.85rem;
  padding: 0.75rem 0.85rem;
  border: 1px solid rgba(34, 197, 94, 0.35);
  border-radius: 0.75rem;
  background: rgba(20, 83, 45, 0.25);
}

.seat-page__team-summary-title {
  margin: 0 0 0.5rem;
  font-size: 0.85rem;
  font-weight: 800;
  color: #86efac;
}

.seat-page__team-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.seat-page__team-list li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem 0.55rem;
  font-size: 0.82rem;
  color: #e5e7eb;
}

.seat-page__team-badge {
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  background: rgba(56, 189, 248, 0.2);
  color: #7dd3fc;
  font-size: 0.68rem;
  font-weight: 700;
}

.seat-page__team-badge--me {
  background: rgba(34, 197, 94, 0.25);
  color: #86efac;
}

.seat-page__team-cod {
  color: #d4af37;
  font-size: 0.72rem;
}

.seat-page__team-seat {
  color: #9ca3af;
  font-family: ui-monospace, monospace;
  font-size: 0.72rem;
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
}

.seat-modal-panel {
  max-width: 22rem;
  text-align: center;
}

.seat-modal-panel--wide {
  max-width: 26rem;
}

.seat-modal-form {
  text-align: right;
  margin-top: 0.5rem;
}

.seat-modal-form__input {
  width: 100%;
  margin-bottom: 0.45rem;
  border: 1px solid #4b5563;
  border-radius: 0.45rem;
  background: #111827;
  color: #fff;
  padding: 0.55rem 0.65rem;
  font-size: 0.85rem;
}
</style>
