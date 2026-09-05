<script setup lang="ts">
import type { Tournament } from '~/types/api'

const props = withDefaults(defineProps<{
  tournament: Tournament
  regCount?: number
  capacity?: number
  compact?: boolean
}>(), {
  compact: false,
})

const auth = useAuthStore()
const { closeDescriptionModal, openDescriptionModal, openGameLoginModalById, openRegisterModal, armDescriptionSuppression } = useModals()

const regCount = computed(() => {
  if (props.regCount !== undefined) return props.regCount
  const count = props.tournament.registrations_count ?? props.tournament.registered_count ?? 0
  return Math.max(0, Number(count))
})

const capacity = computed(() => {
  if (props.capacity !== undefined) return props.capacity
  return Math.max(1, Number(props.tournament.capacity ?? 1))
})

const hasDescription = computed(() => !!props.tournament.description?.trim())
const hideDescriptionAction = computed(() =>
  !!props.tournament.is_registered && !!props.tournament.allows_game_login,
)
const actionRowClass = computed(() => {
  if (props.tournament.is_registered && props.tournament.allows_game_login) {
    return ''
  }
  return hasDescription.value ? '' : 'tournament-actions--single'
})

function onRegisterClick() {
  closeDescriptionModal()
  armDescriptionSuppression(800)
  openRegisterModal(props.tournament)
}
</script>

<template>
  <div class="tournament-actions" :class="actionRowClass">
    <template v-if="auth.isAuthenticated">
      <template v-if="tournament.is_registered">
        <NuxtLink
          :to="`/tournaments/${tournament.id}/select-seat`"
          class="tournament-actions__btn tournament-actions__btn--primary"
        >
          مشاهده جایگاه‌ها
        </NuxtLink>
        <button
          v-if="tournament.allows_game_login"
          type="button"
          class="tournament-actions__btn tournament-actions__btn--outline"
          @click="openGameLoginModalById(tournament.id)"
        >
          اطلاعات ورود
        </button>
      </template>
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
        class="tournament-actions__btn tournament-actions__btn--primary tournament-actions__btn--success tournament-actions__btn--register"
        @mousedown.stop.prevent
        @click.stop.prevent="onRegisterClick"
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

    <button
      v-if="hasDescription && !hideDescriptionAction"
      type="button"
      class="tournament-actions__btn tournament-actions__btn--outline"
      @mousedown.stop.prevent
      @click.stop.prevent="openDescriptionModal(tournament.title, tournament.description!)"
    >
      توضیحات
    </button>
  </div>
</template>
