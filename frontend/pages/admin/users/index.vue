<script setup lang="ts">
import type { User } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت کاربران | PlayNova' })

const api = useApi()
const flash = useState<{ success?: string; error?: string } | null>('flash')
const search = ref('')
const page = ref(1)
const route = useRoute()
const router = useRouter()

if (typeof route.query.search === 'string') search.value = route.query.search

const { data, pending, error, refresh } = await useAsyncData(
  'admin-users',
  () => api.admin.users({
    page: page.value,
    ...(search.value ? { search: search.value } : {}),
  }),
  { watch: [page] },
)

const users = computed(() => data.value?.items ?? [])

function applySearch() {
  page.value = 1
  router.replace({ query: search.value ? { search: search.value } : {} })
  refresh()
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
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">مدیریت کاربران</h1>
      <NuxtLink to="/admin" class="text-sm text-secondary">← داشبورد</NuxtLink>
    </div>

    <form class="mb-4 flex gap-2 max-w-md" @submit.prevent="applySearch">
      <input v-model="search" type="text" placeholder="جستجو..." class="flex-1 bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <button type="submit" class="bg-secondary text-white px-3 py-2 rounded text-sm">جستجو</button>
    </form>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="text-red-400">{{ (error as Error).message }}</div>

    <div v-else class="bg-dark-800 border border-dark-600 rounded-xl overflow-x-auto">
      <table class="w-full text-sm min-w-[960px]">
        <thead>
          <tr class="bg-dark-700 text-gray-400">
            <th class="py-2 px-3 text-right">آیدی</th>
            <th class="py-2 px-3 text-right">نام کاربری</th>
            <th class="py-2 px-3 text-right">ایمیل/موبایل</th>
            <th class="py-2 px-3 text-right">کیل</th>
            <th class="py-2 px-3 text-right">آیدی کالاف</th>
            <th class="py-2 px-3 text-right">کیف پول</th>
            <th class="py-2 px-3 text-right">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id" class="border-b border-dark-700 align-top">
            <td class="py-2 px-3">{{ u.id }}</td>
            <td class="py-2 px-3">
              <NuxtLink :to="`/admin/users/${u.id}/activity`" class="text-secondary hover:underline">{{ u.username }}</NuxtLink> <span v-if="u.is_admin" class="text-primary text-xs">(ادمین)</span></td>
            <td class="py-2 px-3">{{ u.email || u.mobile }}</td>
            <td class="py-2 px-3">
              <form class="flex gap-1 items-center" @submit.prevent="saveKills(u, Number(($event.target as HTMLFormElement).kills.value))">
                <input name="kills" type="number" :value="u.kills ?? 0" min="0" class="w-16 bg-dark-700 border border-dark-600 rounded px-1 py-0.5 text-xs text-white">
                <button type="submit" class="text-xs text-secondary">✓</button>
              </form>
            </td>
            <td class="py-2 px-3">
              <form class="space-y-1 min-w-[140px]" @submit.prevent="saveCodId(u, String(($event.target as HTMLFormElement).cod_id.value))">
                <input name="cod_id" type="text" :value="u.cod_id || ''" dir="ltr" required class="w-full bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs font-mono text-white">
                <button type="submit" class="text-xs text-secondary">ذخیره آیدی</button>
              </form>
            </td>
            <td class="py-2 px-3 whitespace-nowrap">{{ Number(u.wallet).toLocaleString('fa-IR') }}</td>
            <td class="py-2 px-3">
              <form
                class="flex flex-wrap gap-1 items-center mb-2"
                @submit.prevent="adjustWallet(u, ($event.target as HTMLFormElement).action.value as 'add' | 'subtract' | 'set', Number(($event.target as HTMLFormElement).amount.value), ($event.target as HTMLFormElement).description.value, ($event.target as HTMLFormElement).allow_negative?.checked)"
              >
                <select name="action" class="bg-dark-700 border border-dark-600 rounded px-1 py-0.5 text-xs text-white">
                  <option value="add">+ افزایش</option>
                  <option value="subtract">− کاهش</option>
                  <option value="set">= تنظیم</option>
                </select>
                <input name="amount" type="number" min="0" placeholder="مبلغ" required class="w-24 bg-dark-700 border border-dark-600 rounded px-1 py-0.5 text-xs text-white">
                <input name="description" type="text" placeholder="توضیح" class="w-28 bg-dark-700 border border-dark-600 rounded px-1 py-0.5 text-xs text-white">
                <label class="text-xs text-gray-400 flex items-center gap-1">
                  <input name="allow_negative" type="checkbox" class="accent-primary">
                  منفی
                </label>
                <button type="submit" class="text-xs text-success">اعمال</button>
              </form>
              <button v-if="!u.is_admin" type="button" class="text-xs text-red-400" @click="removeUser(u)">حذف</button>
            </td>
          </tr>
        </tbody>
      </table>
      <AdminPagination v-model:page="page" :meta="data?.meta" />
    </div>
  </div>
</template>
