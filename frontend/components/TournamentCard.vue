<script setup lang="ts">
import type { Tournament } from '~/types/api'

const props = withDefaults(defineProps<{
  tournament: Tournament
  compact?: boolean
}>(), {
  compact: false,
})

const auth = useAuthStore()
const { openDescriptionModal, openGameLoginModalById, openRegisterModal } = useModals()
const { formatDate: formatIranDate, formatTime: formatIranTime } = usePersianDateTime()

const regCount = computed(() => {
  const count = props.tournament.registrations_count ?? props.tournament.registered_count ?? 0
  return Math.max(0, Number(count))
})

const capacity = computed(() => Math.max(1, Number(props.tournament.capacity ?? 1)))
const percent = computed(() => Math.min(100, Math.max(0, Math.round((regCount.value / capacity.value) * 100))))
const hasDescription = computed(() => !!props.tournament.description?.trim())

const statusClass = computed(() => {
  const map: Record<string, string> = {
    active: 'bg-success/20 text-success',
    ongoing: 'bg-primary/20 text-primary',
    upcoming: 'bg-secondary/20 text-secondary',
  }
  return map[props.tournament.status] || 'bg-gray-700/30 text-gray-400'
})

const statusLabel = computed(() => props.tournament.status_label || props.tournament.status)

function formatNumber(n: number) {
  return n.toLocaleString('fa-IR')
}

function formatDate(date?: string | null) {
  return formatIranDate(date, props.tournament.start_date_display)
}

function formatTime(date?: string | null) {
  return formatIranTime(date, props.tournament.start_date_display)
}
</script>

<template>
  <div
    class="card-tournament rounded-2xl p-5"
    :class="compact ? 'min-w-[280px] max-w-[320px] shrink-0' : ''"
  >
    <div class="flex justify-between items-start mb-3 gap-2">
      <h3 class="font-bold text-base text-white leading-snug">{{ tournament.title || 'بدون عنوان' }}</h3>
      <span class="text-xs shrink-0 px-2 py-1 rounded-full" :class="statusClass">
        {{ statusLabel }}
      </span>
    </div>

    <p class="text-xs text-gray-400 mb-2">{{ tournament.game || 'Call of Duty Mobile' }}</p>

    <div class="tournament-schedule mb-3">
      <div class="tournament-schedule__date">
        <span class="tournament-schedule__date-label">تاریخ شروع</span>
        <span class="tournament-schedule__date-value">{{ formatDate(tournament.start_date) }}</span>
      </div>
      <div v-if="tournament.start_date" class="tournament-schedule__time">
        <div class="tournament-schedule__time-label">⏰ ساعت برگزاری</div>
        <div class="tournament-schedule__time-value">{{ formatTime(tournament.start_date) }}</div>
      </div>
    </div>

    <div class="text-sm space-y-2 mb-3">
      <div class="flex justify-between">
        <span class="text-gray-400">ورودی:</span>
        <span class="font-bold">{{ formatNumber(Number(tournament.entry_fee)) }} تومان</span>
      </div>
      <div class="flex justify-between">
        <span class="text-gray-400">جایزه:</span>
        <span class="font-bold text-secondary">{{ formatNumber(Number(tournament.prize_pool)) }} تومان</span>
      </div>
      <div class="flex justify-between">
        <span class="text-gray-400">ثبت‌نام‌شده:</span>
        <span class="font-bold">
          <span dir="ltr" class="inline-block">{{ formatNumber(regCount) }}/{{ formatNumber(capacity) }}</span>
        </span>
      </div>
    </div>

    <div class="w-full h-2 bg-gray-700 rounded-full overflow-hidden mb-4">
      <div
        class="tournament-capacity-fill h-full rounded-full"
        :class="regCount > 0 && percent < 4 ? 'tournament-capacity-fill--min' : ''"
        :style="{ width: `${percent}%` }"
      />
    </div>

    <div
      class="tournament-actions"
      :class="hasDescription ? '' : 'tournament-actions--single'"
    >
      <button
        v-if="hasDescription"
        type="button"
        class="tournament-actions__btn tournament-actions__btn--outline"
        @click="openDescriptionModal(tournament.title, tournament.description!)"
      >
        توضیحات
      </button>

      <template v-if="auth.isAuthenticated">
        <button
          v-if="tournament.is_registered && tournament.allows_game_login"
          type="button"
          class="tournament-actions__btn tournament-actions__btn--primary"
          @click="openGameLoginModalById(tournament.id)"
        >
          اطلاعات ورود
        </button>
        <span
          v-else-if="tournament.is_registered"
          class="tournament-actions__btn tournament-actions__btn--muted"
        >
          ثبت‌نام شده
        </span>
        <span
          v-else-if="tournament.pending_team"
          class="tournament-actions__btn tournament-actions__btn--muted"
        >
          در انتظار تأیید هم‌تیمی
        </span>
        <NuxtLink
          v-else-if="tournament.pending_seat"
          :to="`/tournaments/${tournament.id}/select-seat`"
          class="tournament-actions__btn tournament-actions__btn--primary tournament-actions__btn--success"
        >
          انتخاب جایگاه
        </NuxtLink>
        <button
          v-else-if="tournament.accepts_registration && regCount < capacity"
          type="button"
          class="tournament-actions__btn tournament-actions__btn--primary tournament-actions__btn--success"
          @click="openRegisterModal(tournament)"
        >
          {{ compact ? 'ثبت‌نام' : 'ثبت‌نام' }}
        </button>
        <span
          v-else-if="tournament.status === 'ongoing'"
          class="tournament-actions__btn tournament-actions__btn--muted"
        >
          ثبت‌نام بسته شده
        </span>
        <span
          v-else-if="tournament.accepts_registration"
          class="tournament-actions__btn tournament-actions__btn--muted"
        >
          ظرفیت تکمیل
        </span>
        <span v-else class="tournament-actions__btn tournament-actions__btn--muted">به‌زودی</span>
      </template>
      <NuxtLink
        v-else
        to="/login"
        class="tournament-actions__btn tournament-actions__btn--primary"
      >
        {{ compact ? 'ورود' : 'ورود و ثبت‌نام' }}
      </NuxtLink>
    </div>
  </div>
</template>
