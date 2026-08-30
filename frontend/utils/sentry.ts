export function captureClientError(error: unknown, context?: Record<string, unknown>) {
  if (!import.meta.client) return

  const config = useRuntimeConfig()
  if (!config.public.sentryDsn) return

  void import('@sentry/nuxt').then((Sentry) => {
    Sentry.captureException(error, {
      extra: context,
    })
  })
}
