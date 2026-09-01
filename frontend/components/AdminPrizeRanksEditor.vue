<script setup lang="ts">
export interface PrizeRankRow {
  rank: number
  amount: number | ''
}

const props = defineProps<{
  budget: number
  seatMode?: number
}>()

const ranks = defineModel<PrizeRankRow[]>({ default: () => [] })

const isTeamMode = computed(() => (props.seatMode ?? 1) > 1)

const sum = computed(() =>
  ranks.value.reduce((total, row) => total + Number(row.amount || 0), 0),
)

const remaining = computed(() => Number(props.budget || 0) - sum.value)

const matchesBudget = computed(() => {
  if (!props.budget) return sum.value === 0
  return Math.abs(remaining.value) < 1
})

function addRank() {
  const next = (ranks.value.at(-1)?.rank || 0) + 1
  ranks.value = [...ranks.value, { rank: next, amount: '' }]
}

function removeRank(index: number) {
  ranks.value = ranks.value.filter((_, i) => i !== index)
}

function rankLabel(rank: number) {
  if (isTeamMode.value) {
    const labels: Record<number, string> = {
      1: 'تیم اول',
      2: 'تیم دوم',
      3: 'تیم سوم',
    }
    return labels[rank] || `تیم ${rank.toLocaleString('fa-IR')}`
  }

  const labels: Record<number, string> = {
    1: 'نفر اول',
    2: 'نفر دوم',
    3: 'نفر سوم',
  }
  return labels[rank] || `نفر ${rank.toLocaleString('fa-IR')}`
}
</script>

<template>
  <div class="prize-ranks">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
      <p class="text-sm text-gray-300">توزیع جوایز (مجموع باید برابر بودجه باشد)</p>
      <button type="button" class="text-xs text-secondary" @click="addRank">+ رتبه</button>
    </div>
    <p v-if="isTeamMode" class="text-xs text-gray-500 mb-2">
      در بازی تیمی این مبلغ جایزه کل تیم است و بین اعضای همان تیم تقسیم می‌شود.
    </p>

    <div class="space-y-2">
      <div v-for="(row, index) in ranks" :key="index" class="grid grid-cols-[7rem_1fr_auto] gap-2 items-center">
        <span class="text-xs text-gray-400">{{ rankLabel(row.rank) }}</span>
        <input
          v-model.number="row.amount"
          type="number"
          min="0"
          class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
          :placeholder="`جایزه ${rankLabel(row.rank)}`"
        >
        <button type="button" class="text-xs text-red-400" @click="removeRank(index)">حذف</button>
      </div>
    </div>

    <p class="text-xs mt-2" :class="matchesBudget ? 'text-green-400' : 'text-amber-300'">
      مجموع جوایز: {{ sum.toLocaleString('fa-IR') }} تومان
      —
      بودجه: {{ Number(budget || 0).toLocaleString('fa-IR') }} تومان
      <span v-if="!matchesBudget">
        (اختلاف: {{ remaining.toLocaleString('fa-IR') }} تومان)
      </span>
    </p>
  </div>
</template>
