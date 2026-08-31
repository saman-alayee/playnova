<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const route = useRoute()

const trackId = computed(() => String(route.params.trackId ?? '').replace(/\D+/g, ''))
const gatewayUrl = computed(() => `https://gateway.zibal.ir/start/${trackId.value}`)
const formRef = ref<HTMLFormElement | null>(null)

useHead({
  title: 'انتقال به درگاه | PlayNova',
  meta: [{ name: 'referrer', content: 'origin' }],
})

onMounted(() => {
  if (!trackId.value) {
    navigateTo('/wallet')
    return
  }

  // Form navigation to Zibal /start sends Referer from the registered site domain.
  formRef.value?.submit()
})
</script>

<template>
  <div class="max-w-md mx-auto text-center py-16 px-4">
    <p class="text-gray-300 mb-2">در حال انتقال به درگاه پرداخت زیبال...</p>
    <p class="text-xs text-gray-500 mb-6">لطفاً صبر کنید.</p>

    <form ref="formRef" method="GET" :action="gatewayUrl">
      <button type="submit" class="text-secondary text-sm underline hover:no-underline">
        اگر به‌صورت خودکار منتقل نشدید، اینجا کلیک کنید
      </button>
    </form>
  </div>
</template>
