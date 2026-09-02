<script setup lang="ts">
import type { HomeData } from '~/types/api'

definePageMeta({ layout: 'default', keepalive: true })

useHead({ title: 'PlayNova | پلتفرم مسابقات آنلاین Call of Duty Mobile' })

const config = useRuntimeConfig()
const api = useApi()
const auth = useAuthStore()

const heroSlides = [
  `${config.public.backendUrl}/hero-slide-1.png`,
  `${config.public.backendUrl}/hero-slide-2.png`,
  `${config.public.backendUrl}/hero-slide-3.png`,
]

const leagueMeta = {
  beginner: { title: 'مبتدی', subtitle: 'مناسب برای تازه‌کارها', class: 'league-card--beginner', icon: 'beginner' as const },
  intermediate: { title: 'متوسط', subtitle: 'برای بازیکنان با تجربه', class: 'league-card--intermediate', icon: 'intermediate' as const },
  professional: { title: 'حرفه‌ای', subtitle: 'برای حرفه‌ای‌های واقعی', class: 'league-card--professional', icon: 'professional' as const },
}

const activeSlide = ref(0)
const failedSlides = ref<Record<number, boolean>>({})
let slideTimer: ReturnType<typeof setInterval> | null = null

const { data, error, pending, refresh } = usePageData('home', () => api.home(), {
  default: () =>
    ({
      active_tournaments: [],
      leagues: { beginner: [], intermediate: [], professional: [] },
    }) as HomeData,
})

const activeTournaments = computed(() => data.value?.active_tournaments || [])
const leagues = computed(() => data.value?.leagues || { beginner: [], intermediate: [], professional: [] })

function shouldLoadSlide(index: number) {
  return !failedSlides.value[index] && (index === 0 || Math.abs(activeSlide.value - index) <= 1)
}

function onSlideError(index: number) {
  failedSlides.value = { ...failedSlides.value, [index]: true }
}

onMounted(() => {
  if (auth.isAuthenticated) {
    void refresh()
  }
  slideTimer = setInterval(() => {
    activeSlide.value = (activeSlide.value + 1) % heroSlides.length
  }, 5000)
})

onActivated(() => {
  if (auth.isAuthenticated) {
    void refresh()
  }
})

onUnmounted(() => {
  if (slideTimer) clearInterval(slideTimer)
})

watch(() => auth.isAuthenticated, (loggedIn) => {
  if (loggedIn) void refresh()
})
</script>

<template>
  <div>
    <section class="hero-carousel">
      <div
        v-for="(img, i) in heroSlides"
        :key="img"
        class="hero-slide"
        :class="{ 'is-active': activeSlide === i }"
      >
        <img
          v-if="shouldLoadSlide(i)"
          :src="img"
          alt=""
          class="hero-slide__img"
          width="1200"
          height="420"
          :fetchpriority="i === 0 ? 'high' : 'low'"
          :loading="i === 0 ? 'eager' : 'lazy'"
          decoding="async"
          @error="onSlideError(i)"
        >
        <div class="hero-content">
          <a href="#special" class="btn-glow-primary hero-cta">مشاهده مسابقات</a>
        </div>
      </div>
      <div class="hero-dots">
        <button
          v-for="(_, i) in heroSlides"
          :key="i"
          type="button"
          class="hero-dot"
          :class="{ 'is-active': activeSlide === i }"
          :aria-label="`اسلاید ${i + 1}`"
          @click="activeSlide = i"
        />
      </div>
    </section>

    <section class="mb-8">
      <h2 class="text-lg font-bold mb-4 text-white">دسته‌بندی مسابقات</h2>
      <div class="space-y-3">
        <div
          v-for="(meta, key) in leagueMeta"
          :key="key"
          class="league-card rounded-2xl p-4 flex items-center justify-between gap-3"
          :class="meta.class"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="league-card__shield">
              <LeagueIcon :level="meta.icon" />
            </div>
            <div class="min-w-0">
              <h3 class="font-bold text-base text-white">{{ meta.title }}</h3>
              <p class="text-xs text-gray-400">{{ meta.subtitle }}</p>
            </div>
          </div>
          <a :href="`#league-${key}`" class="league-card__btn shrink-0 text-xs font-bold px-3 py-2 rounded-xl border transition">
            مشاهده مسابقات
          </a>
        </div>
      </div>
    </section>

    <section id="special" class="mb-8 scroll-mt-24">
      <h2 class="text-lg font-bold mb-4 text-white">مسابقات ویژه</h2>
    <div v-if="pending && !activeTournaments.length">
      <PageLoading />
    </div>
      <div v-else-if="error && !activeTournaments.length" class="text-center py-10 bg-dark-800/50 rounded-2xl border border-dark-600">
        <p class="text-gray-500">بارگذاری مسابقات ممکن نشد.</p>
      </div>
      <div v-else-if="!activeTournaments.length" class="text-center py-10 bg-dark-800/50 rounded-2xl border border-dark-600">
        <p class="text-gray-500">در حال حاضر مسابقه فعالی وجود ندارد.</p>
      </div>
      <div v-else class="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory special-scroll">
        <SpecialTournamentCard
          v-for="(t, idx) in activeTournaments"
          :key="t.id"
          :tournament="t"
          :hero-image="heroSlides[idx % heroSlides.length]"
        />
      </div>
    </section>

    <section
      v-for="(meta, key) in leagueMeta"
      :id="`league-${key}`"
      :key="`league-section-${key}`"
      class="mb-8 scroll-mt-24"
    >
      <template v-if="(leagues[key] || []).length">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold text-white">مسابقات {{ meta.title }}</h2>
          <span class="text-xs text-gray-500">{{ leagues[key].length }} مسابقه</span>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <TournamentCard
            v-for="t in leagues[key]"
            :key="t.id"
            :tournament="t"
          />
        </div>
      </template>
    </section>
  </div>
</template>
