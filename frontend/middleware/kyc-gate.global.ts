export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.init()
  }

  if (!auth.isAuthenticated || auth.isAdmin) {
    return
  }

  const exemptPaths = [
    '/',
    '/kyc',
    '/login',
    '/register',
    '/forgot-password',
    '/wallet/callback',
    '/tournaments',
    '/history',
    '/leaderboard',
    '/rules',
    '/about',
    '/privacy',
    '/contact',
    '/tickets',
    '/notifications',
  ]

  if (exemptPaths.some((path) => to.path === path || to.path.startsWith(`${path}/`))) {
    return
  }

  if (to.path.startsWith('/register/verify')) {
    return
  }

  if (auth.needsKycRedirect) {
    return navigateTo('/kyc')
  }
})
