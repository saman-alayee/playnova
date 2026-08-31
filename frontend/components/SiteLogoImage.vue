<script setup lang="ts">
withDefaults(
  defineProps<{
    imgClass?: string
    width?: number
    height?: number
    alt?: string
  }>(),
  {
    imgClass: 'site-logo',
    width: 160,
    height: 56,
    alt: 'PlayNova',
  },
)

const { logoSrc, logoFailed, onLogoError } = useSiteLogo()
</script>

<template>
  <img
    v-if="logoSrc && !logoFailed"
    :key="logoSrc"
    :src="logoSrc"
    :class="imgClass"
    :alt="alt"
    :width="width"
    :height="height"
    decoding="async"
    @error="onLogoError"
  >
  <span v-else class="text-lg font-black text-gradient whitespace-nowrap">{{ alt }}</span>
</template>
