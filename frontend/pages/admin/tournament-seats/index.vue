<script setup lang="ts">
import type { Tournament } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'جایگاه‌های مسابقات | PlayNova' })

const auth = useAuthStore()
const api = useApi()

const { data: tournaments, pending, error } = await useAsyncData('admin-tournament-seats', () =>
  api.admin.tournaments(),
  { default: () => [] as Tournament[] },
)

function registeredCount(t: Tournament) {
  return t.registered_count ?? t.registrations_count ?? 0
}

function seatModeLabel(mode?: number) {
  if (mode === 1) return 'انتخاب جایگاه'
  if (mode === 2) return 'تخصیص خودکار'
  return 'بدون جایگاه'
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">مشاهده جایگاه‌های مسابقات</h1>

    <p v-if="!auth.isAdmin" class="text-sm text-gray-400 mb-4">فقط مشاهده جایگاه‌های هر مسابقه</p>

    <AdminApiNotice v-if="auth.isAdmin" message="لیست مسابقات از Admin API خوانده می‌شود. مشاهده نقشه جایگاه‌ها نیاز به توسعه Admin API دارد." />

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-amber-600/40 rounded-xl p-6 text-amber-200">
      API `/api/v1/admin/tournaments` در دسترس نیست.
    </div>
    <div v-else-if="!tournaments?.length" class="text-gray-500">مسابقه‌ای برای نمایش وجود ندارد.</div>
    <div v-else class="space-y-3">
      <div
        v-for="t in tournaments"
        :key="t.id"
        class="bg-dark-800 border border-dark-600 rounded-xl p-4 flex flex-wrap items-center justify-between gap-3"
      >
        <div>
          <h3 class="font-bold text-white">{{ t.title }}</h3>
          <p class="text-xs text-gray-400 mt-1">
            {{ t.status_label || t.status }} — {{ registeredCount(t) }}/{{ t.capacity }} — {{ seatModeLabel(t.seat_mode) }}
          </p>
        </div>
        <span class="bg-secondary text-white rounded px-4 py-2 text-sm font-bold opacity-50 cursor-not-allowed">
          مشاهده نقشه جایگاه‌ها
        </span>
      </div>
    </div>
  </div>
</template>
