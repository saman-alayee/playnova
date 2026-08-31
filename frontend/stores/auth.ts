import { defineStore } from 'pinia'
import type { User, SiteSettings } from '~/types/api'
import { parseAmount } from '~/utils/formatToman'

const TOKEN_KEY = 'playnova_token'
const USER_KEY = 'playnova_user'
let initPromise: Promise<void> | null = null

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    token: null as string | null,
    settings: null as SiteSettings | null,
    unreadNotifications: 0,
    initialized: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user && !!state.token,
    isAdmin: (state) => !!state.user?.is_admin,
    isSeatAdmin: (state) => !!state.user?.is_seat_admin,
    displayName: (state) => state.user?.username || state.user?.name || '',
    walletBalance: (state) => parseAmount(state.user?.wallet),
    logoUrl: (state) => state.settings?.logo_url || null,
    needsKycRedirect: (state) => {
      const user = state.user
      if (!user || user.is_admin) return false
      if (user.kyc_verified || user.kyc_verified_at) return false

      const status = user.kyc_submission_status
      if (status === 'pending' || status === 'approved') return false

      return true
    },
  },

  actions: {
    hydrateFromStorage() {
      if (!import.meta.client) return
      const storedToken = localStorage.getItem(TOKEN_KEY)
      if (storedToken) {
        this.token = storedToken
      }
      const cachedUser = localStorage.getItem(USER_KEY)
      if (cachedUser) {
        try {
          this.setUser(JSON.parse(cachedUser) as User, false)
        } catch {
          localStorage.removeItem(USER_KEY)
        }
      }
    },

    setUser(user: User | null, persist = true) {
      this.user = user
      if (user?.unread_notifications_count !== undefined) {
        this.unreadNotifications = user.unread_notifications_count
      }
      if (persist && import.meta.client) {
        if (user) {
          localStorage.setItem(USER_KEY, JSON.stringify(user))
        } else {
          localStorage.removeItem(USER_KEY)
        }
      }
    },

    setSettings(settings: SiteSettings | null) {
      this.settings = settings
    },

    async fetchUser() {
      const api = useApi()
      const token = api.getToken()
      if (!token) {
        this.user = null
        this.initialized = true
        return null
      }

      try {
        const user = await api.auth.me()
        this.setUser(user)
        return user
      } catch {
        api.setToken(null)
        this.setUser(null)
        return null
      } finally {
        this.initialized = true
      }
    },

    async fetchSettings() {
      if (this.settings) return this.settings
      const api = useApi()
      try {
        const settings = await api.settings()
        this.setSettings(settings)
        return settings
      } catch {
        return null
      }
    },

    async login(
      mobile: string,
      password: string,
      remember = false,
      captcha?: { key: string; answer: string },
    ) {
      const api = useApi()
      const result = await api.auth.login(mobile, password, remember, captcha)
      api.setToken(result.token)
      this.setUser(result.user)
      this.initialized = true
      return result
    },

    async logout() {
      const api = useApi()
      try {
        await api.auth.logout()
      } catch {
        // ignore logout errors
      } finally {
        api.setToken(null)
        this.setUser(null)
        this.initialized = true
        await navigateTo('/login')
      }
    },

    async init() {
      if (this.initialized) return
      if (initPromise) return initPromise

      this.hydrateFromStorage()

      initPromise = this.fetchUser().then(() => undefined)
      try {
        await initPromise
      } finally {
        initPromise = null
      }
    },
  },
})
