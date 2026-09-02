import type { AsyncDataOptions, NuxtApp } from '#app'

type AsyncDataCause = 'initial' | 'refresh:manual' | 'refresh:hook' | 'watch' | undefined

function readCachedData<T>(key: string, nuxtApp: NuxtApp) {
  const extra = nuxtApp as NuxtApp & { static?: { data?: Record<string, unknown> } }
  return (nuxtApp.payload.data[key] ?? extra.static?.data?.[key]) as T | undefined
}

/**
 * Page data that does not block client-side navigation.
 * Shows cached payload instantly, then refreshes from the API.
 */
export function usePageData<T>(
  key: string | (() => string),
  handler: () => Promise<T>,
  options: AsyncDataOptions<T> = {},
) {
  const nuxtApp = useNuxtApp()
  const pendingCount = useState('page-data-pending', () => 0)
  const indicator = import.meta.client ? useLoadingIndicator() : null

  const result = useAsyncData<T>(key, handler, {
    lazy: true,
    getCachedData: (dataKey, app, ctx) => {
      const cause = (ctx as { cause?: AsyncDataCause } | undefined)?.cause
      if (cause === 'refresh:manual' || cause === 'refresh:hook') {
        return undefined
      }
      return readCachedData<T>(dataKey, app)
    },
    ...options,
  })

  if (import.meta.client) {
    watch(
      () => result.pending.value,
      (isPending, wasPending) => {
        if (isPending === wasPending) return
        if (isPending) {
          pendingCount.value++
          indicator?.start()
          return
        }
        pendingCount.value = Math.max(0, pendingCount.value - 1)
        if (pendingCount.value === 0) {
          indicator?.finish()
        }
      },
      { immediate: true },
    )

    onBeforeUnmount(() => {
      if (!result.pending.value) return
      pendingCount.value = Math.max(0, pendingCount.value - 1)
      if (pendingCount.value === 0) {
        indicator?.finish()
      }
    })
  }

  return result
}
