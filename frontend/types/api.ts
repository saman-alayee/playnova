export interface CaptchaChallenge {
  key: string
  question: string
}

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
  active_seats?: Registration[]
  is_admin?: boolean
  is_seat_admin?: boolean
  first_deposit_done?: boolean
  kyc_verified_at?: string | null
  kyc_verified?: boolean
  kyc_submission_status?: string | null
  kyc_wallet_cap?: number
  unread_notifications_count?: number
  registrations_count?: number
  referrer_username?: string | null
  created_at?: string | null
  created_at_display?: string | null
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
  prize_ranks?: Array<{ rank: number; amount: number }>
  capacity: number
  registered_count?: number
  registrations_count?: number
  start_date?: string | null
  start_date_display?: string | null
  end_date?: string | null
  end_date_display?: string | null
  status: 'upcoming' | 'active' | 'ongoing' | 'ended' | 'cancelled' | string
  status_label?: string
  winner?: { id: number; username?: string } | null
  seat_mode?: number
  seat_mode_label?: string
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
  user_id?: number
  type: string
  type_label?: string
  amount: number | string
  balance_after?: number
  status: string
  status_label?: string
  description?: string | null
  reference_id?: string | null
  rejection_reason?: string | null
  created_at: string
  created_at_display?: string
  displayed_at?: string
  displayed_at_display?: string
  updated_at?: string
  user?: {
    id: number
    username?: string
    mobile?: string
    cod_id?: string | null
    wallet?: number
    bank_card_number?: string | null
    bank_account_name?: string | null
  }
}

export interface AdminWithdrawalsData {
  items: Transaction[]
  meta: PaginationMeta | null
  financialSummary?: {
    pending_withdraws?: number
    pending_withdrawals_count?: number
    total_withdraws_completed?: number
    total_wallets?: number
  } | null
  userTransactions?: Record<string, Transaction[]>
}

export interface Discount {
  id: number
  code: string
  type: string
  value: number
  used_count?: number
  usage_limit?: number | null
  expires_at?: string | null
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
  group_id?: string
  recipient_count?: number
  user?: { id: number; username?: string; mobile?: string; email?: string }
}

export interface AdminBroadcastCampaign {
  id: number
  group_id: string
  title: string
  message: string
  recipient_count: number
  created_at?: string
  created_at_display?: string
  type: 'broadcast'
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
  results_channels?: Array<{
    icon?: string
    title?: string
    url?: string | null
  }>
  support_phone?: string | null
  contact_email?: string | null
  contact_phone?: string | null
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
  seat_label?: string | null
  status?: string
  tournament?: Tournament
}

export interface SeatGridSlot {
  seat_number: number
  label: string
  slot: number
}

export interface SeatGridTeam {
  team: number
  slots: SeatGridSlot[]
}

export interface OccupiedSeatInfo {
  seat_number: number
  seat_label: string
  user?: User
}

export interface SeatSelectionData {
  tournament: Tournament
  registration?: Registration
  teams_grid?: SeatGridTeam[]
  occupied_seats?: Record<string, OccupiedSeatInfo>
  taken_seats?: number[]
  seat_label?: string
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
  admin_note?: string | null
  national_id?: string
  rejection_reason?: string | null
  available_document_sides?: string[]
  user?: User
  submission?: {
    id?: number
    status?: string
    created_at?: string
    reviewed_at?: string | null
    admin_note?: string | null
  } | null
}

export interface TicketMessage {
  id: number
  ticket_id: number
  body: string
  is_admin: boolean
  has_attachment?: boolean
  created_at?: string
  created_at_display?: string
  user?: Pick<User, 'id' | 'username' | 'mobile' | 'email'>
}

export interface Ticket {
  id: number
  subject: string
  message: string
  status: 'open' | 'in_progress' | 'resolved' | 'closed' | string
  status_label?: string
  priority: 'low' | 'medium' | 'high' | string
  priority_label?: string
  messages_count?: number
  created_at?: string
  created_at_display?: string
  updated_at?: string
  updated_at_display?: string
  user?: Pick<User, 'id' | 'username' | 'mobile' | 'email'>
  messages?: TicketMessage[]
}

export interface AdminDashboard {
  total_users?: number
  total_tournaments?: number
  active_tournaments?: number
  total_deposits?: number
  total_withdraws_completed?: number
  pending_withdraws?: number
  pending_withdrawals_count?: number
  total_wallets?: number
  total_entry_fees?: number
  total_prizes_paid?: number
  net_revenue?: number
  open_tickets?: number
  pending_kyc?: number
  unresolved_api_errors?: number
}

export interface ApiErrorLog {
  id: number
  status_code: number
  method: string
  endpoint: string
  message: string
  exception_class?: string | null
  stack_trace?: string | null
  context?: Record<string, unknown> | null
  user?: { id: number; username?: string; email?: string } | null
  ip_address?: string | null
  is_resolved: boolean
  resolved_at?: string | null
  resolved_by?: { id: number; username?: string } | null
  created_at?: string
  created_at_display?: string
}

export interface ApiErrorLogStats {
  unresolved_count: number
  last_24h_count: number
}

export interface PageContent {
  title?: string
  content?: string
  body?: string
}

export interface ContactInfo {
  email?: string
  phone?: string
  address?: string
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
  prize_pool?: number
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
  match_score?: number
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
  prize_table?: Record<number, number>
  prize_table_text?: string
  prize_pool?: number
  vision_model?: string
}

export interface TournamentResultAnalysis {
  tournament_id: number
  tournament_title: string
  players: TournamentResultPlayer[]
  matched: TournamentResultMatched[]
  unmatched: TournamentResultUnmatched[]
  suggested_winner_user_id?: number | null
  participants: TournamentResultParticipant[]
  prize_table?: Record<number, number>
  prize_table_text?: string
  prize_pool?: number
  vision_model?: string
  raw_excerpt?: string
}

export interface AvalAiCreditSource {
  id: string
  name: string
  description: string
  amount_irt: number
  remaining_irt: number
  end_date?: string | null
  template_id?: string
}

export interface AvalAiCredit {
  limit: number
  remaining_irt: number
  remaining_unit: number
  total_unit: number
  exchange_rate: number
  account_tier: number
  packages: AvalAiCreditSource[]
  grants: AvalAiCreditSource[]
}

export interface ApiError extends Error {
  status?: number
  data?: ApiResponse
}
