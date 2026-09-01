<script setup lang="ts">
import type { RuleSection, Tournament } from '~/types/api'

const {
  registerOpen,
  registerTournament,
  closeRegisterModal,
  armDescriptionSuppression,
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
    try {
      await auth.fetchUser()
      rules.value = await api.rules()
    } catch {
      rules.value = []
    }
  }
})

function close() {
  closeRegisterModal()
}

function walletError() {
  return `موجودی کیف پول کافی نیست. حداقل ${entryFee.value.toLocaleString('fa-IR')} تومان لازم است (موجودی شما: ${auth.walletBalance.toLocaleString('fa-IR')} تومان).`
}

async function continueFromRules() {
  if (!hasEnoughWallet.value) {
    errorMessage.value = walletError()
    return
  }
  if (!supportsTeam.value) {
    await startRegistration('solo')
    return
  }
  step.value = 'type'
}

async function startRegistration(reservationType: 'solo' | 'team') {
  const t = registerTournament.value
  if (!t) return
  if (!hasEnoughWallet.value) {
    errorMessage.value = walletError()
    return
  }
  loading.value = true
  errorMessage.value = ''
  armDescriptionSuppression(1500)
  try {
    await auth.fetchUser()
    await api.tournaments.register(t.id, { reservation_type: reservationType })
    await auth.fetchUser()
    await refreshNuxtData('home')
    closeRegisterModal()
    await navigateTo(`/tournaments/${t.id}/select-seat`, { replace: true })
  } catch (e: unknown) {
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
  if (import.meta.client) {
    document.body.classList.remove('register-modal-active')
  }
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

        <p class="register-tournament-modal__wallet text-xs text-gray-500 mb-3">
          موجودی کیف پول:
          <strong>{{ auth.walletBalance.toLocaleString('fa-IR') }} تومان</strong>
          —
          هزینه ورودی:
          <strong>{{ entryFee.toLocaleString('fa-IR') }} تومان</strong>
        </p>
        <p v-if="!hasEnoughWallet" class="register-tournament-modal__wallet-warn text-xs text-amber-300 mb-3">
          برای ادامه ثبت‌نام، ابتدا کیف پول را شارژ کنید.
        </p>

        <div v-if="step === 'rules'">
          <h3 class="font-bold text-primary mb-3">📜 تأیید خواندن قوانین</h3>
          <div class="modal-panel__body register-tournament-modal__rules">
            <div v-for="(rule, index) in rules" :key="rule.id" class="mb-3">
              <strong class="text-secondary">بخش {{ index + 1 }}:</strong>
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
              :disabled="!acceptRules || loading || !hasEnoughWallet"
              @click.stop.prevent="continueFromRules"
            >
              {{ loading ? '...' : (supportsTeam ? 'ادامه — انتخاب نوع ثبت‌نام' : 'ادامه و انتخاب جایگاه') }}
            </button>
            <button type="button" class="bg-gray-600 text-white rounded-lg px-4 py-2 text-sm font-bold" @click="close">
              انصراف
            </button>
          </div>
        </div>

        <div v-else>
          <h3 class="font-bold text-primary mb-3">نوع ثبت‌نام</h3>
          <p class="text-sm text-gray-400 mb-4">
            این مسابقه {{ registerTournament.seat_mode_label || 'تیمی' }} است. نحوه رزرو جایگاه را انتخاب کنید.
          </p>
          <p class="text-xs text-gray-500 mb-4">
            تا تأیید نهایی جایگاه، مبلغی کسر نمی‌شود؛ اما موجودی کافی در کیف پول لازم است.
          </p>
          <div class="space-y-3">
            <button
              type="button"
              class="btn-glow-success w-full rounded-lg py-3 text-sm font-bold"
              :disabled="loading || !hasEnoughWallet"
              @click.stop.prevent="startRegistration('solo')"
            >
              🎯 ثبت‌نام تکی — انتخاب جایگاه
            </button>
            <button
              v-if="supportsTeam"
              type="button"
              class="w-full bg-secondary hover:opacity-90 text-white rounded-lg py-3 text-sm font-bold disabled:opacity-50"
              :disabled="loading || !hasEnoughWallet"
              @click.stop.prevent="startRegistration('team')"
            >
              👥 ثبت‌نام تیمی — انتخاب تیم
              <span v-if="requiredInvites === 1" class="block text-xs font-normal mt-1 opacity-90">
                فقط شما درخواست می‌دهید؛ ۱ آیدی هم‌تیمی در مرحله تأیید
              </span>
              <span v-else class="block text-xs font-normal mt-1 opacity-90">
                فقط شما درخواست می‌دهید؛ {{ requiredInvites }} آیدی هم‌تیمی در مرحله تأیید
              </span>
            </button>
            <button type="button" class="w-full bg-gray-600 text-white rounded-lg py-2 text-sm font-bold" @click="step = 'rules'">
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

.register-tournament-modal__wallet-warn {
  line-height: 1.6;
}
</style>
