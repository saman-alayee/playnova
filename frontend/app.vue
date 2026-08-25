<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const api = useApi()

const { data: settings } = await useAsyncData('site-settings', () => api.settings(), {
  server: true,
  lazy: false,
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
