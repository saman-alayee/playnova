export interface ApiResponse<T = unknown> {
  success: boolean
  message?: string | null
  data?: T
  errors?: Record<string, string[]> | string[] | null
  meta?: PaginationMeta
  links?: PaginationLinks
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface User {
  id: number
  username: string
  name?: string | null
  email?: string | null
  mobile: string
  cod_id?: string | null
  cod_id_changed?: boolean
  bank_card_number?: string | null
  bank_account_name?: string | null
  kills?: number
  wins?: number
  losses?: number
  wallet: number | string
  referral_code?: string
  is_admin?: boolean
  is_seat_admin?: boolean
  first_deposit_done?: boolean
  kyc_verified_at?: string | null
  unread_notifications_count?: number
}

export interface AuthResponse {
  user: User
  token: string
}

export interface Tournament {
  id: number
  title: string
  game?: string
  league?: 'beginner' | 'intermediate' | 'professional' | string
  description?: string | null
  entry_fee: number
  prize_pool: number
  capacity: number
  registered_count?: number
  registrations_count?: number
  start_date?: string | null
  start_date_display?: string | null
  end_date?: string | null
  end_date_display?: string | null
  status: 'upcoming' | 'active' | 'ongoing' | 'ended' | 'cancelled' | string
  status_label?: string
  seat_mode?: number
  winner_id?: number | null
  game_login_info?: string | null
  is_registered?: boolean
  pending_seat?: boolean
  pending_team?: boolean
  allows_game_login?: boolean
  accepts_registration?: boolean
}

export interface HomeData {
  active_tournaments: Tournament[]
  leagues: Record<string, Tournament[]>
  news?: NewsItem[]
  hero_slides?: string[]
}

export interface NewsItem {
  id: number
  title: string
  body?: string
  created_at?: string
}

export interface LeaderboardEntry {
  rank?: number
  id?: number
  user_id?: number
  username: string
  kills: number
  wins?: number
  losses?: number
}

export interface HistoryItem {
  id: number
  title: string
  status: string
  status_label?: string
  start_date?: string
  end_date?: string
  prize_pool?: number
  result?: string
}

export interface Transaction {
  id: number
  type: string
  type_label?: string
  amount: number | string
  status: string
  status_label?: string
  description?: string | null
  created_at: string
}

export interface WalletData {
  balance: number
  transactions: Transaction[]
  max_deposit?: number
  kyc_verified?: boolean
}

export interface Notification {
  id: number
  title: string
  body?: string
  message?: string
  type?: string | null
  is_read: boolean
  created_at: string
  created_at_display?: string
}

export interface RuleSection {
  id: number
  content: string
}

export interface TournamentShowData {
  tournament: Tournament
  is_registered: boolean
  pending_seat: boolean
  registration?: Registration | null
  occupied_seats?: Record<string, unknown>
}

export interface NotificationsListData {
  notifications: Notification[]
  news: NewsItem[]
  unread_count: number
}

export interface FaqCategory {
  icon: string
  title: string
  items: FaqItem[]
}

export interface FaqItem {
  q: string
  a: string
}

export interface FaqData {
  categories: Record<string, FaqCategory>
  active_key?: string
  active_category?: FaqCategory | null
  support_phone?: string | null
}

export interface SiteSettings {
  logo_url?: string
  site_name?: string
  social?: {
    instagram?: string | null
    rubika?: string | null
    telegram?: string | null
  }
  support_phone?: string | null
}

export interface TeamInvite {
  id: number
  tournament_id: number
  tournament_title?: string
  inviter_username?: string
  invitee_username?: string
  status: string
  seconds_remaining?: number
  team_group_id?: string | null
  direction?: 'incoming' | 'outgoing'
}

export interface TeamInviteBannerData {
  pending: TeamInvite[]
  sent: TeamInvite[]
  signature?: string
}

export interface Registration {
  id: number
  tournament_id: number
  user_id: number
  seat_number?: number | null
  status?: string
  tournament?: Tournament
}

export interface SeatSelectionData {
  tournament: Tournament
  taken_seats: number[]
  user_registration?: Registration | null
}

export interface GameLoginInfo {
  title: string
  content: string
  seat_label?: string | null
  seat_number?: number | null
  error?: string
}

export interface KycSubmission {
  id?: number
  status?: 'pending' | 'approved' | 'rejected' | string
  national_id?: string
  rejection_reason?: string | null
}

export interface AdminDashboard {
  users_count?: number
  tournaments_count?: number
  pending_withdrawals?: number
  pending_kyc?: number
  recent_transactions?: Transaction[]
}

export interface PageContent {
  title?: string
  content?: string
  body?: string
}

export interface ProfileData {
  user: User
  active_seats?: Registration[]
  referral_bonus_percent?: number
  stats?: {
    tournaments_played?: number
    wins?: number
    kills?: number
  }
}

export interface TournamentPrizeEntry {
  id: number
  user_id: number
  username?: string
  cod_id?: string | null
  rank?: number | null
  team_label?: string | null
  seat_number?: number | null
  kills?: number | null
  prize_amount: number
}

export interface TournamentPrizeBatch {
  id: number
  tournament_id: number
  tournament_title?: string
  status: string
  status_label: string
  total_amount: number
  winner?: { id: number; username: string } | null
  approved_by?: { id: number; username: string } | null
  approved_at?: string | null
  approved_at_display?: string | null
  paid_at?: string | null
  paid_at_display?: string | null
  created_at_display?: string | null
  entries: TournamentPrizeEntry[]
}

export interface TournamentResultPlayer {
  rank: number
  name?: string | null
  uid?: string | null
  kills?: number | null
  score?: number | null
}

export interface TournamentResultMatched {
  rank: number
  detected_name?: string | null
  detected_uid?: string | null
  kills?: number | null
  user_id: number
  username: string
  cod_id?: string | null
  match_method?: string
}

export interface TournamentResultUnmatched {
  rank: number
  detected_name?: string | null
  detected_uid?: string | null
  kills?: number | null
}

export interface TournamentResultParticipant {
  user_id: number
  username: string
  cod_id?: string | null
  seat_number?: number | null
}

export interface TournamentResultAiConfig {
  system_prompt: string
  user_prompt: string
  seat_mode_label: string
  has_saved_prompt: boolean
}

export interface TournamentResultAnalysis {
  tournament_id: number
  tournament_title: string
  players: TournamentResultPlayer[]
  matched: TournamentResultMatched[]
  unmatched: TournamentResultUnmatched[]
  suggested_winner_user_id?: number | null
  participants: TournamentResultParticipant[]
  raw_excerpt?: string
}

export interface ApiError extends Error {
  status?: number
  data?: ApiResponse
}
