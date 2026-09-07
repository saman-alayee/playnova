function isSeatAdminPath(path: string) {
  return path === '/admin/tournament-seats' || path.startsWith('/admin/tournament-seats/')
}

export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.init()
  }

  if (!auth.isAuthenticated) {
    return navigateTo({
      path: '/login',
      query: { redirect: to.fullPath },
    })
  }

  if (auth.isAdmin) {
    return
  }

  if (auth.isSeatAdmin) {
    if (isSeatAdminPath(to.path)) {
      return
    }

    return navigateTo('/admin/tournament-seats')
  }

  return navigateTo('/')
})
