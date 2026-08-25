<script setup lang="ts">
import type { KycSubmission } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'احراز هویت | پنل مدیریت' })

const api = useApi()
const config = useRuntimeConfig()
const flash = useState<{ success?: string; error?: string } | null>('flash')

const { data, pending, error, refresh } = await useAsyncData('admin-kyc', () => api.admin.kyc())
const submissions = computed(() => data.value?.items ?? [])

const statusLabels: Record<string, string> = {
  pending: 'در انتظار بررسی',
  approved: 'تأیید شده',
  rejected: 'رد شده',
}

async function openDocument(id: number, side: string) {
  const token = localStorage.getItem('playnova_token')
  const res = await fetch(`${config.public.apiBase}/admin/kyc/${id}/document/${side}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
    credentials: 'include',
  })
  if (!res.ok) {
    flash.value = { error: 'خطا در بارگذاری تصویر' }
    return
  }
  const blob = await res.blob()
  window.open(URL.createObjectURL(blob), '_blank')
}

async function updateSubmission(s: KycSubmission, status: string, adminNote: string) {
  try {
    await api.admin.updateKyc(s.id, { status, admin_note: adminNote })
    flash.value = { success: 'وضعیت به‌روز شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">مدیریت احراز هویت (KYC)</h1>
      <NuxtLink to="/admin" class="text-sm text-secondary">← داشبورد</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="text-red-400">{{ (error as Error).message }}</div>
    <div v-else-if="!submissions.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      هنوز درخواستی ثبت نشده است.
    </div>

    <div v-else class="space-y-4">
      <div v-for="s in submissions" :key="s.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4">
        <div class="flex flex-wrap justify-between gap-2 mb-3">
          <div>
            <h3 class="font-bold text-lg text-white">{{ (s.user as { username?: string })?.username || '—' }}</h3>
            <p class="text-xs text-gray-400" dir="ltr">{{ (s.user as { mobile?: string })?.mobile }}</p>
            <span class="inline-block mt-2 text-xs px-2 py-0.5 rounded border border-dark-600 text-gray-300">{{ statusLabels[s.status] || s.status }}</span>
          </div>
          <div class="flex gap-2 flex-wrap">
            <button type="button" class="text-xs bg-secondary/20 text-secondary px-3 py-1.5 rounded" @click="openDocument(s.id, 'document')">مدارک</button>
            <button type="button" class="text-xs bg-secondary/20 text-secondary px-3 py-1.5 rounded" @click="openDocument(s.id, 'front')">روی کارت</button>
            <button type="button" class="text-xs bg-secondary/20 text-secondary px-3 py-1.5 rounded" @click="openDocument(s.id, 'back')">پشت کارت</button>
          </div>
        </div>
        <form class="flex flex-wrap gap-2 items-end border-t border-dark-600 pt-3" @submit.prevent="updateSubmission(s, ($event.target as HTMLFormElement).status.value, ($event.target as HTMLFormElement).admin_note.value)">
          <select name="status" class="bg-dark-700 border border-dark-600 rounded px-2 py-1.5 text-sm text-white">
            <option value="pending" :selected="s.status === 'pending'">در انتظار</option>
            <option value="approved" :selected="s.status === 'approved'">تأیید</option>
            <option value="rejected" :selected="s.status === 'rejected'">رد</option>
          </select>
          <input name="admin_note" type="text" :value="s.admin_note || ''" placeholder="یادداشت ادمین" class="flex-1 min-w-[200px] bg-dark-700 border border-dark-600 rounded px-2 py-1.5 text-sm text-white">
          <button type="submit" class="text-sm bg-success text-white px-4 py-1.5 rounded font-bold">ذخیره</button>
        </form>
      </div>
    </div>
  </div>
</template>
