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
      timeout?: number
    } = {},
  ): Promise<T> {
    const {
      method = 'GET',
      body = undefined,
      query = undefined,
      auth = true,
      csrf = false,
      timeout = 15000,
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

    const token = auth ? getToken() : null
    const needsCredentials = csrf || !!token

    try {
      const response = await $fetch<ApiResponse<T>>(path, {
        baseURL: config.public.apiBase as string,
        method,
        headers,
        body: isFormData ? body : body ?? undefined,
        query,
        credentials: needsCredentials ? 'include' : 'omit',
        timeout,
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
      options: { csrf?: boolean; auth?: boolean; timeout?: number } = {},
    ) =>
      request<T>(path, {
        method: 'POST',
        body,
        csrf: options.csrf,
        auth: options.auth ?? true,
        timeout: options.timeout,
      }),

    put: <T>(path: string, body?: Record<string, unknown>) =>
      request<T>(path, { method: 'PUT', body }),

    patch: <T>(path: string, body?: Record<string, unknown>) =>
      request<T>(path, { method: 'PATCH', body }),

    delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),

    paginated: async <T>(
      path: string,
      query?: Record<string, string | number | boolean | undefined>,
      auth = true,
    ): Promise<{ items: T[]; meta: import('~/types/api').PaginationMeta | null }> => {
      const headers: Record<string, string> = { Accept: 'application/json' }
      const token = auth ? getToken() : null
      if (token) headers.Authorization = `Bearer ${token}`

      const response = await $fetch<import('~/types/api').ApiResponse<T[]>>(path, {
        baseURL: config.public.apiBase as string,
        query,
        headers,
        credentials: token ? 'include' : 'omit',
      })

      if (!response.success) {
        const err = new Error(response.message || 'خطای سرور') as import('~/types/api').ApiError
        err.data = response
        throw err
      }

      return { items: (response.data ?? []) as T[], meta: response.meta ?? null }
    },

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
        api.post<{
          token?: string
          verification_required?: boolean
          user?: import('~/types/api').User
        }>('/auth/register', data, { auth: false }),

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

    home: () => api.get<import('~/types/api').HomeData>('/home', undefined, !!getToken()),

    settings: () => api.get<import('~/types/api').SiteSettings>('/settings', undefined, false),

    tournaments: {
      show: (id: number | string) =>
        api.get<import('~/types/api').TournamentShowData>(`/tournaments/${id}`),

      register: (id: number | string, data?: Record<string, unknown>) =>
        api.post<{ next_step?: string }>(`/tournaments/${id}/register`, data),

      cancelPending: (id: number | string) =>
        api.post<void>(`/tournaments/${id}/cancel-pending`),

      teamInvite: (id: number | string, teammateCodId: string | string[]) =>
        api.post<void>(`/tournaments/${id}/team-invite`, {
          ...(Array.isArray(teammateCodId)
            ? { teammate_cod_ids: teammateCodId }
            : { teammate_cod_id: teammateCodId }),
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
      popup: () => api.get<import('~/types/api').Notification | null>('/notifications/popup'),
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

      tournaments: (query?: Record<string, string | number | boolean | undefined>) =>
        api.paginated<import('~/types/api').Tournament>('/admin/tournaments', query),

      tournament: (id: number | string) =>
        api.get<import('~/types/api').Tournament & { game_login_info?: string; winner_id?: number }>(
          `/admin/tournaments/${id}`,
        ),

      createTournament: (data: Record<string, unknown>) =>
        api.post<import('~/types/api').Tournament>('/admin/tournaments', data),

      updateTournament: (id: number | string, data: Record<string, unknown>) =>
        api.put<import('~/types/api').Tournament>(`/admin/tournaments/${id}`, data),

      deleteTournament: (id: number | string) => api.delete<void>(`/admin/tournaments/${id}`),

      updateTournamentStatus: (id: number | string, status: string) =>
        api.put<void>(`/admin/tournaments/${id}/status`, { status }),

      recordTournamentResult: (id: number | string, winnerId: number) =>
        api.post<void>(`/admin/tournaments/${id}/result`, { winner_id: winnerId }),

      payTournamentPrize: (id: number | string) =>
        api.post<void>(`/admin/tournaments/${id}/pay-prize`),

      tournamentParticipants: (id: number | string) =>
        api.get<{ user_id: number; username: string; email?: string; cod_id?: string }[]>(
          `/admin/tournaments/${id}/participants`,
        ),

      analyzeTournamentResult: (id: number | string, formData: FormData) =>
        api.post<import('~/types/api').TournamentResultAnalysis>(
          `/admin/tournaments/${id}/result-ai/analyze`,
          formData,
          { timeout: 180000 },
        ),

      tournamentResultAiConfig: (id: number | string) =>
        api.get<import('~/types/api').TournamentResultAiConfig>(
          `/admin/tournaments/${id}/result-ai/config`,
        ),

      applyTournamentResult: (
        id: number | string,
        data: { winner_user_id: number; player_stats?: { user_id: number; kills?: number; rank?: number }[] },
      ) =>
        api.post<{ winner_id: number; winner_username: string; tournament_status: string; prize_pending_approval?: boolean }>(
          `/admin/tournaments/${id}/result-ai/apply`,
          data,
        ),

      tournamentPrizes: (id: number | string) =>
        api.get<import('~/types/api').TournamentPrizeBatch | null>(`/admin/tournaments/${id}/prizes`),

      updateTournamentPrizes: (
        id: number | string,
        entries: { id: number; prize_amount: number }[],
      ) => api.put<import('~/types/api').TournamentPrizeBatch>(`/admin/tournaments/${id}/prizes`, { entries }),

      approveTournamentPrizes: (id: number | string) =>
        api.post<import('~/types/api').TournamentPrizeBatch>(`/admin/tournaments/${id}/prizes/approve`),

      users: (query?: Record<string, string | number | boolean | undefined>) =>
        api.paginated<import('~/types/api').User>('/admin/users', query),

      updateUserCodId: (userId: number, codId: string) =>
        api.put<void>(`/admin/users/${userId}/cod-id`, { cod_id: codId }),

      updateUserKills: (userId: number, kills: number) =>
        api.put<void>(`/admin/users/${userId}/kills`, { kills }),

      adjustUserWallet: (
        userId: number,
        data: { action: 'add' | 'subtract' | 'set'; amount: number; description?: string; allow_negative?: boolean },
      ) => api.put<void>(`/admin/users/${userId}/wallet`, data),

      userActivity: (userId: number) =>
        api.paginated<{ id: number; category: string; action: string; description?: string; created_at?: string; actor?: { username?: string } }>(
          `/admin/users/${userId}/activity`,
        ),

      deleteUser: (userId: number) => api.delete<void>(`/admin/users/${userId}`),

      withdrawals: (query?: Record<string, string | number | boolean | undefined>) =>
        api.paginated<import('~/types/api').Transaction>('/admin/withdrawals', query),

      updateWithdrawal: (
        txId: number,
        data: { status: string; rejection_reason?: string },
      ) => api.put<void>(`/admin/withdrawals/${txId}`, data),

      kyc: () => api.paginated<import('~/types/api').KycSubmission>('/admin/kyc'),

      updateKyc: (id: number, data: { status: string; admin_note?: string }) =>
        api.put<void>(`/admin/kyc/${id}`, data),

      kycDocumentUrl: (id: number, side: string) => {
        const base = config.public.apiBase as string
        const token = getToken()
        return `${base}/admin/kyc/${id}/document/${side}${token ? `?token=${token}` : ''}`
      },

      siteSettings: () => api.get<Record<string, string>>('/admin/settings/site'),

      updateSiteSettings: (data: Record<string, unknown>) =>
        api.put<void>('/admin/settings/site', data),

      discounts: () => api.paginated<import('~/types/api').Discount>('/admin/discounts'),

      createDiscount: (data: Record<string, unknown>) => api.post<void>('/admin/discounts', data),

      deleteDiscount: (id: number) => api.delete<void>(`/admin/discounts/${id}`),

      news: () => api.paginated<import('~/types/api').NewsItem>('/admin/news'),

      createNews: (formData: FormData) => api.post<void>('/admin/news', formData),

      deleteNews: (id: number) => api.delete<void>(`/admin/news/${id}`),

      sendBroadcast: (data: { title: string; message: string }) =>
        api.post<void>('/admin/broadcast', data),

      broadcasts: () => api.paginated<import('~/types/api').Notification>('/admin/broadcasts'),

      updateBroadcast: (id: number, data: { title: string; message: string }) =>
        api.put<void>(`/admin/broadcasts/${id}`, data),

      deleteBroadcast: (id: number) => api.delete<void>(`/admin/broadcasts/${id}`),

      rules: () => api.get<import('~/types/api').RuleSection[]>('/admin/rules'),

      createRule: (content: string) => api.post<void>('/admin/rules', { content }),

      updateRule: (id: number, content: string) => api.put<void>(`/admin/rules/${id}`, { content }),

      deleteRule: (id: number) => api.delete<void>(`/admin/rules/${id}`),

      logo: () => api.get<{ logo?: string; logo_url?: string }>('/admin/settings/logo'),

      updateLogo: (formData: FormData) => api.post<void>('/admin/settings/logo', formData),

      deleteLogo: () => api.delete<void>('/admin/settings/logo'),

      paymentGateway: () =>
        api.get<{ merchant_id?: string; is_active?: boolean; sandbox?: boolean }>(
          '/admin/settings/payment-gateway',
        ),

      updatePaymentGateway: (data: Record<string, unknown>) =>
        api.put<void>('/admin/settings/payment-gateway', data),

      testPaymentGateway: () => api.post<{ message?: string }>('/admin/settings/payment-gateway/test'),

      smsSettings: () => api.get<Record<string, unknown>>('/admin/settings/sms'),

      updateSmsSettings: (data: Record<string, unknown>) => api.put<void>('/admin/settings/sms', data),

      aiSettings: () =>
        api.get<{
          base_url: string
          vision_model: string
          timeout: number
          is_active: boolean
          has_api_key: boolean
          api_key_source: 'database' | 'env' | 'none'
          suggested_models: string[]
        }>('/admin/settings/ai'),

      updateAiSettings: (data: Record<string, unknown>) => api.put<void>('/admin/settings/ai', data),

      testAiSettings: () =>
        api.post<{ model?: string; response?: string; message?: string }>('/admin/settings/ai/test'),

      referralSettings: () => api.get<{ bonus_percent: number }>('/admin/settings/referral'),

      updateReferralSettings: (bonusPercent: number) =>
        api.put<void>('/admin/settings/referral', { bonus_percent: bonusPercent }),

      admins: () => api.get<import('~/types/api').User[]>('/admin/admins'),

      addAdmin: (email: string) => api.post<void>('/admin/admins', { email }),

      removeAdmin: (userId: number) => api.delete<void>(`/admin/admins/${userId}`),

      seatAdmins: () => api.get<import('~/types/api').User[]>('/admin/seat-admins'),

      addSeatAdmin: (email: string) => api.post<void>('/admin/seat-admins', { email }),

      removeSeatAdmin: (userId: number) => api.delete<void>(`/admin/seat-admins/${userId}`),

      tournamentSeats: () => api.get<import('~/types/api').Tournament[]>('/admin/tournament-seats'),

      tournamentSeatMap: (id: number | string) =>
        api.get<{
          tournament: import('~/types/api').Tournament
          occupied_seats: Record<number, { username?: string; cod_id?: string }>
          capacity: number
          seat_mode: number
        }>(`/admin/tournament-seats/${id}`),
    },
  }

  return api
}
