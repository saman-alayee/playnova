import { defineStore } from 'pinia'
import type { User, SiteSettings } from '~/types/api'

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
    walletBalance: (state) => Number(state.user?.wallet ?? 0),
    logoUrl: (state) => state.settings?.logo_url || null,
  },

  actions: {
    setUser(user: User | null) {
      this.user = user
      if (user?.unread_notifications_count !== undefined) {
        this.unreadNotifications = user.unread_notifications_count
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
        this.user = null
        return null
      } finally {
        this.initialized = true
      }
    },

    async fetchSettings() {
      const api = useApi()
      try {
        const settings = await api.settings()
        this.setSettings(settings)
        return settings
      } catch {
        return null
      }
    },

    async login(mobile: string, password: string, remember = false) {
      const api = useApi()
      const result = await api.auth.login(mobile, password, remember)
      api.setToken(result.token)
      this.setUser(result.user)
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
        this.user = null
        await navigateTo('/login')
      }
    },

    async init() {
      if (import.meta.client) {
        const api = useApi()
        const stored = localStorage.getItem('playnova_token')
        if (stored) {
          this.token = stored
        }
      }
      await Promise.all([this.fetchSettings(), this.fetchUser()])
    },
  },
})
