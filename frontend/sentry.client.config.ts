import * as Sentry from '@sentry/nuxt'

const dsn = process.env.NUXT_PUBLIC_SENTRY_DSN
const enabled = process.env.NODE_ENV === 'production' && !!dsn

Sentry.init({
  dsn,
  enabled,
  environment: process.env.NODE_ENV,
  tracesSampleRate: Number(process.env.NUXT_PUBLIC_SENTRY_TRACES_SAMPLE_RATE || '0.1'),
  replaysSessionSampleRate: 0,
  replaysOnErrorSampleRate: 0,
})
