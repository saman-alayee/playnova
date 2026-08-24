<script setup lang="ts">
withDefaults(defineProps<{ level?: 'beginner' | 'intermediate' | 'professional' }>(), {
  level: 'beginner',
})
</script>

<template>
  <svg
    v-if="level === 'beginner'"
    width="32"
    height="32"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    stroke-linecap="round"
    aria-hidden="true"
  >
    <circle cx="12" cy="12" r="8" />
    <circle cx="12" cy="12" r="4" />
    <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none" />
    <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
  </svg>
  <svg
    v-else-if="level === 'intermediate'"
    width="32"
    height="32"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <circle cx="12" cy="8.5" r="5.5" />
    <path d="M8.2 13.2L6 21l6-2.8L18 21l-2.2-7.8" />
    <path d="M9.5 8.5h5M12 6v5" />
  </svg>
  <svg
    v-else
    width="32"
    height="32"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path d="M3 17h18" />
    <path d="M5 17l1.5-9 4.5 3.5L12 5l1 6.5L17.5 8 19 17" />
    <path d="M3 17v2h18v-2" />
    <circle cx="12" cy="5" r="1" fill="currentColor" stroke="none" />
  </svg>
</template>
