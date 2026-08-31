<script setup lang="ts">
defineEmits<{ 'open-sidebar': [] }>()

const route = useRoute()
const auth = useAuthStore()
const { mediaUrl, publicAssetUrl } = useMediaUrl()
const { formatToman } = useFormatToman()

const logoFailed = ref(false)
const logoIndex = ref(0)

const logoCandidates = computed(() => {
  const candidates = [
    publicAssetUrl('/playnova-logo.png'),
    publicAssetUrl('/logo.png'),
    mediaUrl(auth.logoUrl),
  ].filter((url): url is string => !!url)

  return [...new Set(candidates)]
})

const logoSrc = computed(() => logoCandidates.value[logoIndex.value] ?? null)

function onLogoError() {
  if (logoIndex.value < logoCandidates.value.length - 1) {
    logoIndex.value += 1
    return
  }
  logoFailed.value = true
}

watch(logoCandidates, () => {
  logoIndex.value = 0
  logoFailed.value = false
})

function isActive(path: string) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}
</script>

<template>
  <header class="site-header">
    <div class="container mx-auto px-4 py-3 max-w-7xl">
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0 shrink-0">
          <button
            type="button"
            class="hamburger-btn"
            aria-label="منو"
            @click="$emit('open-sidebar')"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <NuxtLink to="/" class="site-header-logo">
            <img
              v-if="logoSrc && !logoFailed"
              :key="logoSrc"
              :src="logoSrc"
              class="site-logo"
              alt="PlayNova"
              width="160"
              height="56"
              decoding="async"
              @error="onLogoError"
            >
            <span v-else class="text-lg font-black text-gradient whitespace-nowrap">PlayNova</span>
          </NuxtLink>
        </div>

        <nav class="desktop-nav" aria-label="منوی اصلی">
          <NuxtLink to="/" :class="{ 'is-active': isActive('/') && route.path === '/' }">خانه</NuxtLink>
          <NuxtLink to="/rules" :class="{ 'is-active': isActive('/rules') }">قوانین</NuxtLink>
          <NuxtLink to="/leaderboard" :class="{ 'is-active': isActive('/leaderboard') }">رتبه‌بندی</NuxtLink>
          <NuxtLink to="/history" :class="{ 'is-active': isActive('/history') }">تاریخچه</NuxtLink>
          <template v-if="auth.isAuthenticated">
            <NuxtLink to="/profile" :class="{ 'is-active': isActive('/profile') }">پروفایل</NuxtLink>
            <NuxtLink to="/tickets" :class="{ 'is-active': isActive('/tickets') }">سوالات متداول</NuxtLink>
            <NuxtLink v-if="auth.isAdmin" to="/admin" :class="{ 'is-active': isActive('/admin') }">
              پنل مدیریت
            </NuxtLink>
          </template>
        </nav>

        <div class="header-actions">
          <NuxtLink to="/wallet" class="btn-header-wallet">💼 کیف پول</NuxtLink>
          <template v-if="auth.isAuthenticated">
            <span class="show-desktop-only text-xs text-success font-bold">
              {{ formatToman(auth.walletBalance, false) }}
            </span>
            <span class="show-desktop-only text-sm text-gray-300 max-w-[120px] truncate">
              {{ auth.displayName }}
            </span>
            <button
              type="button"
              class="btn-header-logout"
              @click="auth.logout()"
            >
              خروج
            </button>
          </template>
          <template v-else>
            <NuxtLink to="/login" class="btn-header-login">ورود</NuxtLink>
            <NuxtLink to="/register" class="btn-header-register">ثبت‌نام</NuxtLink>
          </template>
        </div>
      </div>
    </div>
  </header>
</template>
