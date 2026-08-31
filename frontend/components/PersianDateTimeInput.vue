<script setup lang="ts">
import {
  formatJalaliLabel,
  isoToJalaliParts,
  jalaliPartsToApiDateTime,
  type JalaliDateTimeParts,
} from '~/utils/jalali'

const props = defineProps<{
  modelValue?: string
  required?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const parts = reactive<JalaliDateTimeParts>({
  jy: 1404,
  jm: 1,
  jd: 1,
  hour: 12,
  minute: 0,
})

function syncFromModel(value?: string) {
  const parsed = isoToJalaliParts(value)
  if (!parsed) return
  Object.assign(parts, parsed)
}

watch(
  () => props.modelValue,
  (value) => syncFromModel(value),
  { immediate: true },
)

function emitValue() {
  emit('update:modelValue', jalaliPartsToApiDateTime(parts))
}

watch(parts, emitValue, { deep: true })

const preview = computed(() => formatJalaliLabel(parts))
</script>

<template>
  <div class="space-y-2">
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
      <input
        v-model.number="parts.jy"
        type="number"
        min="1300"
        max="1500"
        :required="required"
        class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        placeholder="سال"
        @input="emitValue"
      >
      <input
        v-model.number="parts.jm"
        type="number"
        min="1"
        max="12"
        :required="required"
        class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        placeholder="ماه"
        @input="emitValue"
      >
      <input
        v-model.number="parts.jd"
        type="number"
        min="1"
        max="31"
        :required="required"
        class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        placeholder="روز"
        @input="emitValue"
      >
      <input
        v-model.number="parts.hour"
        type="number"
        min="0"
        max="23"
        :required="required"
        class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        placeholder="ساعت"
        @input="emitValue"
      >
      <input
        v-model.number="parts.minute"
        type="number"
        min="0"
        max="59"
        :required="required"
        class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
        placeholder="دقیقه"
        @input="emitValue"
      >
    </div>
    <p class="text-xs text-gray-400">
      تاریخ شمسی (تهران): <span class="text-secondary font-bold">{{ preview }}</span>
    </p>
  </div>
</template>
