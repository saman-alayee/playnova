<script setup lang="ts">
import type { User } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'ادمین جایگاه | PlayNova' })

const api = useApi()
const flash = useState('flash')

const email = ref('')
const adding = ref(false)
const removingId = ref<number | null>(null)
const errors = ref<string[]>([])

const { data, pending, refresh } = await useAsyncData('admin-seat-admins', () => api.admin.seatAdmins())
const list = computed(() => (data.value ?? []) as User[])

function displayLine(admin: User) {
  const name = admin.name?.trim() || admin.username?.trim() || '—'
  const mail = admin.email?.trim()
  return mail ? `${name} (${mail})` : name
}

async function add() {
  if (!email.value.trim()) return
  adding.value = true
  errors.value = []
  try {
    await api.admin.addSeatAdmin(email.value.trim())
    email.value = ''
    flash.value = { success: 'ادمین جایگاه با موفقیت اضافه شد.' }
    await refresh()
  } catch (e: unknown) {
    errors.value = [(e as Error).message || 'افزودن ادمین جایگاه ناموفق بود.']
  } finally {
    adding.value = false
  }
}

async function remove(admin: User) {
  if (!confirm(`دسترسی ادمین جایگاه «${displayLine(admin)}» حذف شود؟`)) return

  removingId.value = admin.id
  errors.value = []
  try {
    await api.admin.removeSeatAdmin(admin.id)
    flash.value = { success: 'دسترسی ادمین جایگاه حذف شد.' }
    await refresh()
  } catch (e: unknown) {
    errors.value = [(e as Error).message || 'حذف دسترسی ناموفق بود.']
  } finally {
    removingId.value = null
  }
}
</script>

<template>
  <div class="seat-admins-page">
    <h1 class="seat-admins-page__heading">ادمین‌های مشاهده جایگاه</h1>

    <div v-if="errors.length" class="seat-admins-page__errors">
      <p v-for="(err, i) in errors" :key="i">{{ err }}</p>
    </div>

    <section class="seat-admins-card">
      <h2 class="seat-admins-card__title">افزودن ادمین جایگاه</h2>
      <p class="seat-admins-card__hint">
        این کاربر فقط به صفحه مشاهده جایگاه‌های هر مسابقه دسترسی دارد (بدون سایر بخش‌های ادمین).
      </p>

      <form class="seat-admins-card__form" @submit.prevent="add">
        <input
          v-model="email"
          type="email"
          required
          placeholder="ایمیل کاربر"
          class="seat-admins-card__input"
          dir="ltr"
        >
        <button type="submit" class="seat-admins-card__submit" :disabled="adding">
          {{ adding ? 'در حال افزودن...' : 'افزودن' }}
        </button>
      </form>
    </section>

    <section class="seat-admins-card">
      <h2 class="seat-admins-card__title">لیست ادمین‌های جایگاه</h2>

      <div v-if="pending" class="seat-admins-card__state">در حال بارگذاری...</div>

      <div v-else-if="!list.length" class="seat-admins-card__state seat-admins-card__state--empty">
        ادمین جایگاهی ثبت نشده است.
      </div>

      <ul v-else class="seat-admins-list">
        <li v-for="admin in list" :key="admin.id" class="seat-admins-list__item">
          <span class="seat-admins-list__name">
            {{ admin.name || admin.username || '—' }}
            <span v-if="admin.email" class="seat-admins-list__email">({{ admin.email }})</span>
          </span>
          <button
            type="button"
            class="seat-admins-list__remove"
            :disabled="removingId === admin.id"
            @click="remove(admin)"
          >
            {{ removingId === admin.id ? 'در حال حذف...' : 'حذف دسترسی' }}
          </button>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.seat-admins-page {
  max-width: 48rem;
}

.seat-admins-page__heading {
  margin: 0 0 1.35rem;
  font-size: 1.5rem;
  font-weight: 800;
  color: #fff;
}

.seat-admins-page__errors {
  margin-bottom: 1rem;
  padding: 0.65rem 0.85rem;
  border-radius: 0.55rem;
  border: 1px solid rgba(248, 113, 113, 0.45);
  background: rgba(127, 29, 29, 0.25);
  color: #fecaca;
  font-size: 0.82rem;
}

.seat-admins-page__errors p {
  margin: 0;
}

.seat-admins-card {
  margin-bottom: 1rem;
  padding: 1.25rem 1.15rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.85rem;
  background: rgba(17, 24, 39, 0.65);
}

.seat-admins-card__title {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  font-weight: 800;
  color: #fff;
}

.seat-admins-card__hint {
  margin: 0 0 0.85rem;
  font-size: 0.78rem;
  line-height: 1.75;
  color: #9ca3af;
}

.seat-admins-card__form {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  align-items: center;
}

.seat-admins-card__input {
  flex: 1 1 15rem;
  min-width: 0;
  min-height: 2.5rem;
  padding: 0.6rem 0.85rem;
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.55rem;
  background: rgba(31, 41, 55, 0.85);
  color: #f3f4f6;
  font-size: 0.875rem;
  outline: none;
}

.seat-admins-card__input:focus {
  border-color: rgba(139, 92, 246, 0.65);
}

.seat-admins-card__submit {
  border: none;
  border-radius: 0.55rem;
  padding: 0.6rem 1.25rem;
  min-height: 2.5rem;
  background: #16a34a;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 800;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
}

.seat-admins-card__submit:hover:not(:disabled) {
  background: #15803d;
}

.seat-admins-card__submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.seat-admins-card__state {
  padding: 1rem 0;
  text-align: center;
  color: #9ca3af;
  font-size: 0.875rem;
}

.seat-admins-card__state--empty {
  text-align: right;
}

.seat-admins-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.seat-admins-list__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid rgba(55, 65, 81, 0.55);
  transition: background 0.15s;
}

.seat-admins-list__item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.seat-admins-list__item:first-child {
  padding-top: 0.15rem;
}

.seat-admins-list__item:hover {
  background: rgba(31, 41, 55, 0.25);
}

.seat-admins-list__name {
  min-width: 0;
  font-size: 0.9rem;
  font-weight: 600;
  color: #fff;
  word-break: break-word;
}

.seat-admins-list__email {
  margin-right: 0.2rem;
  font-size: 0.78rem;
  font-weight: 500;
  color: #9ca3af;
  direction: ltr;
  unicode-bidi: embed;
}

.seat-admins-list__remove {
  flex-shrink: 0;
  border: none;
  background: transparent;
  color: #f87171;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: color 0.15s, opacity 0.15s;
}

.seat-admins-list__remove:hover:not(:disabled) {
  color: #fca5a5;
}

.seat-admins-list__remove:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .seat-admins-list__item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.45rem;
  }

  .seat-admins-card__submit {
    width: 100%;
  }
}
</style>
