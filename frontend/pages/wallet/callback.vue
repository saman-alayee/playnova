<script setup lang="ts">
definePageMeta({ layout: 'default' })

useHead({ title: 'بازگشت از درگاه | PlayNova' })

const route = useRoute()
const api = useApi()
const auth = useAuthStore()
const flash = useState<{ success?: string; error?: string; info?: string } | null>('flash')

const status = ref<'loading' | 'done' | 'error'>('loading')
const message = ref('در حال تأیید پرداخت...')

onMounted(async () => {
  if (!auth.isAuthenticated) {
    await auth.fetchUser()
  }

  const query: Record<string, string> = {}
  for (const [key, value] of Object.entries(route.query)) {
    if (typeof value === 'string') query[key] = value
  }

  try {
    const result = await api.wallet.processCallback(query)
    message.value = result?.message || 'پرداخت با موفقیت انجام شد.'
    flash.value = { success: message.value }
    status.value = 'done'
    await auth.fetchUser()
    await navigateTo('/wallet')
  } catch (e: unknown) {
    const err = e as { message?: string }
    message.value = err.message || 'پرداخت ناموفق بود.'
    flash.value = { error: message.value }
    status.value = 'error'
    setTimeout(() => navigateTo('/wallet'), 2500)
  }
})
</script>

<template>
  <div class="max-w-md mx-auto text-center py-16">
    <div v-if="status === 'loading'" class="text-gray-400">{{ message }}</div>
    <div v-else-if="status === 'done'" class="text-success">{{ message }}</div>
    <div v-else class="text-red-400">{{ message }}</div>
  </div>
</template>
