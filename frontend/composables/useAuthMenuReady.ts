/** Auth-dependent menu items must render only on the client after init completes. */
export function useAuthMenuReady() {
  const auth = useAuthStore()

  return computed(() => import.meta.client && auth.initialized)
}
