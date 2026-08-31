<script setup lang="ts">
import type { Tournament } from '~/types/api'

useHead({ title: 'تاریخچه مسابقات | PlayNova' })
definePageMeta({ keepalive: true })

const api = useApi()
const auth = useAuthStore()
const { publicAssetUrl } = useMediaUrl()

const { data, pending, error } = await useAsyncData('history', () => api.history(), {
  default: () => [] as Tournament[],
})

const tournaments = computed(() => (data.value || []) as Tournament[])
const resultChannels = computed(() =>
  (auth.settings?.results_channels || []).filter((item) => item.url),
)

function formatNumber(value?: number | null) {
  return Number(value || 0).toLocaleString('fa-IR')
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-center text-white">🏆 تاریخچه مسابقات پایان‌یافته</h1>

    <div
      v-if="resultChannels.length"
      class="mb-6 p-4 bg-dark-800 border border-dark-600 rounded-xl text-sm text-gray-300"
    >
      <p class="text-center mb-3">نتایج بازی در شبکه‌های اجتماعی نمایش داده می‌شود.</p>
      <div class="sidebar-social__grid">
        <a
          v-for="(item, index) in resultChannels"
          :key="`${item.title}-${index}`"
          :href="item.url || '#'"
          target="_blank"
          rel="noopener noreferrer"
          class="sidebar-social__link"
          :title="item.title"
        >
          <span class="sidebar-social__icon">
            <img
              v-if="item.icon"
              :src="publicAssetUrl(item.icon) || item.icon"
              :alt="item.title || ''"
              width="36"
              height="36"
              loading="lazy"
            >
          </span>
        </a>
      </div>
    </div>

    <div v-if="pending" class="text-center py-10 text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-center text-gray-400">
      بارگذاری تاریخچه ممکن نشد.
    </div>
    <div v-else-if="!tournaments.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      مسابقه‌ای در تاریخچه ثبت نشده است.
    </div>
    <div v-else class="space-y-6">
      <article
        v-for="tournament in tournaments"
        :key="tournament.id"
        class="bg-dark-800 border border-dark-600 rounded-xl p-6 card-tournament"
      >
        <div class="flex flex-wrap justify-between items-start gap-4 mb-4">
          <div>
            <h2 class="text-xl font-bold text-primary">{{ tournament.title }}</h2>
            <p class="text-sm text-gray-400">{{ tournament.game || 'Call of Duty Mobile' }}</p>
          </div>
          <span class="text-xs bg-success/20 text-success px-3 py-1 rounded-full">
            {{ tournament.status_label || 'پایان‌یافته' }}
          </span>
        </div>

        <div class="text-sm space-y-2 mb-4">
          <div class="flex justify-between gap-4">
            <span class="text-gray-400">ورودی:</span>
            <span class="font-bold">{{ formatNumber(tournament.entry_fee) }} تومان</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-gray-400">جایزه:</span>
            <span class="font-bold text-secondary">{{ formatNumber(tournament.prize_pool) }} تومان</span>
          </div>
          <p v-if="tournament.winner?.username" class="text-gray-300 pt-1">
            برنده: <span class="font-bold text-white">{{ tournament.winner.username }}</span>
          </p>
        </div>

        <p class="text-sm text-gray-400 leading-7">
          جوایز این مسابقه به کیف پول برندگان واریز شده است.
        </p>
      </article>
    </div>
  </div>
</template>
