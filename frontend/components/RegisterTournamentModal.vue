<script setup lang="ts">
import type { RuleSection, Tournament } from '~/types/api'

const {
  registerOpen,
  registerTournament,
  closeRegisterModal,
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

watch(registerOpen, async (open) => {
  if (open) {
    step.value = 'rules'
    acceptRules.value = false
    errorMessage.value = ''
    try {
      rules.value = await api.rules()
    } catch {
      rules.value = []
    }
  }
})

function close() {
  closeRegisterModal()
}

async function startRegistration(reservationType: 'solo' | 'team') {
  const t = registerTournament.value
  if (!t) return
  loading.value = true
  errorMessage.value = ''
  try {
    await api.tournaments.register(t.id, { reservation_type: reservationType })
    flash.value = {
      info: reservationType === 'team'
        ? 'تیم مورد نظر را انتخاب کنید و آیدی هم‌تیمی‌ها را در مرحله تأیید وارد کنید.'
        : 'برای تکمیل ثبت‌نام، جایگاه خود را انتخاب کنید.',
    }
    await auth.fetchUser()
    await navigateTo(`/tournaments/${t.id}/select-seat`)
    close()
  } catch (e: unknown) {
    const err = e as { message?: string }
    errorMessage.value = err.message || 'ثبت‌نام ناموفق بود.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="registerOpen && registerTournament"
      class="modal-overlay"
      @click.self="close"
    >
      <div class="modal-panel register-tournament-modal__panel">
        <h2 class="modal-panel__title">{{ registerTournament.title }}</h2>

        <p v-if="errorMessage" class="text-sm text-danger mb-3">{{ errorMessage }}</p>

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
              :disabled="!acceptRules"
              @click="step = 'type'"
            >
              ادامه
            </button>
            <button type="button" class="bg-gray-600 text-white rounded-lg px-4 py-2 text-sm font-bold" @click="close">
              انصراف
            </button>
          </div>
        </div>

        <div v-else>
          <h3 class="font-bold text-primary mb-3">نوع ثبت‌نام</h3>
          <p class="text-sm text-gray-400 mb-4">نحوه رزرو جایگاه خود را انتخاب کنید. تا تأیید نهایی، مبلغی کسر نمی‌شود.</p>
          <p class="text-xs text-gray-500 mb-4">
            موجودی کیف پول:
            <strong>{{ auth.walletBalance.toLocaleString('fa-IR') }} تومان</strong>
            —
            هزینه ورودی:
            <strong>{{ Number(registerTournament.entry_fee).toLocaleString('fa-IR') }} تومان</strong>
          </p>
          <div class="space-y-3">
            <button
              type="button"
              class="btn-glow-success w-full rounded-lg py-3 text-sm font-bold"
              :disabled="loading"
              @click.stop.prevent="startRegistration('solo')"
            >
              🎯 رزرو تکی — انتخاب جایگاه
            </button>
            <button
              v-if="supportsTeam"
              type="button"
              class="w-full bg-secondary hover:opacity-90 text-white rounded-lg py-3 text-sm font-bold disabled:opacity-50"
              :disabled="loading"
              @click.stop.prevent="startRegistration('team')"
            >
              👥 رزرو تیمی — انتخاب تیم
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
.register-tournament-modal__panel {
  max-width: 560px;
}

.register-tournament-modal__rules {
  max-height: 45vh;
  overflow-y: auto;
}
</style>
