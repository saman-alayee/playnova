<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const route = useRoute()
const api = useApi()
const flash = useState('flash')

const id = computed(() => route.params.id as string)
const selectedSeat = ref<number | null>(null)
const loading = ref(false)
const errors = ref<string[]>([])

const { data, pending, error } = await useAsyncData(
  () => `select-seat-${id.value}`,
  () => api.tournaments.selectSeat(id.value),
)

useHead(() => ({ title: `انتخاب جایگاه | ${data.value?.tournament?.title || 'مسابقه'}` }))

const takenSeats = computed(() => new Set(data.value?.taken_seats || []))
const capacity = computed(() => data.value?.tournament?.capacity || 100)

const seats = computed(() => {
  const list: number[] = []
  for (let i = 1; i <= capacity.value; i++) list.push(i)
  return list
})

async function submit() {
  if (!selectedSeat.value) {
    errors.value = ['یک جایگاه انتخاب کنید.']
    return
  }
  loading.value = true
  errors.value = []
  try {
    await api.tournaments.storeSeat(id.value, selectedSeat.value)
    flash.value = { success: 'جایگاه با موفقیت ثبت شد.' }
    await navigateTo(`/tournaments/${id.value}`)
  } catch (e: unknown) {
    const err = e as Error
    errors.value = [err.message || 'ثبت جایگاه ناموفق بود.']
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <div v-if="pending" class="text-center py-10 text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error || !data" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      امکان انتخاب جایگاه وجود ندارد.
    </div>
    <template v-else>
      <NuxtLink :to="`/tournaments/${id}`" class="text-sm text-secondary mb-4 inline-block">← بازگشت</NuxtLink>
      <h1 class="text-2xl font-bold mb-2 text-white">انتخاب جایگاه</h1>
      <p class="text-sm text-gray-400 mb-6">{{ data.tournament.title }}</p>

      <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
        </ul>
      </div>

      <div class="grid grid-cols-5 sm:grid-cols-8 md:grid-cols-10 gap-2 mb-6">
        <button
          v-for="seat in seats"
          :key="seat"
          type="button"
          class="aspect-square rounded-lg text-sm font-bold border transition"
          :class="[
            takenSeats.has(seat)
              ? 'bg-gray-800 border-gray-700 text-gray-600 cursor-not-allowed'
              : selectedSeat === seat
                ? 'bg-success border-success text-white'
                : 'bg-dark-800 border-dark-600 text-white hover:border-secondary',
          ]"
          :disabled="takenSeats.has(seat)"
          @click="selectedSeat = seat"
        >
          {{ seat }}
        </button>
      </div>

      <button
        type="button"
        class="btn-glow-primary rounded-lg px-6 py-2"
        :disabled="loading || !selectedSeat"
        @click="submit"
      >
        {{ loading ? '...' : 'تأیید جایگاه' }}
      </button>
    </template>
  </div>
</template>
