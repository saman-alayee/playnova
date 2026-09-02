<script setup lang="ts">
import type { TournamentShowData } from '~/types/api'

const route = useRoute()
const api = useApi()
const auth = useAuthStore()
const flash = useState<{ success?: string; error?: string; info?: string } | null>('flash')
const { openRegisterModal, openGameLoginModalById } = useModals()
const { formatDateTime } = usePersianDateTime()

const id = computed(() => route.params.id as string)

const { data, pending, error, refresh } = usePageData(
  () => `tournament-${id.value}`,
  () => api.tournaments.show(id.value),
)

const tournament = computed(() => data.value?.tournament)
const isRegistered = computed(() => data.value?.is_registered ?? false)
const pendingSeat = computed(() => data.value?.pending_seat ?? false)
const registration = computed(() => data.value?.registration)

useHead(() => ({ title: `${tournament.value?.title || 'مسابقه'} | PlayNova` }))

const cancelling = ref(false)

async function cancelPending() {
  if (!tournament.value) return
  cancelling.value = true
  try {
    await api.tournaments.cancelPending(tournament.value.id)
    flash.value = { info: 'ثبت‌نام ناتمام لغو شد.' }
    await refresh()
  } catch (e: unknown) {
    const err = e as { message?: string }
    flash.value = { error: err.message || 'لغو ثبت‌نام ناموفق بود.' }
  } finally {
    cancelling.value = false
  }
}

function openRegister() {
  if (tournament.value) {
    openRegisterModal(tournament.value)
  }
}

const statusLabel: Record<string, string> = {
  upcoming: 'آینده',
  active: 'فعال',
  ongoing: 'در حال برگزاری',
  ended: 'پایان یافته',
  cancelled: 'لغو شده',
}

const statusColor: Record<string, string> = {
  upcoming: 'bg-secondary text-white',
  active: 'bg-success text-white',
  ongoing: 'bg-success text-white',
  ended: 'bg-gray-600',
  cancelled: 'bg-gray-800',
}
</script>

<template>
  <div>
    <PageLoading v-if="pending" />
    <div v-else-if="error || !tournament" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      مسابقه یافت نشد.
    </div>
    <template v-else>
      <div class="mb-6">
        <NuxtLink to="/" class="text-sm text-secondary">← بازگشت</NuxtLink>
      </div>

      <div class="max-w-2xl mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
        <div class="flex justify-between items-start mb-4 gap-3">
          <h1 class="text-2xl font-bold text-white">{{ tournament.title }}</h1>
          <span
            class="text-xs px-3 py-1 rounded-full font-bold shrink-0"
            :class="statusColor[tournament.status] || 'bg-gray-700'"
          >
            {{ statusLabel[tournament.status] || tournament.status_label || tournament.status }}
          </span>
        </div>

        <p class="text-gray-400 mb-4">{{ tournament.description || 'توضیحاتی برای این مسابقه ثبت نشده است.' }}</p>

        <div class="grid grid-cols-2 gap-4 text-sm mb-6">
          <div class="bg-dark-700 rounded-lg p-3">
            <p class="text-gray-400">مبلغ ورودی</p>
            <p class="font-bold">{{ Number(tournament.entry_fee).toLocaleString('fa-IR') }} تومان</p>
          </div>
          <div class="bg-dark-700 rounded-lg p-3">
            <p class="text-gray-400">جایزه کل</p>
            <p class="font-bold text-secondary">{{ Number(tournament.prize_pool).toLocaleString('fa-IR') }} تومان</p>
          </div>
          <div class="bg-dark-700 rounded-lg p-3">
            <p class="text-gray-400">تاریخ شروع</p>
            <p class="font-bold">{{ formatDateTime(tournament.start_date_display || tournament.start_date) }}</p>
          </div>
          <div class="bg-dark-700 rounded-lg p-3">
            <p class="text-gray-400">ظرفیت</p>
            <p class="font-bold">
              {{ (tournament.remaining_capacity ?? (tournament.capacity - (tournament.registered_count ?? 0))).toLocaleString('fa-IR') }}
              / {{ Number(tournament.capacity).toLocaleString('fa-IR') }}
            </p>
          </div>
          <div v-if="tournament.seat_mode_label" class="bg-dark-700 rounded-lg p-3 sm:col-span-2">
            <p class="text-gray-400">نوع چیدمان جایگاه</p>
            <p class="font-bold">{{ tournament.seat_mode_label }}</p>
          </div>
        </div>

        <template v-if="auth.isAuthenticated">
          <div
            v-if="isRegistered"
            class="bg-green-900/30 border border-green-500 text-green-300 rounded-lg p-3 text-center text-sm mb-4"
          >
            ✅ شما در این مسابقه ثبت‌نام کرده‌اید.
            <span v-if="registration?.seat_number"> — جایگاه: <strong>{{ registration.seat_number }}</strong></span>
          </div>

          <div v-else-if="pendingSeat" class="space-y-3 mb-4">
            <NuxtLink
              :to="`/tournaments/${tournament.id}/select-seat`"
              class="block w-full text-center bg-success hover:opacity-90 text-white rounded py-2 font-bold text-sm"
            >
              انتخاب جایگاه
            </NuxtLink>
            <button
              type="button"
              class="w-full bg-gray-700 hover:bg-gray-600 text-white rounded py-2 text-sm font-bold"
              :disabled="cancelling"
              @click="cancelPending"
            >
              {{ cancelling ? '...' : 'لغو ثبت‌نام ناتمام' }}
            </button>
          </div>

          <div v-else-if="(tournament.registered_count ?? 0) >= tournament.capacity" class="bg-gray-800 border border-gray-600 text-gray-400 rounded-lg p-3 text-center text-sm mb-4">
            ظرفیت این مسابقه تکمیل شده است.
          </div>

          <button
            v-else-if="tournament.accepts_registration"
            type="button"
            class="w-full bg-success hover:opacity-90 text-white rounded py-2 font-bold shadow-glowsuccess text-sm mb-4"
            @click="openRegister"
          >
            ثبت‌نام در مسابقه
          </button>

          <button
            v-if="isRegistered && tournament.allows_game_login"
            type="button"
            class="w-full btn-glow-primary rounded py-2 text-sm font-bold"
            @click="openGameLoginModalById(tournament.id)"
          >
            اطلاعات ورود به بازی
          </button>
        </template>

        <NuxtLink
          v-else
          to="/login"
          class="block w-full text-center bg-success hover:opacity-90 text-white rounded py-2 font-bold text-sm"
        >
          ورود و ثبت‌نام
        </NuxtLink>
      </div>
    </template>
  </div>
</template>
