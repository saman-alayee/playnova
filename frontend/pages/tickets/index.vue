<script setup lang="ts">
useHead({ title: 'سوالات متداول | PlayNova' })

const route = useRoute()
const api = useApi()

const activeCat = computed(() => (route.query.cat as string) || undefined)

const { data, pending } = await useAsyncData(
  'faq',
  () => api.faq(activeCat.value),
  { watch: [activeCat] },
)

const categories = computed(() => data.value?.categories || {})
const activeCategory = computed(() => data.value?.active_category)
const supportPhone = computed(() => data.value?.support_phone)

function scrollToFaqAnswers() {
  if (!import.meta.client) return
  nextTick(() => {
    requestAnimationFrame(() => {
      document.getElementById('faq-answers')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
  })
}

watch(activeCategory, (category) => {
  if (category) scrollToFaqAnswers()
})

watch(
  () => route.hash,
  (hash) => {
    if (hash === '#faq-answers' && activeCategory.value) scrollToFaqAnswers()
  },
)

onMounted(() => {
  if (route.hash === '#faq-answers' && activeCategory.value) scrollToFaqAnswers()
})
</script>

<template>
  <div class="max-w-4xl mx-auto">
    <div class="text-center mb-8">
      <h1 class="text-2xl md:text-3xl font-bold text-primary mb-2">سوالات متداول</h1>
      <p class="text-sm text-gray-400">یکی از دسته‌ها را انتخاب کنید تا پاسخ سوالات را ببینید.</p>
    </div>

    <div v-if="pending" class="text-center text-gray-500 py-10">در حال بارگذاری...</div>
    <template v-else>
      <div id="faq-categories" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8 scroll-mt-24">
        <NuxtLink
          v-for="(cat, key) in categories"
          :key="key"
          :to="{ path: '/tickets', query: { cat: key }, hash: '#faq-answers' }"
          class="block rounded-xl border p-4 text-center transition"
          :class="activeCat === key ? 'border-secondary bg-secondary/10 shadow-glowprimary' : 'border-dark-600 bg-dark-800 hover:border-secondary/50'"
        >
          <div class="text-3xl mb-2">{{ cat.icon }}</div>
          <div class="font-bold text-sm leading-relaxed">{{ cat.title }}</div>
          <div class="text-xs text-gray-500 mt-1">{{ cat.items.length }} سوال</div>
        </NuxtLink>
      </div>

      <div v-if="activeCategory" id="faq-answers" class="bg-dark-800 border border-dark-600 rounded-xl p-5 md:p-6 mb-8 scroll-mt-24">
        <div class="flex items-center gap-3 mb-5 pb-4 border-b border-dark-600">
          <span class="text-2xl">{{ activeCategory.icon }}</span>
          <h2 class="text-xl font-bold">{{ activeCategory.title }}</h2>
        </div>
        <div class="space-y-3">
          <details
            v-for="(item, index) in activeCategory.items"
            :key="index"
            class="group rounded-lg border border-dark-600 bg-dark-900/50 open:border-secondary/40 open:bg-dark-900"
            :open="index === 0"
          >
            <summary class="cursor-pointer list-none px-4 py-3 font-semibold text-sm text-gray-100 flex items-start justify-between gap-3">
              <span>{{ item.q }}</span>
              <span class="text-secondary text-lg leading-none shrink-0 group-open:rotate-45 transition-transform">+</span>
            </summary>
            <div class="px-4 pb-4 text-sm text-gray-300 leading-7 whitespace-pre-line border-t border-dark-700/80 pt-3">
              {{ item.a }}
            </div>
          </details>
        </div>
      </div>
      <div v-else class="bg-dark-800 border border-dashed border-dark-600 rounded-xl p-8 text-center text-gray-500 text-sm mb-8">
        برای مشاهده پاسخ‌ها، یکی از دسته‌های بالا را انتخاب کنید.
      </div>

      <div class="bg-gradient-to-l from-amber-900/20 to-dark-800 border border-amber-700/40 rounded-xl p-5 md:p-6 text-center">
        <h3 class="font-bold text-amber-200 mb-2">پاسخ سوال خود را پیدا نکردید؟</h3>
        <p class="text-sm text-gray-300 mb-3">با شماره ثابت پشتیبانی تماس بگیرید تا راهنمایی‌تان کنیم.</p>
        <a
          v-if="supportPhone"
          :href="`tel:${String(supportPhone).replace(/\s+/g, '')}`"
          dir="ltr"
          class="inline-flex items-center justify-center gap-2 bg-amber-600 hover:bg-amber-500 text-white font-bold px-6 py-3 rounded-xl text-lg"
        >
          📞 {{ supportPhone }}
        </a>
        <p v-else class="text-sm text-gray-400">
          شماره تماس به‌زودی در این بخش قرار می‌گیرد. فعلاً از
          <NuxtLink to="/contact" class="text-secondary underline">ارتباط با ما</NuxtLink>
          استفاده کنید.
        </p>
      </div>
    </template>
  </div>
</template>
