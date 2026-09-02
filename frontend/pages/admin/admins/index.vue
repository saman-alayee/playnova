<script setup lang="ts">
import type { User } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت ادمین‌ها | PlayNova' })

const api = useApi()
const auth = useAuthStore()
const flash = useState('flash')

const showAddForm = ref(false)
const email = ref('')
const adding = ref(false)
const removingId = ref<number | null>(null)
const errors = ref<string[]>([])

const { data, pending, refresh } = usePageData('admin-admins', () => api.admin.admins())
const admins = computed(() => (data.value ?? []) as User[])

function displayName(admin: User) {
  return admin.name?.trim() || admin.username?.trim() || '—'
}

function isCurrentUser(admin: User) {
  return admin.id === auth.user?.id
}

async function add() {
  if (!email.value.trim()) return
  adding.value = true
  errors.value = []
  try {
    await api.admin.addAdmin(email.value.trim())
    email.value = ''
    showAddForm.value = false
    flash.value = { success: 'ادمین با موفقیت اضافه شد.' }
    await refresh()
  } catch (e: unknown) {
    errors.value = [(e as Error).message || 'افزودن ادمین ناموفق بود.']
  } finally {
    adding.value = false
  }
}

async function remove(admin: User) {
  if (isCurrentUser(admin)) return
  if (!confirm(`دسترسی ادمین «${displayName(admin)}» حذف شود؟`)) return

  removingId.value = admin.id
  errors.value = []
  try {
    await api.admin.removeAdmin(admin.id)
    flash.value = { success: 'دسترسی ادمین حذف شد.' }
    await refresh()
  } catch (e: unknown) {
    errors.value = [(e as Error).message || 'حذف دسترسی ناموفق بود.']
  } finally {
    removingId.value = null
  }
}
</script>

<template>
  <div class="admins-page">
    <div class="admins-page__card">
      <div class="admins-page__header">
        <h1 class="admins-page__title">مدیریت ادمین‌ها 👑</h1>
        <button
          type="button"
          class="admins-page__add-btn"
          @click="showAddForm = !showAddForm"
        >
          + افزودن ادمین جدید
        </button>
      </div>

      <Transition name="admins-fade">
        <form
          v-if="showAddForm"
          class="admins-page__form"
          @submit.prevent="add"
        >
          <h2 class="admins-page__form-title">افزودن کاربر به عنوان ادمین</h2>
          <div class="admins-page__form-row">
            <input
              v-model="email"
              type="email"
              required
              placeholder="ایمیل کاربر"
              class="admins-page__input"
              dir="ltr"
            >
            <button type="submit" class="admins-page__submit" :disabled="adding">
              {{ adding ? 'در حال افزودن...' : 'افزودن' }}
            </button>
          </div>
        </form>
      </Transition>

      <div v-if="errors.length" class="admins-page__errors">
        <p v-for="(err, i) in errors" :key="i">{{ err }}</p>
      </div>

      <div v-if="pending" class="admins-page__state">در حال بارگذاری...</div>

      <div v-else class="admins-page__table-wrap">
        <table class="admins-page__table">
          <thead>
            <tr>
              <th>نام</th>
              <th>ایمیل</th>
              <th class="is-center">نقش</th>
              <th class="is-center">عملیات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="admin in admins" :key="admin.id">
              <td class="admins-page__name">{{ displayName(admin) }}</td>
              <td class="admins-page__email" dir="ltr">{{ admin.email || '—' }}</td>
              <td class="is-center">
                <span class="admins-page__role">ادمین</span>
              </td>
              <td class="is-center">
                <span v-if="isCurrentUser(admin)" class="admins-page__self">(شما)</span>
                <button
                  v-else
                  type="button"
                  class="admins-page__remove"
                  :disabled="removingId === admin.id"
                  @click="remove(admin)"
                >
                  {{ removingId === admin.id ? 'در حال حذف...' : '🚫 حذف دسترسی' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="!admins.length" class="admins-page__state admins-page__state--empty">
          هیچ ادمینی یافت نشد.
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admins-page {
  max-width: 56rem;
  margin: 0 auto;
}

.admins-page__card {
  padding: 1.35rem 1.25rem 1.1rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.85rem;
  background: rgba(17, 24, 39, 0.55);
  backdrop-filter: blur(6px);
}

.admins-page__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.85rem;
  margin-bottom: 1.25rem;
}

.admins-page__title {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 800;
  color: #fff;
}

.admins-page__add-btn {
  border: none;
  border-radius: 0.55rem;
  padding: 0.55rem 1rem;
  background: #16a34a;
  color: #fff;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
  white-space: nowrap;
}

.admins-page__add-btn:hover {
  background: #15803d;
  transform: translateY(-1px);
}

.admins-page__form {
  margin-bottom: 1rem;
  padding: 1rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.65rem;
  background: rgba(3, 7, 18, 0.65);
}

.admins-page__form-title {
  margin: 0 0 0.75rem;
  font-size: 0.95rem;
  font-weight: 700;
  color: #93c5fd;
}

.admins-page__form-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
}

.admins-page__input {
  flex: 1 1 12rem;
  min-width: 0;
  padding: 0.6rem 0.85rem;
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.55rem;
  background: rgba(17, 24, 39, 0.85);
  color: #f3f4f6;
  font-size: 0.875rem;
  outline: none;
}

.admins-page__input:focus {
  border-color: rgba(139, 92, 246, 0.65);
}

.admins-page__submit {
  border: none;
  border-radius: 0.55rem;
  padding: 0.6rem 1.25rem;
  background: #16a34a;
  color: #fff;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}

.admins-page__submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.admins-page__errors {
  margin-bottom: 0.85rem;
  padding: 0.65rem 0.85rem;
  border-radius: 0.55rem;
  border: 1px solid rgba(248, 113, 113, 0.45);
  background: rgba(127, 29, 29, 0.25);
  color: #fecaca;
  font-size: 0.82rem;
}

.admins-page__errors p {
  margin: 0;
}

.admins-page__state {
  padding: 2rem 1rem;
  text-align: center;
  color: #9ca3af;
  font-size: 0.875rem;
}

.admins-page__state--empty {
  border-top: 1px solid rgba(75, 85, 99, 0.35);
}

.admins-page__table-wrap {
  overflow-x: auto;
}

.admins-page__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.admins-page__table thead {
  background: rgba(3, 7, 18, 0.55);
  border-bottom: 1px solid rgba(75, 85, 99, 0.55);
}

.admins-page__table th {
  padding: 0.8rem 1rem;
  text-align: right;
  font-weight: 600;
  color: #9ca3af;
  white-space: nowrap;
}

.admins-page__table th.is-center {
  text-align: center;
}

.admins-page__table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid rgba(55, 65, 81, 0.45);
  color: #e5e7eb;
  vertical-align: middle;
}

.admins-page__table td.is-center {
  text-align: center;
}

.admins-page__table tbody tr {
  transition: background 0.15s;
}

.admins-page__table tbody tr:hover {
  background: rgba(31, 41, 55, 0.35);
}

.admins-page__name {
  font-weight: 600;
  color: #fff;
}

.admins-page__email {
  color: #d1d5db;
  font-size: 0.82rem;
}

.admins-page__role {
  display: inline-block;
  padding: 0.2rem 0.65rem;
  border-radius: 999px;
  background: rgba(147, 51, 234, 0.18);
  color: #c4b5fd;
  font-size: 0.72rem;
  font-weight: 700;
}

.admins-page__remove {
  border: none;
  background: transparent;
  color: #f87171;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: color 0.15s, opacity 0.15s;
}

.admins-page__remove:hover:not(:disabled) {
  color: #fca5a5;
}

.admins-page__remove:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.admins-page__self {
  color: #6b7280;
  font-size: 0.78rem;
}

.admins-fade-enter-active,
.admins-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.admins-fade-enter-from,
.admins-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (max-width: 640px) {
  .admins-page__header {
    flex-direction: column;
    align-items: stretch;
  }

  .admins-page__add-btn {
    width: 100%;
    text-align: center;
  }
}
</style>
