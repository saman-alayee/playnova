<script setup lang="ts">
type WalletAction = 'add' | 'subtract' | 'set'

const props = defineProps<{
  busy?: boolean
  apply: (payload: {
    action: WalletAction
    amount: number
    description?: string
    allowNegative: boolean
  }) => Promise<void>
}>()

const action = ref<WalletAction>('add')
const amount = ref('')
const description = ref('')
const allowNegative = ref(false)
const localError = ref('')

function reset() {
  action.value = 'add'
  amount.value = ''
  description.value = ''
  allowNegative.value = false
  localError.value = ''
}

function parseAmount(value: unknown): number | null {
  const raw = String(value ?? '')
    .trim()
    .replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
    .replace(/,/g, '')

  if (!raw) return null

  const parsed = Number(raw)
  if (!Number.isFinite(parsed) || parsed < 0) return null

  return parsed
}

async function onApply() {
  localError.value = ''
  const parsed = parseAmount(amount.value)

  if (parsed === null) {
    localError.value = 'مبلغ معتبر وارد کنید.'
    return
  }

  try {
    await props.apply({
      action: action.value,
      amount: parsed,
      description: description.value.trim() || undefined,
      allowNegative: allowNegative.value,
    })
    reset()
  } catch (e: unknown) {
    localError.value = (e as Error).message || 'اعمال تغییر کیف پول ناموفق بود.'
  }
}
</script>

<template>
  <form class="wallet-form" autocomplete="off" novalidate @submit.prevent="onApply">
    <span class="wallet-form__label">تنظیم کیف پول:</span>
    <select v-model="action" class="wallet-form__select">
      <option value="add">+ افزایش</option>
      <option value="subtract">− کاهش</option>
      <option value="set">= تنظیم</option>
    </select>
    <span class="wallet-form__label wallet-form__label--sub">مبلغ:</span>
    <input
      v-model="amount"
      type="text"
      inputmode="decimal"
      placeholder="مبلغ"
      autocomplete="off"
      class="wallet-form__input wallet-form__input--amount"
    >
    <span class="wallet-form__label wallet-form__label--sub">توضیح:</span>
    <input
      v-model="description"
      type="text"
      placeholder="توضیح"
      autocomplete="off"
      class="wallet-form__input wallet-form__input--desc"
    >
    <label class="wallet-form__checkbox">
      <input v-model="allowNegative" type="checkbox" class="accent-primary">
      اجازه منفی
    </label>
    <button
      type="submit"
      class="wallet-form__btn"
      :disabled="busy"
    >
      {{ busy ? '...' : 'اعمال' }}
    </button>
    <p v-if="localError" class="wallet-form__error">{{ localError }}</p>
  </form>
</template>

<style scoped>
.wallet-form {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem 0.45rem;
  width: 100%;
  row-gap: 0.45rem;
}

.wallet-form__label {
  flex-shrink: 0;
  font-size: 0.72rem;
  color: #9ca3af;
  font-weight: 700;
  white-space: nowrap;
}

.wallet-form__label--sub {
  font-weight: 600;
  color: #6b7280;
}

.wallet-form__select,
.wallet-form__input {
  border: 1px solid rgba(75, 85, 99, 0.8);
  border-radius: 0.45rem;
  background: #111827;
  color: #fff;
  padding: 0.4rem 0.5rem;
  font-size: 0.78rem;
}

.wallet-form__select {
  width: auto;
  min-width: 6.5rem;
}

.wallet-form__input--amount {
  width: 7rem;
}

.wallet-form__input--desc {
  flex: 1 1 8rem;
  min-width: 6rem;
}

.wallet-form__checkbox {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.72rem;
  color: #9ca3af;
}

.wallet-form__btn {
  border: none;
  border-radius: 0.45rem;
  background: #166534;
  color: #dcfce7;
  padding: 0.38rem 0.65rem;
  font-size: 0.74rem;
  font-weight: 700;
  cursor: pointer;
}

.wallet-form__btn:disabled {
  opacity: 0.65;
  cursor: wait;
}

.wallet-form__error {
  flex: 1 1 100%;
  margin: 0;
  font-size: 0.75rem;
  color: #fca5a5;
}
</style>
