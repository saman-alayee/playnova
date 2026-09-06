<script setup lang="ts">
definePageMeta({
  layout: false,
})

const route = useRoute()

const trackId = computed(() => String(route.params.trackId ?? '').replace(/\D+/g, ''))
const gatewayUrl = computed(() => `https://gateway.zibal.ir/start/${trackId.value}`)

useHead({
  title: 'انتقال به درگاه | PlayNova',
  meta: [{ name: 'referrer', content: 'origin' }],
})

function leaveToGateway() {
  if (!import.meta.client) {
    return
  }

  if (!trackId.value) {
    window.location.replace('/wallet')
    return
  }

  window.location.replace(gatewayUrl.value)
}

onMounted(() => {
  leaveToGateway()
})
</script>

<template>
  <div class="min-h-screen bg-dark-900 flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center py-16">
      <p class="text-gray-300 mb-2">در حال انتقال به درگاه پرداخت زیبال...</p>
      <p class="text-xs text-gray-500 mb-6">لطفاً صبر کنید.</p>
      <a :href="gatewayUrl" class="text-secondary text-sm underline hover:no-underline" rel="origin">
        اگر به‌صورت خودکار منتقل نشدید، اینجا کلیک کنید
      </a>
    </div>
  </div>
</template>
