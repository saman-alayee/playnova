<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })

await navigateTo('/admin/site-settings', { redirectCode: 301, replace: true })
</script>

<template>
  <div />
</template>
