<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const model = defineModel<string>({ default: '' })
const visible = ref(false)

const toggleLabel = computed(() => (visible.value ? 'مخفی کردن رمز عبور' : 'نمایش رمز عبور'))
</script>

<template>
  <div class="password-input">
    <input
      v-model="model"
      v-bind="$attrs"
      :type="visible ? 'text' : 'password'"
      class="password-input__field"
    >
    <button
      type="button"
      class="password-input__toggle"
      :aria-label="toggleLabel"
      :title="toggleLabel"
      tabindex="-1"
      @click="visible = !visible"
    >
      <svg
        v-if="visible"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M3 3l18 18" />
        <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
        <path d="M9.5 5.2A10.7 10.7 0 0 1 12 5c6 0 10 7 10 7a17.3 17.3 0 0 1-3.2 3.9" />
        <path d="M6.7 6.7C4.2 8.5 2.5 11.2 2 12s4 7 10 7c1.4 0 2.7-.2 3.9-.7" />
      </svg>
      <svg
        v-else
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" />
        <circle cx="12" cy="12" r="3" />
      </svg>
    </button>
  </div>
</template>
