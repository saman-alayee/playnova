<script setup lang="ts">
import type { User } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت کاربران | PlayNova' })

const api = useApi()
const flash = useState<{ success?: string; error?: string } | null>('flash')
const route = useRoute()
const router = useRouter()
const { formatToman } = useFormatToman()

const search = ref(typeof route.query.search === 'string' ? route.query.search : '')
const role = ref(typeof route.query.role === 'string' ? route.query.role : 'all')
const kyc = ref(typeof route.query.kyc === 'string' ? route.query.kyc : 'all')
const deposit = ref(typeof route.query.deposit === 'string' ? route.query.deposit : 'all')
const sort = ref(typeof route.query.sort === 'string' ? route.query.sort : 'newest')
const page = ref(Number(route.query.page) > 0 ? Number(route.query.page) : 1)

const roleOptions = [
  { value: 'all', label: 'همه نقش‌ها' },
  { value: 'admin', label: 'فقط ادمین' },
  { value: 'seat_admin', label: 'ادمین جایگاه' },
  { value: 'regular', label: 'کاربر عادی' },
]

const kycOptions = [
  { value: 'all', label: 'همه وضعیت KYC' },
  { value: 'verified', label: 'تأیید شده' },
  { value: 'pending', label: 'در انتظار بررسی' },
  { value: 'unverified', label: 'بدون احراز / رد شده' },
]

const depositOptions = [
  { value: 'all', label: 'همه وضعیت واریز' },
  { value: 'done', label: 'واریز اول انجام شده' },
  { value: 'not_done', label: 'بدون واریز اول' },
]

const sortOptions = [
  { value: 'newest', label: 'جدیدترین عضویت' },
  { value: 'wallet', label: 'بیشترین موجودی' },
  { value: 'kills', label: 'بیشترین کیل' },
  { value: 'username', label: 'نام کاربری (الفبا)' },
]

function queryParams() {
  const params: Record<string, string | number> = { page: page.value }
  if (search.value.trim()) params.search = search.value.trim()
  if (role.value !== 'all') params.role = role.value
  if (kyc.value !== 'all') params.kyc = kyc.value
  if (deposit.value !== 'all') params.deposit = deposit.value
  if (sort.value !== 'newest') params.sort = sort.value
  return params
}

const { data, pending, error, refresh } = await useAsyncData(
  'admin-users',
  () => api.admin.users(queryParams()),
  { watch: [page] },
)

const users = computed(() => data.value?.items ?? [])
const totalUsers = computed(() => data.value?.meta?.total ?? 0)
const hasActiveFilters = computed(
  () => !!search.value.trim() || role.value !== 'all' || kyc.value !== 'all' || deposit.value !== 'all' || sort.value !== 'newest',
)

function syncRoute() {
  const query: Record<string, string> = {}
  if (search.value.trim()) query.search = search.value.trim()
  if (role.value !== 'all') query.role = role.value
  if (kyc.value !== 'all') query.kyc = kyc.value
  if (deposit.value !== 'all') query.deposit = deposit.value
  if (sort.value !== 'newest') query.sort = sort.value
  if (page.value > 1) query.page = String(page.value)
  router.replace({ query })
}

function applyFilters() {
  page.value = 1
  syncRoute()
  refresh()
}

function resetFilters() {
  search.value = ''
  role.value = 'all'
  kyc.value = 'all'
  deposit.value = 'all'
  sort.value = 'newest'
  page.value = 1
  syncRoute()
  refresh()
}

watch(page, () => {
  syncRoute()
  refresh()
})

function roleLabel(user: User) {
  if (user.is_admin) return 'ادمین'
  if (user.is_seat_admin) return 'ادمین جایگاه'
  return 'کاربر'
}

function roleClass(user: User) {
  if (user.is_admin) return 'is-admin'
  if (user.is_seat_admin) return 'is-seat-admin'
  return 'is-user'
}

function kycLabel(user: User) {
  if (user.kyc_verified || user.kyc_verified_at) return 'تأیید شده'
  if (user.kyc_submission_status === 'pending') return 'در انتظار'
  if (user.kyc_submission_status === 'rejected') return 'رد شده'
  return 'ثبت نشده'
}

function kycClass(user: User) {
  if (user.kyc_verified || user.kyc_verified_at) return 'is-verified'
  if (user.kyc_submission_status === 'pending') return 'is-pending'
  if (user.kyc_submission_status === 'rejected') return 'is-rejected'
  return 'is-none'
}

function activeSeatLabel(user: User) {
  const seat = user.active_seats?.[0]
  if (!seat) return '—'
  const title = seat.tournament?.title || 'مسابقه'
  return `${seat.seat_label || seat.seat_number} · ${title}`
}

async function saveKills(user: User, kills: number) {
  try {
    await api.admin.updateUserKills(user.id, kills)
    flash.value = { success: 'کیل به‌روز شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function saveCodId(user: User, codId: string) {
  try {
    await api.admin.updateUserCodId(user.id, codId)
    flash.value = { success: 'آیدی کالاف ذخیره شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function adjustWallet(
  user: User,
  action: 'add' | 'subtract' | 'set',
  amount: number,
  description?: string,
  allowNegative?: boolean,
) {
  try {
    await api.admin.adjustUserWallet(user.id, { action, amount, description, allow_negative: allowNegative })
    flash.value = { success: 'کیف پول به‌روز شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function removeUser(user: User) {
  if (!confirm('آیا مطمئن هستید؟')) return
  try {
    await api.admin.deleteUser(user.id)
    flash.value = { success: 'کاربر حذف شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}
</script>

<template>
  <div class="admin-users-page">
    <div class="admin-users-page__header">
  <div>
        <h1 class="admin-users-page__title">مدیریت کاربران</h1>
        <p v-if="!pending && !error" class="admin-users-page__subtitle">
          {{ totalUsers.toLocaleString('fa-IR') }} نتیجه
          <span v-if="hasActiveFilters"> — فیلتر فعال</span>
        </p>
      </div>
      <NuxtLink to="/admin" class="admin-users-page__back">← داشبورد</NuxtLink>
    </div>

    <form class="admin-users-page__filters" @submit.prevent="applyFilters">
      <div class="admin-users-page__filters-row">
        <input
          v-model="search"
          type="text"
          placeholder="جستجو: نام کاربری، موبایل، ایمیل، آیدی کالاف، کد معرف، آیدی..."
          class="admin-users-page__search"
        >
        <button type="submit" class="admin-users-page__btn admin-users-page__btn--primary">جستجو</button>
        <button
          v-if="hasActiveFilters"
          type="button"
          class="admin-users-page__btn admin-users-page__btn--ghost"
          @click="resetFilters"
        >
          پاک کردن فیلترها
        </button>
      </div>

      <div class="admin-users-page__filters-row admin-users-page__filters-row--compact">
        <label class="admin-users-page__field">
          <span>نقش</span>
          <select v-model="role" class="admin-users-page__select" @change="applyFilters">
            <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </label>
        <label class="admin-users-page__field">
          <span>احراز هویت</span>
          <select v-model="kyc" class="admin-users-page__select" @change="applyFilters">
            <option v-for="opt in kycOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </label>
        <label class="admin-users-page__field">
          <span>واریز اول</span>
          <select v-model="deposit" class="admin-users-page__select" @change="applyFilters">
            <option v-for="opt in depositOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </label>
        <label class="admin-users-page__field">
          <span>مرتب‌سازی</span>
          <select v-model="sort" class="admin-users-page__select" @change="applyFilters">
            <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </label>
      </div>
    </form>

    <div v-if="pending" class="admin-users-page__state">در حال بارگذاری...</div>
    <div v-else-if="error" class="admin-users-page__state admin-users-page__state--error">
      {{ (error as Error).message }}
    </div>

    <div v-else-if="users.length === 0" class="admin-users-page__state">
      کاربری با این فیلترها یافت نشد.
    </div>

    <div v-else class="admin-users-page__list">
      <article v-for="u in users" :key="u.id" class="user-card">
        <div class="user-card__head">
          <div class="user-card__identity">
            <NuxtLink :to="`/admin/users/${u.id}/activity`" class="user-card__username">
              {{ u.username }}
            </NuxtLink>
            <span class="user-card__id">#{{ u.id.toLocaleString('fa-IR') }}</span>
            <span class="user-card__badge" :class="roleClass(u)">{{ roleLabel(u) }}</span>
            <span class="user-card__badge" :class="kycClass(u)">{{ kycLabel(u) }}</span>
            <span v-if="u.first_deposit_done" class="user-card__badge is-deposit">واریز اول ✓</span>
          </div>
          <div class="user-card__wallet">{{ formatToman(u.wallet) }}</div>
        </div>

        <div class="user-card__info">
          <div class="user-card__info-item">
            <span class="user-card__label">موبایل:</span>
            <span class="user-card__value" dir="ltr">{{ u.mobile || '—' }}</span>
          </div>
          <div class="user-card__info-item">
            <span class="user-card__label">ایمیل:</span>
            <span class="user-card__value" dir="ltr">{{ u.email || '—' }}</span>
          </div>
          <div class="user-card__info-item">
            <span class="user-card__label">عضویت:</span>
            <span class="user-card__value">{{ u.created_at_display || '—' }}</span>
          </div>
          <div class="user-card__info-item">
            <span class="user-card__label">کد معرف:</span>
            <span class="user-card__value" dir="ltr">{{ u.referral_code || '—' }}</span>
          </div>
          <div class="user-card__info-item">
            <span class="user-card__label">معرف:</span>
            <span class="user-card__value">{{ u.referrer_username || '—' }}</span>
          </div>
          <div class="user-card__info-item">
            <span class="user-card__label">ثبت‌نام‌ها:</span>
            <span class="user-card__value">{{ (u.registrations_count ?? 0).toLocaleString('fa-IR') }}</span>
          </div>
          <div class="user-card__info-item">
            <span class="user-card__label">کیل / برد / باخت:</span>
            <span class="user-card__value">{{ (u.kills ?? 0).toLocaleString('fa-IR') }} / {{ (u.wins ?? 0).toLocaleString('fa-IR') }} / {{ (u.losses ?? 0).toLocaleString('fa-IR') }}</span>
          </div>
          <div class="user-card__info-item user-card__info-item--wide">
            <span class="user-card__label">جایگاه فعال:</span>
            <span class="user-card__value">{{ activeSeatLabel(u) }}</span>
          </div>
        </div>

        <div class="user-card__actions">
          <form
            class="user-card__action-row"
            @submit.prevent="saveKills(u, Number(($event.target as HTMLFormElement).kills.value))"
          >
            <span class="user-card__action-label">کیل:</span>
            <input name="kills" type="number" :value="u.kills ?? 0" min="0" class="user-card__input user-card__input--sm">
            <button type="submit" class="user-card__mini-btn">ذخیره</button>
          </form>

          <form
            class="user-card__action-row user-card__action-row--grow"
            @submit.prevent="saveCodId(u, String(($event.target as HTMLFormElement).cod_id.value))"
          >
            <span class="user-card__action-label">آیدی کالاف:</span>
            <input name="cod_id" type="text" :value="u.cod_id || ''" dir="ltr" required class="user-card__input">
            <button type="submit" class="user-card__mini-btn">ذخیره</button>
          </form>

          <form
            class="user-card__action-row user-card__action-row--wide user-card__wallet-form"
            @submit.prevent="adjustWallet(u, ($event.target as HTMLFormElement).action.value as 'add' | 'subtract' | 'set', Number(($event.target as HTMLFormElement).amount.value), ($event.target as HTMLFormElement).description.value, ($event.target as HTMLFormElement).allow_negative?.checked)"
          >
            <span class="user-card__action-label">تنظیم کیف پول:</span>
            <select name="action" class="user-card__select user-card__select--action">
              <option value="add">+ افزایش</option>
              <option value="subtract">− کاهش</option>
              <option value="set">= تنظیم</option>
            </select>
            <span class="user-card__action-label user-card__action-label--sub">مبلغ:</span>
            <input name="amount" type="number" min="0" placeholder="مبلغ" required class="user-card__input user-card__input--amount">
            <span class="user-card__action-label user-card__action-label--sub">توضیح:</span>
            <input name="description" type="text" placeholder="توضیح" class="user-card__input user-card__input--desc">
            <label class="user-card__checkbox">
              <input name="allow_negative" type="checkbox" class="accent-primary">
              اجازه منفی
            </label>
            <button type="submit" class="user-card__mini-btn user-card__mini-btn--success">اعمال</button>
          </form>
        </div>

        <div class="user-card__footer">
          <NuxtLink :to="`/admin/users/${u.id}/activity`" class="user-card__link">تاریخچه فعالیت</NuxtLink>
          <button v-if="!u.is_admin" type="button" class="user-card__delete" @click="removeUser(u)">
            حذف کاربر
          </button>
        </div>
      </article>

      <AdminPagination v-model:page="page" :meta="data?.meta" />
    </div>
  </div>
</template>

<style scoped>
.admin-users-page__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.admin-users-page__title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 800;
  color: #fff;
}

.admin-users-page__subtitle {
  margin: 0.35rem 0 0;
  font-size: 0.82rem;
  color: #9ca3af;
}

.admin-users-page__back {
  font-size: 0.85rem;
  color: #a78bfa;
}

.admin-users-page__filters {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1rem;
  padding: 0.9rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.9rem;
  background: rgba(17, 24, 39, 0.7);
}

.admin-users-page__filters-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.admin-users-page__filters-row--compact {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.55rem;
}

@media (min-width: 900px) {
  .admin-users-page__filters-row--compact {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.admin-users-page__search {
  flex: 1 1 16rem;
  border: 1px solid rgba(75, 85, 99, 0.8);
  border-radius: 0.6rem;
  background: #111827;
  color: #fff;
  padding: 0.65rem 0.8rem;
  font-size: 0.86rem;
}

.admin-users-page__field {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.72rem;
  color: #9ca3af;
}

.admin-users-page__field span {
  white-space: nowrap;
  flex-shrink: 0;
}

.admin-users-page__field .admin-users-page__select {
  flex: 1 1 8rem;
  min-width: 0;
}

.admin-users-page__select,
.user-card__select {
  border: 1px solid rgba(75, 85, 99, 0.8);
  border-radius: 0.55rem;
  background: #111827;
  color: #fff;
  padding: 0.5rem 0.55rem;
  font-size: 0.8rem;
}

.admin-users-page__btn {
  border: none;
  border-radius: 0.6rem;
  padding: 0.65rem 0.95rem;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}

.admin-users-page__btn--primary {
  background: #7c3aed;
  color: #fff;
}

.admin-users-page__btn--ghost {
  background: #374151;
  color: #e5e7eb;
}

.admin-users-page__state {
  padding: 2rem 1rem;
  text-align: center;
  color: #9ca3af;
}

.admin-users-page__state--error {
  color: #f87171;
}

.admin-users-page__list {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.user-card {
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 1rem;
  background: rgba(17, 24, 39, 0.82);
  overflow: hidden;
}

.user-card__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.9rem 1rem;
  border-bottom: 1px solid rgba(55, 65, 81, 0.65);
  background: rgba(15, 23, 42, 0.55);
}

.user-card__identity {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
}

.user-card__username {
  font-size: 1.05rem;
  font-weight: 800;
  color: #ddd6fe;
}

.user-card__username:hover {
  text-decoration: underline;
}

.user-card__id {
  font-size: 0.75rem;
  color: #6b7280;
  font-family: ui-monospace, monospace;
}

.user-card__badge {
  display: inline-flex;
  align-items: center;
  border-radius: 9999px;
  padding: 0.12rem 0.5rem;
  font-size: 0.68rem;
  font-weight: 700;
}

.user-card__badge.is-admin {
  background: rgba(124, 58, 237, 0.22);
  color: #ddd6fe;
}

.user-card__badge.is-seat-admin {
  background: rgba(59, 130, 246, 0.2);
  color: #bfdbfe;
}

.user-card__badge.is-user {
  background: rgba(107, 114, 128, 0.25);
  color: #d1d5db;
}

.user-card__badge.is-verified {
  background: rgba(34, 197, 94, 0.18);
  color: #86efac;
}

.user-card__badge.is-pending {
  background: rgba(234, 179, 8, 0.18);
  color: #fde047;
}

.user-card__badge.is-rejected,
.user-card__badge.is-none {
  background: rgba(239, 68, 68, 0.15);
  color: #fca5a5;
}

.user-card__badge.is-deposit {
  background: rgba(16, 185, 129, 0.15);
  color: #6ee7b7;
}

.user-card__wallet {
  font-size: 1rem;
  font-weight: 800;
  color: #86efac;
  white-space: nowrap;
}

.user-card__info {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  padding: 0.9rem 1rem;
}

@media (min-width: 768px) {
  .user-card__info {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 1.25rem;
    row-gap: 0.45rem;
  }
}

@media (min-width: 1100px) {
  .user-card__info {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.user-card__info-item {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: flex-start;
  gap: 0.35rem;
  font-size: 0.8rem;
  color: #e5e7eb;
  min-width: 0;
}

.user-card__info-item--wide {
  grid-column: 1 / -1;
}

.user-card__label {
  flex-shrink: 0;
  font-size: 0.72rem;
  color: #9ca3af;
  font-weight: 700;
}

.user-card__value {
  min-width: 0;
  word-break: break-word;
}

.user-card__actions {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  padding: 0.85rem 1rem;
  border-top: 1px solid rgba(55, 65, 81, 0.55);
  background: rgba(15, 23, 42, 0.35);
}

.user-card__action-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  min-width: 0;
}

.user-card__action-row--grow {
  flex: 1 1 auto;
}

.user-card__action-row--wide {
  width: 100%;
}

.user-card__action-label {
  flex-shrink: 0;
  font-size: 0.72rem;
  color: #9ca3af;
  font-weight: 700;
  white-space: nowrap;
}

.user-card__action-label--sub {
  font-weight: 600;
  color: #6b7280;
}

.user-card__wallet-form {
  row-gap: 0.45rem;
}

.user-card__select--action {
  width: auto;
  min-width: 6.5rem;
}

.user-card__input {
  border: 1px solid rgba(75, 85, 99, 0.8);
  border-radius: 0.45rem;
  background: #111827;
  color: #fff;
  padding: 0.4rem 0.5rem;
  font-size: 0.78rem;
}

.user-card__input--sm {
  width: 5rem;
}

.user-card__input--amount {
  width: 6.5rem;
}

.user-card__input--desc {
  flex: 1 1 8rem;
  min-width: 6rem;
}

.user-card__mini-btn {
  border: none;
  border-radius: 0.45rem;
  background: #4c1d95;
  color: #ede9fe;
  padding: 0.38rem 0.65rem;
  font-size: 0.74rem;
  font-weight: 700;
  cursor: pointer;
}

.user-card__mini-btn--success {
  background: #166534;
  color: #dcfce7;
}

.user-card__checkbox {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.72rem;
  color: #9ca3af;
}

.user-card__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.7rem 1rem;
  border-top: 1px solid rgba(55, 65, 81, 0.45);
}

.user-card__link {
  font-size: 0.78rem;
  color: #a78bfa;
}

.user-card__delete {
  border: none;
  background: transparent;
  color: #f87171;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
}
</style>
