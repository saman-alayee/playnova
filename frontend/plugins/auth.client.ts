export default defineNuxtPlugin(() => {
  const auth = useAuthStore()
  auth.hydrateFromStorage()
  void auth.init()
})
