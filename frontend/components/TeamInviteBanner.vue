<script setup lang="ts">
import type { TeamInvite } from '~/types/api'

const auth = useAuthStore()
const api = useApi()

const pending = ref<TeamInvite[]>([])
const sent = ref<TeamInvite[]>([])
const loading = ref(false)
let pollTimer: ReturnType<typeof setInterval> | null = null

async function refresh(force = false) {
  if (!auth.isAuthenticated) return
  loading.value = true
  try {
    const data = await api.teamInvites.banner()
    pending.value = data.pending || []
    sent.value = data.sent || []
  } catch {
    if (force) {
      pending.value = []
      sent.value = []
    }
  } finally {
    loading.value = false
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
  if (auth.isAuthenticated) {
    refresh(true)
    pollTimer = setInterval(() => refresh(false), 12000)
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) refresh(true)
    })
  }
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})

watch(() => auth.isAuthenticated, (val) => {
  if (val) refresh(true)
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
