<script setup lang="ts">
import type { Tournament } from '~/types/api'

const props = defineProps<{
  tournament: Tournament
  heroImage: string
}>()

const regCount = computed(() => {
  const count = props.tournament.registrations_count ?? props.tournament.registered_count ?? 0
  return Math.max(0, Number(count))
})

const capacity = computed(() => Math.max(1, Number(props.tournament.capacity ?? 1)))
</script>

<template>
  <article class="special-card snap-start">
    <div class="special-card__img" :style="{ backgroundImage: `url('${heroImage}')` }" />
    <div class="special-card__body">
      <h3 class="font-bold text-sm text-white mb-1 truncate">{{ tournament.title }}</h3>
      <TournamentSchedule :tournament="tournament" class="text-xs text-gray-400 mb-1" />
      <TournamentStats
        :tournament="tournament"
        :reg-count="regCount"
        :capacity="capacity"
      />
      <TournamentActions
        :tournament="tournament"
        :reg-count="regCount"
        :capacity="capacity"
        compact
      />
    </div>
  </article>
</template>
