export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.init()
  }

  if (!auth.isAuthenticated) {
    return navigateTo('/login')
  }

  if (!auth.isAdmin) {
    return navigateTo('/')
  }
})
