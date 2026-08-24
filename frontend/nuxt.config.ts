export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },

  modules: ['@pinia/nuxt', '@nuxtjs/tailwindcss'],

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
      ],
      link: [
        { rel: 'icon', href: '/favicon.ico', sizes: '48x48' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&display=swap',
        },
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://127.0.0.1:8000/api/v1',
      backendUrl: process.env.NUXT_PUBLIC_BACKEND_URL || 'http://127.0.0.1:8000',
    },
  },

  routeRules: {
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
