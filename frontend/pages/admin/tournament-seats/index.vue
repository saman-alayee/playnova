<script setup lang="ts">
import type { Tournament } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'جایگاه‌های مسابقات | PlayNova' })

const api = useApi()
const { data, pending } = await useAsyncData('admin-tournament-seats-list', () => api.admin.tournamentSeats())
const tournaments = computed(() => (data.value ?? []) as Tournament[])

function formatCount(n?: number | null) {
  return Number(n ?? 0).toLocaleString('fa-IR')
}

function metaLine(tournament: Tournament) {
  const status = tournament.status_label || tournament.status || '—'
  const registered = formatCount(tournament.registered_count ?? tournament.registrations_count ?? 0)
  const capacity = formatCount(tournament.capacity ?? 0)
  const mode = tournament.seat_mode_label || '—'

  return `${status} — ${registered}/${capacity} — ${mode}`
}
</script>

<template>
  <div class="seat-list-page">
    <h1 class="seat-list-page__title">نقشه جایگاه‌های مسابقات</h1>

    <div v-if="pending" class="seat-list-page__state">در حال بارگذاری...</div>

    <div v-else-if="!tournaments.length" class="seat-list-page__state seat-list-page__state--empty">
      مسابقه فعالی برای نمایش جایگاه وجود ندارد.
    </div>

    <div v-else class="seat-list-page__list">
      <NuxtLink
        v-for="t in tournaments"
        :key="t.id"
        :to="`/admin/tournament-seats/${t.id}`"
        class="seat-tournament-card"
      >
        <div class="seat-tournament-card__info">
          <h2 class="seat-tournament-card__name">{{ t.title }}</h2>
          <p class="seat-tournament-card__meta">{{ metaLine(t) }}</p>
        </div>
        <span class="seat-tournament-card__action">مشاهده نقشه جایگاه‌ها</span>
      </NuxtLink>
    </div>
  </div>
</template>

<style scoped>
.seat-list-page__title {
  margin: 0 0 1.25rem;
  font-size: 1.5rem;
  font-weight: 800;
  color: #fff;
}

.seat-list-page__state {
  padding: 2rem 1rem;
  text-align: center;
  color: #9ca3af;
  font-size: 0.9rem;
}

.seat-list-page__state--empty {
  border: 1px dashed rgba(75, 85, 99, 0.45);
  border-radius: 0.75rem;
  background: rgba(17, 24, 39, 0.35);
}

.seat-list-page__list {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.seat-tournament-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.1rem;
  border: 1px solid rgba(55, 65, 81, 0.65);
  border-radius: 0.75rem;
  background: #11111d;
  color: inherit;
  text-decoration: none;
  transition: border-color 0.15s, background 0.15s;
}

.seat-tournament-card:hover {
  border-color: rgba(107, 114, 128, 0.75);
  background: #151522;
}

.seat-tournament-card__info {
  min-width: 0;
  text-align: right;
}

.seat-tournament-card__name {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.5;
  color: #fff;
}

.seat-tournament-card__meta {
  margin: 0.2rem 0 0;
  font-size: 0.78rem;
  line-height: 1.6;
  color: #9ca3af;
}

.seat-tournament-card__action {
  flex-shrink: 0;
  font-size: 0.9rem;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
}

@media (max-width: 640px) {
  .seat-tournament-card {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }

  .seat-tournament-card__action {
    text-align: left;
  }
}
</style>
