import type { ApiResponse, ApiError } from '~/types/api'

const TOKEN_KEY = 'playnova_token'

export function useApi() {
  const config = useRuntimeConfig()
  const authStore = useAuthStore()

  function getToken(): string | null {
    if (import.meta.client) {
      return localStorage.getItem(TOKEN_KEY)
    }
    return authStore.token
  }

  function setToken(token: string | null) {
    authStore.token = token
    if (import.meta.client) {
      if (token) {
        localStorage.setItem(TOKEN_KEY, token)
      } else {
        localStorage.removeItem(TOKEN_KEY)
      }
    }
  }

  async function ensureCsrfCookie() {
    const backendUrl = config.public.backendUrl as string
    await $fetch('/sanctum/csrf-cookie', {
      baseURL: backendUrl,
      credentials: 'include',
    })
  }

  async function request<T>(
    path: string,
    options: {
      method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
      body?: Record<string, unknown> | FormData | null
      query?: Record<string, string | number | boolean | undefined>
      auth?: boolean
      csrf?: boolean
    } = {},
  ): Promise<T> {
    const {
      method = 'GET',
      body = undefined,
      query = undefined,
      auth = true,
      csrf = false,
    } = options

    const headers: Record<string, string> = {
      Accept: 'application/json',
    }

    if (auth) {
      const token = getToken()
      if (token) {
        headers.Authorization = `Bearer ${token}`
      }
    }

    if (csrf && import.meta.client) {
      await ensureCsrfCookie()
      headers['X-Requested-With'] = 'XMLHttpRequest'
    }

    const isFormData = body instanceof FormData
    if (body && !isFormData) {
      headers['Content-Type'] = 'application/json'
    }

    try {
      const response = await $fetch<ApiResponse<T>>(path, {
        baseURL: config.public.apiBase as string,
        method,
        headers,
        body: isFormData ? body : body ?? undefined,
        query,
        credentials: 'include',
      })

      if (response && typeof response === 'object' && 'success' in response) {
        if (!response.success) {
          const err = new Error(response.message || 'خطای سرور') as ApiError
          err.data = response
          throw err
        }
        return (response.data ?? response) as T
      }

      return response as T
    } catch (e: unknown) {
      const fetchError = e as { status?: number; data?: ApiResponse; message?: string }
      const err = new Error(
        fetchError.data?.message || fetchError.message || 'خطا در ارتباط با سرور',
      ) as ApiError
      err.status = fetchError.status
      err.data = fetchError.data
      throw err
    }
  }

  const api = {
    get: <T>(
      path: string,
      query?: Record<string, string | number | boolean | undefined>,
      auth = true,
    ) => request<T>(path, { query, auth }),

    post: <T>(
      path: string,
      body?: Record<string, unknown> | FormData | null,
      options: { csrf?: boolean; auth?: boolean } = {},
    ) => request<T>(path, { method: 'POST', body, csrf: options.csrf, auth: options.auth ?? true }),

    put: <T>(path: string, body?: Record<string, unknown>) =>
      request<T>(path, { method: 'PUT', body }),

    patch: <T>(path: string, body?: Record<string, unknown>) =>
      request<T>(path, { method: 'PATCH', body }),

    delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),

    getToken,
    setToken,
    ensureCsrfCookie,

    auth: {
      login: (login: string, password: string, remember = false) =>
        api.post<{ user: import('~/types/api').User; token: string }>(
          '/auth/login',
          { login, password, remember },
          { auth: false },
        ),

      register: (data: Record<string, unknown>) =>
        api.post<{ token?: string; verification_required?: boolean; user?: import('~/types/api').User }>(
          '/auth/register',
          data,
          { auth: false },
        ),

      logout: () => api.post<void>('/auth/logout'),

      me: () => api.get<import('~/types/api').User>('/auth/me'),

      verifyRegister: (token: string, code: string) =>
        api.post<{ user: import('~/types/api').User; token: string }>(
          `/auth/register/verify/${token}`,
          { code },
          { auth: false },
        ),

      resendRegisterVerify: (token: string) =>
        api.post<void>(`/auth/register/verify/${token}/resend`, undefined, { auth: false }),

      forgotPassword: (mobile: string) =>
        api.post<{ token?: string }>('/auth/forgot-password', { mobile }, { auth: false }),

      resetPassword: (token: string, data: Record<string, unknown>) =>
        api.post<void>(`/auth/reset-password/${token}`, data, { auth: false }),

      resendResetCode: (token: string) =>
        api.post<void>(`/auth/reset-password/${token}/resend`, undefined, { auth: false }),
    },

    home: () => api.get<import('~/types/api').HomeData>('/home', undefined, false),

    settings: () => api.get<import('~/types/api').SiteSettings>('/settings', undefined, false),

    tournaments: {
      show: (id: number | string) =>
        api.get<import('~/types/api').TournamentShowData>(`/tournaments/${id}`),

      register: (id: number | string, data?: Record<string, unknown>) =>
        api.post<{ next_step?: string }>(`/tournaments/${id}/register`, data),

      cancelPending: (id: number | string) =>
        api.post<void>(`/tournaments/${id}/cancel-pending`),

      teamInvite: (id: number | string, teammateCodId: string) =>
        api.post<void>(`/tournaments/${id}/team-invite`, {
          teammate_cod_id: teammateCodId,
          accept_rules: '1',
        }),

      gameLogin: (id: number | string) =>
        api.get<import('~/types/api').GameLoginInfo>(`/tournaments/${id}/game-login`),

      selectSeat: (id: number | string) =>
        api.get<import('~/types/api').SeatSelectionData>(`/tournaments/${id}/select-seat`),

      storeSeat: (id: number | string, seatNumber: number) =>
        api.post<void>(`/tournaments/${id}/select-seat`, { seat_number: seatNumber }),
    },

    leaderboard: () => api.get<import('~/types/api').LeaderboardEntry[]>('/leaderboard', undefined, false),

    history: () => api.get<import('~/types/api').HistoryItem[]>('/history', undefined, false),

    rules: () => api.get<import('~/types/api').RuleSection[]>('/rules', undefined, false),

    pages: {
      privacy: () => api.get<import('~/types/api').PageContent>('/pages/privacy', undefined, false),
      about: () => api.get<import('~/types/api').PageContent>('/pages/about', undefined, false),
      contact: () => api.get<import('~/types/api').PageContent>('/pages/contact', undefined, false),
    },

    faq: (cat?: string) =>
      api.get<import('~/types/api').FaqData>('/pages/faq', cat ? { cat } : undefined, false),

    profile: {
      show: () => api.get<import('~/types/api').ProfileData>('/profile'),
      update: (data: Record<string, unknown>) => api.put<void>('/profile', data),
    },

    wallet: {
      show: () => api.get<import('~/types/api').WalletData>('/wallet'),
      deposit: (amount: number) => api.post<{ redirect_url?: string }>('/wallet/deposit', { amount }),
      withdraw: (data: Record<string, unknown>) => api.post<void>('/wallet/withdraw', data),
      processCallback: (query: Record<string, string>) =>
        request<{ message?: string }>('/wallet/callback', { query, auth: false }),
    },

    kyc: {
      show: () => api.get<import('~/types/api').KycSubmission>('/kyc'),
      store: (formData: FormData) => api.post<void>('/kyc', formData),
    },

    notifications: {
      list: () => api.get<import('~/types/api').NotificationsListData>('/notifications'),
      markRead: (id: number | string) => api.post<void>(`/notifications/${id}/read`),
      markAllRead: () => api.post<void>('/notifications/read-all'),
      delete: (id: number | string) => api.delete<void>(`/notifications/${id}`),
    },

    teamInvites: {
      banner: () => api.get<import('~/types/api').TeamInviteBannerData>('/team-invites'),
      accept: (id: number | string) => api.post<void>(`/team-invites/${id}/accept`),
      decline: (id: number | string) => api.post<void>(`/team-invites/${id}/decline`),
      cancel: (id: number | string) => api.post<void>(`/team-invites/${id}/cancel`),
    },

    admin: {
      dashboard: () => api.get<import('~/types/api').AdminDashboard>('/admin/dashboard'),
      tournaments: () => api.get<import('~/types/api').Tournament[]>('/admin/tournaments'),
      users: () => api.get<import('~/types/api').User[]>('/admin/users'),
      withdrawals: () => api.get<import('~/types/api').Transaction[]>('/admin/withdrawals'),
      kyc: () => api.get<import('~/types/api').KycSubmission[]>('/admin/kyc'),
      siteSettings: () => api.get<import('~/types/api').SiteSettings>('/admin/settings/site'),
      updateSiteSettings: (data: Record<string, unknown>) =>
        api.put<void>('/admin/settings/site', data),
    },
  }

  return api
}
