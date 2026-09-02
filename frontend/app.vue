<template>
  <NuxtLoadingIndicator color="#8B5CF6" :height="3" :duration="2000" :throttle="0" />
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const api = useApi()

const { data: settings } = useAsyncData('site-settings', () => api.settings(), {
  server: true,
  lazy: true,
})

if (settings.value) {
  auth.setSettings(settings.value)
}

watch(settings, (value) => {
  if (value) auth.setSettings(value)
})

useHead({
  htmlAttrs: { lang: 'fa', dir: 'rtl' },
})
</script>
