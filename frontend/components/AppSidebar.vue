<script setup lang="ts">
defineProps<{ open: boolean }>()
defineEmits<{ close: [] }>()

const route = useRoute()
const auth = useAuthStore()

const menuReady = computed(() => auth.initialized)

const socialItems = computed(() => {
  const social = auth.settings?.social || {}
  return [
    {
      key: 'instagram',
      icon: '/social-instagram.svg',
      title: 'اینستاگرام',
      url: buildSocialUrl(social.instagram, 'https://instagram.com/'),
    },
    {
      key: 'rubika',
      icon: '/social-rubika.png',
      title: 'روبیکا',
      url: buildSocialUrl(social.rubika, 'https://rubika.ir/'),
    },
    {
      key: 'telegram',
      icon: '/social-telegram.svg',
      title: 'تلگرام',
      url: buildSocialUrl(social.telegram, 'https://t.me/'),
    },
  ]
})

function buildSocialUrl(value: string | null | undefined, prefix: string) {
  if (!value) return null
  if (value.startsWith('http')) return value
  return prefix + value.replace(/^@/, '')
}

function isActive(path: string) {
  return route.path.startsWith(path)
}
</script>

<template>
  <div>
    <Transition name="fade">
      <div v-if="open" class="sidebar-overlay" @click="$emit('close')" />
    </Transition>

    <aside class="sidebar-panel" :class="{ open }" @click.stop>
      <div class="sidebar-top">
        <span class="sidebar-top__title">منو</span>
        <button type="button" class="sidebar-close" aria-label="بستن منو" @click="$emit('close')">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-width="2" d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
      </div>

      <nav class="sidebar-menu">
        <template v-if="menuReady && auth.isAuthenticated">
          <NuxtLink to="/profile" class="sidebar-item" @click="$emit('close')">
            <span class="sidebar-item__left">
              <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <span>پروفایل</span>
            </span>
            <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </NuxtLink>
          <NuxtLink to="/wallet" class="sidebar-item" @click="$emit('close')">
            <span class="sidebar-item__left">
              <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
              <span>کیف پول من</span>
            </span>
            <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </NuxtLink>
          <NuxtLink to="/#special" class="sidebar-item" @click="$emit('close')">
            <span class="sidebar-item__left">
              <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
              </svg>
              <span>مسابقات من</span>
            </span>
            <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </NuxtLink>
          <div class="sidebar-divider" />
        </template>

        <NuxtLink
          to="/notifications"
          class="sidebar-item"
          :class="{ 'is-active': isActive('/notifications') }"
          @click="$emit('close')"
        >
          <span class="sidebar-item__left">
            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span>اعلانات</span>
          </span>
          <span class="flex items-center gap-2">
            <span v-if="auth.unreadNotifications > 0" class="sidebar-item__badge">
              {{ auth.unreadNotifications > 99 ? '99+' : auth.unreadNotifications }}
            </span>
            <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </span>
        </NuxtLink>

        <NuxtLink to="/rules" class="sidebar-item" @click="$emit('close')">
          <span class="sidebar-item__left">
            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>قوانین</span>
          </span>
          <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </NuxtLink>

        <NuxtLink to="/leaderboard" class="sidebar-item" @click="$emit('close')">
          <span class="sidebar-item__left">
            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
            <span>رتبه‌بندی</span>
          </span>
          <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </NuxtLink>

        <NuxtLink to="/history" class="sidebar-item" @click="$emit('close')">
          <span class="sidebar-item__left">
            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>تاریخچه مسابقات</span>
          </span>
          <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </NuxtLink>

        <NuxtLink to="/about" class="sidebar-item" @click="$emit('close')">
          <span class="sidebar-item__left">
            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>درباره ما</span>
          </span>
          <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </NuxtLink>

        <NuxtLink to="/privacy" class="sidebar-item" @click="$emit('close')">
          <span class="sidebar-item__left">
            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="1.8" d="M12 11c1.657 0 3-1.343 3-3S13.657 5 12 5 9 6.343 9 8s1.343 3 3 3z" />
              <path stroke-linecap="round" stroke-width="1.8" d="M5 20a7 7 0 0114 0" />
            </svg>
            <span>حریم خصوصی</span>
          </span>
          <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </NuxtLink>

        <NuxtLink to="/contact" class="sidebar-item" @click="$emit('close')">
          <span class="sidebar-item__left">
            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <span>ارتباط با ما</span>
          </span>
          <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </NuxtLink>

        <NuxtLink to="/tickets" class="sidebar-item" :class="{ 'is-active': isActive('/tickets') }" @click="$emit('close')">
          <span class="sidebar-item__left">
            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
            </svg>
            <span>سوالات متداول</span>
          </span>
          <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </NuxtLink>

        <template v-if="menuReady">
          <template v-if="auth.isAuthenticated">
            <NuxtLink to="/kyc" class="sidebar-item" @click="$emit('close')">
            <span class="sidebar-item__left">
              <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>احراز هویت</span>
            </span>
            <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </NuxtLink>
          <NuxtLink v-if="auth.isAdmin" to="/admin" class="sidebar-item" @click="$emit('close')">
            <span class="sidebar-item__left">
              <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span>پنل مدیریت</span>
            </span>
            <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </NuxtLink>
          </template>
          <template v-else>
            <div class="sidebar-divider" />
            <NuxtLink to="/login" class="sidebar-item" @click="$emit('close')">
            <span class="sidebar-item__left">
              <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
              </svg>
              <span>ورود</span>
            </span>
            <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </NuxtLink>
          <NuxtLink to="/register" class="sidebar-item" @click="$emit('close')">
            <span class="sidebar-item__left">
              <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
              </svg>
              <span>ثبت‌نام</span>
            </span>
            <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </NuxtLink>
          </template>
        </template>
      </nav>

      <div class="sidebar-social">
        <p class="sidebar-social__title">شبکه‌های اجتماعی</p>
        <div class="sidebar-social__grid">
          <a
            v-for="item in socialItems"
            :key="item.key"
            :href="item.url || '#'"
            target="_blank"
            rel="noopener noreferrer"
            class="sidebar-social__link"
            :class="{ 'is-disabled': !item.url }"
            :title="item.title"
            :aria-disabled="!item.url"
          >
            <span class="sidebar-social__icon">
              <img :src="item.icon" :alt="item.title" width="36" height="36" loading="lazy">
            </span>
          </a>
        </div>
      </div>

      <div v-if="menuReady && auth.isAuthenticated" class="sidebar-footer">
        <button type="button" class="sidebar-item sidebar-item--danger" @click="auth.logout(); $emit('close')">
          <span class="sidebar-item__left">
            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span>خروج</span>
          </span>
        </button>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
