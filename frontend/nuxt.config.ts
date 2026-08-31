export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: process.env.NODE_ENV !== 'production' },

  modules: ['@pinia/nuxt', '@nuxtjs/tailwindcss', '@sentry/nuxt/module'],

  css: ['~/assets/css/main.css'],

  app: {
    head: {
      htmlAttrs: { lang: 'fa', dir: 'rtl' },
      title: 'PlayNova - پلتفرم مسابقات آنلاین',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no' },
        { name: 'description', content: 'پلتفرم برگزاری مسابقات آنلاین Call of Duty Mobile — رقابت، هیجان و جوایز نقدی.' },
        { name: 'theme-color', content: '#050505' },
        { property: 'og:type', content: 'website' },
        { property: 'og:site_name', content: 'PlayNova' },
        { property: 'og:locale', content: 'fa_IR' },
        { property: 'og:image', content: '/logo.png' },
      ],
      link: [
        { rel: 'icon', href: '/favicon.ico', sizes: '48x48' },
        { rel: 'icon', type: 'image/png', href: '/favicon-48x48.png', sizes: '48x48' },
        { rel: 'icon', type: 'image/png', href: '/favicon-96x96.png', sizes: '96x96' },
        { rel: 'apple-touch-icon', href: '/favicon-192x192.png', sizes: '192x192' },
        { rel: 'preload', href: '/fonts/vazirmatn-arabic.woff2', as: 'font', type: 'font/woff2', crossorigin: 'anonymous' },
        { rel: 'preload', href: '/fonts/vazirmatn-latin.woff2', as: 'font', type: 'font/woff2', crossorigin: 'anonymous' },
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://127.0.0.1:8000/api/v1',
      backendUrl: process.env.NUXT_PUBLIC_BACKEND_URL || 'http://127.0.0.1:8000',
      sentryDsn: process.env.NUXT_PUBLIC_SENTRY_DSN || '',
      sentryTracesSampleRate: process.env.NUXT_PUBLIC_SENTRY_TRACES_SAMPLE_RATE || '0.1',
    },
  },

  sentry: {
    enabled: process.env.NODE_ENV === 'production' && !!process.env.NUXT_PUBLIC_SENTRY_DSN,
    dsn: process.env.NUXT_PUBLIC_SENTRY_DSN || '',
  },

  experimental: {
    payloadExtraction: true,
    defaults: {
      nuxtLink: {
        prefetchOn: { visibility: true },
      },
    },
  },

  nitro: {
    compressPublicAssets: true,
  },

  routeRules: {
    '/': { swr: 30 },
    '/rules': { swr: 300 },
    '/leaderboard': { swr: 60 },
    '/history': { swr: 60 },
    '/about': { swr: 3600 },
    '/privacy': { swr: 3600 },
    '/contact': { swr: 300 },
    '/admin/**': { ssr: false },
    '/profile': { ssr: false },
    '/wallet': { ssr: false },
    '/wallet/**': { ssr: false },
    '/kyc': { ssr: false },
    '/kyc/**': { ssr: false },
    '/notifications': { ssr: false },
    '/notifications/**': { ssr: false },
    '/faq': { redirect: { to: '/tickets', statusCode: 301 } },
    '/register/verify-mobile/**': { redirect: { to: '/register/verify/**', statusCode: 301 } },
    '/admin/settings/site': { redirect: { to: '/admin/site-settings', statusCode: 301 } },
    '/admin/dashboard': { redirect: { to: '/admin', statusCode: 301 } },
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },
})
