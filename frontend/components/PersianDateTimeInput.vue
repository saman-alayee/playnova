<script setup lang="ts">
import DatePicker from '@alireza-ab/vue3-persian-datepicker'
import {
  apiDateTimeToPickerValue,
  formatJalaliLabel,
  isoToJalaliParts,
  pickerValueToApiDateTime,
} from '~/utils/jalali'

const props = defineProps<{
  modelValue?: string
  required?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const pickerValue = ref('')
const syncing = ref(false)

function syncFromModel(value?: string) {
  syncing.value = true
  pickerValue.value = apiDateTimeToPickerValue(value)
  syncing.value = false
}

watch(
  () => props.modelValue,
  (value) => syncFromModel(value),
  { immediate: true },
)

watch(pickerValue, (value) => {
  if (syncing.value) return

  const apiValue = pickerValueToApiDateTime(value)
  if (apiValue !== props.modelValue) {
    emit('update:modelValue', apiValue)
  }
})

const preview = computed(() => {
  const parts = isoToJalaliParts(props.modelValue)
  return parts ? formatJalaliLabel(parts) : '—'
})
</script>

<template>
  <div class="persian-datetime-field">
    <ClientOnly>
      <DatePicker
        v-model="pickerValue"
        type="datetime"
        mode="single"
        locale="fa"
        format="jYYYY/jMM/jDD HH:mm"
        input-format="jYYYY/jMM/jDD HH:mm"
        display-format="?D ?MMMM ?YYYY — HH:mm"
        :clearable="!required"
        :shortcut="true"
        icon-inside
        modal
        auto-submit
        class="persian-datetime-input"
      />
      <template #fallback>
        <div class="persian-datetime-field__fallback">
          {{ preview }}
        </div>
      </template>
    </ClientOnly>

    <input
      type="hidden"
      :value="modelValue"
      :required="required"
    >

    <p class="persian-datetime-field__hint">
      تاریخ شمسی (تهران):
      <span class="persian-datetime-field__preview">{{ preview }}</span>
    </p>
  </div>
</template>

<style scoped>
.persian-datetime-field__fallback,
.persian-datetime-field__hint {
  font-size: 0.75rem;
  color: #9ca3af;
}

.persian-datetime-field__fallback {
  padding: 0.55rem 0.75rem;
  border: 1px solid #4b5563;
  border-radius: 0.5rem;
  background: #374151;
  color: #e5e7eb;
}

.persian-datetime-field__hint {
  margin: 0.45rem 0 0;
}

.persian-datetime-field__preview {
  color: #d4af37;
  font-weight: 700;
}

:deep(.persian-datetime-input.pdp) {
  --primary-color: #d4af37;
  --secondary-color: rgba(197, 160, 89, 0.28);
  --in-range-background: rgba(197, 160, 89, 0.14);
  --text-color: #e5e7eb;
  --hover-color: #fff;
  --background: #111827;
  --border-color: #4b5563;
  --icon-background: #1f2937;
  --main-box-shadow: 0 12px 40px rgba(0, 0, 0, 0.55);
  --radius: 0.5rem;
  --z-index: 10050;
  width: 100%;
}

:deep(.persian-datetime-input .pdp-input) {
  width: 100%;
  color: #fff;
  background: #374151;
  border-color: #4b5563;
  font-size: 0.9rem;
}

:deep(.persian-datetime-input .pdp-input::placeholder) {
  color: #9ca3af;
}

:deep(.persian-datetime-input .pdp-input.pdp-focus) {
  border-bottom-color: #d4af37;
  box-shadow: 0 0 0 1px rgba(212, 175, 55, 0.25);
}

:deep(.persian-datetime-input .pdp-icon) {
  color: #d4af37;
  background: #1f2937;
  border-color: #4b5563;
}

:deep(.persian-datetime-input .pdp-picker) {
  color: #e5e7eb;
}

:deep(.persian-datetime-input .pdp-picker .pdp-header .top button),
:deep(.persian-datetime-input .pdp-picker .pdp-header .bottom .pdp-month),
:deep(.persian-datetime-input .pdp-picker .pdp-header .bottom .pdp-year) {
  color: #d4af37;
}

:deep(.persian-datetime-input .pdp-picker .pdp-day.friday) {
  color: #fbbf24;
}

:deep(.persian-datetime-input .pdp-picker .pdp-day.start-range),
:deep(.persian-datetime-input .pdp-picker .pdp-footer .pdp-submit),
:deep(.persian-datetime-input .pdp-picker .pdp-footer .pdp-today) {
  color: #111827;
}

:deep(.persian-datetime-input .pdp-picker .pdp-shortcut li.selected) {
  color: #111827;
}
</style>
