<script setup lang="ts">
import type { RuleSection, Tournament } from '~/types/api'

const {
  registerOpen,
  registerTournament,
  closeRegisterModal,
} = useModals()

const api = useApi()
const flash = useState<{ success?: string; error?: string; info?: string } | null>('flash')

const step = ref<'rules' | 'type' | 'team'>('rules')
const acceptRules = ref(false)
const teammateCodId = ref('')
const loading = ref(false)
const errorMessage = ref('')

const rules = ref<RuleSection[]>([])

watch(registerOpen, async (open) => {
  if (open) {
    step.value = 'rules'
    acceptRules.value = false
    teammateCodId.value = ''
    errorMessage.value = ''
    try {
      rules.value = await api.rules()
    } catch {
      rules.value = []
    }
  }
})

const supportsTeam = computed(() => (registerTournament.value?.seat_mode ?? 1) >= 2)

function close() {
  closeRegisterModal()
}

async function registerSolo() {
  const t = registerTournament.value
  if (!t) return
  loading.value = true
  errorMessage.value = ''
  try {
    const result = await api.tournaments.register(t.id, { accept_rules: '1' })
    close()
    flash.value = { info: 'برای تکمیل ثبت‌نام، جایگاه خود را انتخاب کنید.' }
    if (result?.next_step === 'select_seat') {
      await navigateTo(`/tournaments/${t.id}/select-seat`)
    } else {
      await navigateTo(`/tournaments/${t.id}`)
    }
  } catch (e: unknown) {
    const err = e as { message?: string }
    errorMessage.value = err.message || 'ثبت‌نام ناموفق بود.'
  } finally {
    loading.value = false
  }
}

async function submitTeamInvite() {
  const t = registerTournament.value
  if (!t || !teammateCodId.value.trim()) return
  loading.value = true
  errorMessage.value = ''
  try {
    await api.tournaments.teamInvite(t.id, teammateCodId.value.trim())
    close()
    flash.value = { success: 'درخواست رزرو تیمی ارسال شد.' }
    await navigateTo('/')
  } catch (e: unknown) {
    const err = e as { message?: string }
    errorMessage.value = err.message || 'ارسال درخواست تیمی ناموفق بود.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="registerOpen && registerTournament"
      class="register-tournament-modal"
      @click.self="close"
    >
      <div class="register-tournament-modal__panel">
        <h2 class="text-xl font-bold text-primary mb-2">{{ registerTournament.title }}</h2>

        <p v-if="errorMessage" class="text-sm text-danger mb-3">{{ errorMessage }}</p>

        <div v-if="step === 'rules'">
          <h3 class="font-bold text-primary mb-3">📜 تأیید خواندن قوانین</h3>
          <div class="register-tournament-modal__rules">
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

        <div v-else-if="step === 'type'">
          <h3 class="font-bold text-primary mb-3">نوع ثبت‌نام</h3>
          <p class="text-sm text-gray-400 mb-4">نحوه رزرو جایگاه خود را انتخاب کنید.</p>
          <div class="space-y-3">
            <button
              type="button"
              class="btn-glow-success w-full rounded-lg py-3 text-sm font-bold"
              :disabled="loading"
              @click="registerSolo"
            >
              🎯 رزرو تکی — انتخاب جایگاه
            </button>
            <button
              v-if="supportsTeam"
              type="button"
              class="w-full bg-secondary hover:opacity-90 text-white rounded-lg py-3 text-sm font-bold"
              @click="step = 'team'"
            >
              👥 رزرو تیمی — دعوت هم‌تیمی
            </button>
            <button type="button" class="w-full bg-gray-600 text-white rounded-lg py-2 text-sm font-bold" @click="step = 'rules'">
              بازگشت
            </button>
          </div>
        </div>

        <div v-else>
          <h3 class="font-bold text-primary mb-3">رزرو تیمی</h3>
          <p class="text-sm text-gray-400 mb-4">
            آیدی کالاف هم‌تیمی خود را وارد کنید. در صورت تأیید، هر دو در یک تیم قرار می‌گیرید.
          </p>
          <input
            v-model="teammateCodId"
            type="text"
            placeholder="آیدی کالاف هم‌تیمی"
            class="form-input w-full mb-2"
          >
          <p class="text-xs text-yellow-400 mb-4">
            برای ارسال درخواست، موجودی شما باید حداقل {{ Number(registerTournament.entry_fee).toLocaleString('fa-IR') }} تومان باشد.
          </p>
          <div class="flex gap-3">
            <button
              type="button"
              class="flex-1 bg-secondary text-white rounded-lg py-2 text-sm font-bold disabled:opacity-50"
              :disabled="loading || !teammateCodId.trim()"
              @click="submitTeamInvite"
            >
              {{ loading ? '...' : 'ارسال درخواست' }}
            </button>
            <button type="button" class="bg-gray-600 text-white rounded-lg px-4 py-2 text-sm font-bold" @click="step = 'type'">
              بازگشت
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
