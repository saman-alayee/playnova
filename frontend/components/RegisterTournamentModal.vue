<script setup lang="ts">
import type { RuleSection } from '~/types/api'

const {
  registerOpen,
  registerTournament,
  closeRegisterModal,
  closeDescriptionModal,
  beginRegistrationNavigation,
  clearRegisterBodyLock,
  armDescriptionSuppression,
  armRegisterSuppression,
} = useModals()

const api = useApi()
const auth = useAuthStore()
const flash = useState<{ success?: string; error?: string; info?: string } | null>('flash')

const step = ref<'rules' | 'type'>('rules')
const acceptRules = ref(false)
const loading = ref(false)
const errorMessage = ref('')

const rules = ref<RuleSection[]>([])

const requiredInvites = computed(() => {
  const mode = registerTournament.value?.seat_mode ?? 1
  return Math.max(0, mode - 1)
})

const supportsTeam = computed(() => requiredInvites.value >= 1)

const entryFee = computed(() => Number(registerTournament.value?.entry_fee ?? 0))

const hasEnoughWallet = computed(() => auth.walletBalance >= entryFee.value)

watch(registerOpen, (open) => {
  if (import.meta.client) {
    document.body.classList.toggle('register-modal-active', open)
  }
})

watch(registerOpen, async (open) => {
  if (open) {
    step.value = 'rules'
    acceptRules.value = false
    errorMessage.value = ''
    closeDescriptionModal()
    try {
      await auth.fetchUser()
      rules.value = await api.rules()
    } catch {
      rules.value = []
    }
  } else if (import.meta.client) {
    document.body.classList.remove('register-modal-active')
  }
})

function close() {
  closeRegisterModal()
}

function walletError() {
  return `موجودی کیف پول کافی نیست. حداقل ${entryFee.value.toLocaleString('fa-IR')} تومان لازم است (موجودی شما: ${auth.walletBalance.toLocaleString('fa-IR')} تومان).`
}

async function continueFromRules() {
  if (!acceptRules.value || loading.value) return
  if (!hasEnoughWallet.value) {
    errorMessage.value = walletError()
    return
  }
  errorMessage.value = ''
  if (supportsTeam.value) {
    step.value = 'type'
    return
  }
  await startRegistration('solo')
}

async function goToSeats(reservationType: 'solo' | 'team') {
  await startRegistration(reservationType)
}

async function startRegistration(reservationType: 'solo' | 'team') {
  const t = registerTournament.value
  if (!t || loading.value) return
  if (!hasEnoughWallet.value) {
    errorMessage.value = walletError()
    return
  }

  loading.value = true
  errorMessage.value = ''
  closeDescriptionModal()
  armDescriptionSuppression(8000)
  armRegisterSuppression(4000)

  const target = `/tournaments/${t.id}/select-seat`

  try {
    await api.tournaments.register(t.id, {
      reservation_type: reservationType,
      accept_rules: '1',
    })

    beginRegistrationNavigation()
    closeRegisterModal({ keepNavigating: true })

    const nav = await navigateTo(target, { replace: true, external: false })
    if (nav === false && import.meta.client) {
      window.location.assign(target)
    }
  } catch (e: unknown) {
    clearRegisterBodyLock()
    const err = e as { message?: string }
    const message = err.message || 'ثبت‌نام ناموفق بود.'
    errorMessage.value = message
    if (message.includes('کیف پول')) {
      flash.value = { error: message }
    }
  } finally {
    loading.value = false
  }
}

onUnmounted(() => {
  clearRegisterBodyLock()
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="registerOpen && registerTournament"
      class="modal-overlay register-tournament-modal"
      @click.self="close"
    >
      <div class="modal-panel register-tournament-modal__panel" @click.stop>
        <h2 class="modal-panel__title">{{ registerTournament.title }}</h2>

        <p v-if="errorMessage" class="register-tournament-modal__error">{{ errorMessage }}</p>
        <p v-if="errorMessage.includes('کیف پول')" class="text-center mb-3">
          <NuxtLink to="/wallet" class="text-secondary text-sm font-bold underline" @click="close">
            شارژ کیف پول
          </NuxtLink>
        </p>

        <div v-if="step === 'rules'">
          <h3 class="register-tournament-modal__heading">📜 تأیید خواندن قوانین</h3>
          <div class="modal-panel__body register-tournament-modal__rules">
            <div v-for="(rule, index) in rules" :key="rule.id" class="mb-3">
              <span class="text-gray-300">{{ rule.content }}</span>
            </div>
            <p v-if="!rules?.length" class="text-gray-500">هیچ قانونی ثبت نشده است.</p>
          </div>
          <label class="flex items-start gap-2 mt-4 text-sm text-gray-300">
            <input v-model="acceptRules" type="checkbox" class="mt-1 accent-primary">
            قوانین و مقررات را مطالعه کرده و با تمامی موارد آن موافقم.
          </label>
          <div class="flex gap-3 mt-4">
            <button
              type="button"
              class="btn-glow-success flex-1 rounded-lg py-2 text-sm font-bold disabled:opacity-50"
              :disabled="!acceptRules || loading"
              @click.stop.prevent="continueFromRules"
            >
              {{ loading ? 'در حال ثبت‌نام...' : 'ادامه' }}
            </button>
            <button type="button" class="bg-gray-600 text-white rounded-lg px-4 py-2 text-sm font-bold" @click="close">
              انصراف
            </button>
          </div>
        </div>

        <div v-else>
          <h3 class="register-tournament-modal__heading">نوع ثبت‌نام</h3>
          <p class="register-tournament-modal__type-text">
            نحوه رزرو جایگاه خود را انتخاب کنید.
          </p>
          <div class="space-y-3">
            <button
              type="button"
              class="btn-glow-success w-full rounded-lg py-3 text-sm font-bold"
              :disabled="loading"
              @click.stop.prevent="goToSeats('solo')"
            >
              🎯 رزرو تکی — انتخاب جایگاه
            </button>
            <button
              v-if="supportsTeam"
              type="button"
              class="w-full bg-secondary hover:opacity-90 text-white rounded-lg py-3 text-sm font-bold disabled:opacity-50"
              :disabled="loading"
              @click.stop.prevent="goToSeats('team')"
            >
              👥 رزرو تیمی — دعوت هم‌تیمی
            </button>
            <button
              type="button"
              class="w-full bg-gray-600 text-white rounded-lg py-2 text-sm font-bold"
              :disabled="loading"
              @click="step = 'rules'"
            >
              بازگشت
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.register-tournament-modal {
  z-index: 100001;
}

.register-tournament-modal__panel {
  max-width: 560px;
}

.register-tournament-modal__heading {
  font-weight: 800;
  color: #8B5CF6;
  margin-bottom: 0.75rem;
}

.register-tournament-modal__type-text {
  color: #fff;
  font-size: 0.9rem;
  margin-bottom: 1rem;
}

.register-tournament-modal__rules {
  max-height: 45vh;
  overflow-y: auto;
}

.register-tournament-modal__error {
  margin-bottom: 0.75rem;
  padding: 0.55rem 0.7rem;
  border: 1px solid #b91c1c;
  border-radius: 0.75rem;
  background: rgba(127, 29, 29, 0.35);
  color: #fca5a5;
  font-size: 0.8rem;
}
</style>
