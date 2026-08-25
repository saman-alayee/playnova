<script setup lang="ts">
import type { TeamInvite } from '~/types/api'

const auth = useAuthStore()
const api = useApi()

const pending = ref<TeamInvite[]>([])
const sent = ref<TeamInvite[]>([])
let pollTimer: ReturnType<typeof setInterval> | null = null

async function refresh(force = false) {
  if (!auth.isAuthenticated || (import.meta.client && document.hidden && !force)) return
  try {
    const data = await api.teamInvites.banner()
    pending.value = data.pending || []
    sent.value = data.sent || []
  } catch {
    if (force) {
      pending.value = []
      sent.value = []
    }
  }
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function startPolling() {
  stopPolling()
  if (!auth.isAuthenticated) return
  void refresh(true)
  pollTimer = setInterval(() => refresh(false), 3000)
}

function onVisibilityChange() {
  if (document.hidden) {
    stopPolling()
  } else {
    startPolling()
  }
}

async function handleAction(action: 'accept' | 'decline' | 'cancel', id: number) {
  try {
    if (action === 'accept') await api.teamInvites.accept(id)
    else if (action === 'decline') await api.teamInvites.decline(id)
    else await api.teamInvites.cancel(id)
    await refresh(true)
  } catch (e: unknown) {
    const err = e as Error
    alert(err.message || 'عملیات ناموفق بود.')
  }
}

onMounted(() => {
  document.addEventListener('visibilitychange', onVisibilityChange)
  startPolling()
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisibilityChange)
  stopPolling()
})

watch(() => auth.isAuthenticated, (val) => {
  if (val) startPolling()
  else {
    stopPolling()
    pending.value = []
    sent.value = []
  }
})
</script>

<template>
  <div v-if="auth.isAuthenticated && (pending.length || sent.length)" class="fixed bottom-4 left-4 right-4 z-[9999] max-w-lg mx-auto space-y-2">
    <div
      v-for="invite in pending"
      :key="`pending-${invite.id}`"
      class="bg-dark-800 border border-secondary/40 rounded-xl p-4 shadow-lg"
    >
      <p class="text-sm text-white mb-2">
        <strong>{{ invite.inviter_username }}</strong>
        شما را به مسابقه
        <strong>{{ invite.tournament_title }}</strong>
        دعوت کرده است.
        <span v-if="invite.seconds_remaining" class="text-yellow-400 text-xs block mt-1">
          {{ invite.seconds_remaining }} ثانیه باقی‌مانده
        </span>
      </p>
      <div class="flex gap-2">
        <button
          class="flex-1 btn-glow-success rounded-lg py-2 text-sm"
          @click="handleAction('accept', invite.id)"
        >
          پذیرش
        </button>
        <button
          class="flex-1 tournament-actions__btn tournament-actions__btn--outline"
          @click="handleAction('decline', invite.id)"
        >
          رد
        </button>
      </div>
    </div>

    <div
      v-for="invite in sent"
      :key="`sent-${invite.id}`"
      class="bg-dark-800 border border-primary/30 rounded-xl p-4 shadow-lg"
    >
      <p class="text-sm text-gray-300 mb-2">
        دعوت
        <strong>{{ invite.invitee_username }}</strong>
        برای
        <strong>{{ invite.tournament_title }}</strong>
        در انتظار پاسخ است.
      </p>
      <button
        class="w-full tournament-actions__btn tournament-actions__btn--outline"
        @click="handleAction('cancel', invite.id)"
      >
        لغو دعوت
      </button>
    </div>
  </div>
</template>
