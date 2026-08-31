<script setup lang="ts">
import type { PaginationMeta } from '~/types/api'

const props = defineProps<{
  page: number
  meta?: PaginationMeta | null
}>()

const emit = defineEmits<{ 'update:page': [number] }>()

function go(next: number) {
  const last = props.meta?.last_page ?? 1
  if (next < 1 || next > last) return
  emit('update:page', next)
}
</script>

<template>
  <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-3 mt-4">
    <button
      type="button"
      class="text-xs px-3 py-1 rounded bg-dark-700 text-gray-300 disabled:opacity-40"
      :disabled="page <= 1"
      @click="go(page - 1)"
    >
      قبلی
    </button>
    <span class="text-xs text-gray-400">
      {{ page.toLocaleString('fa-IR') }} / {{ meta.last_page.toLocaleString('fa-IR') }}
      <span v-if="meta.total" class="text-gray-500">({{ meta.total.toLocaleString('fa-IR') }} مورد)</span>
    </span>
    <button
      type="button"
      class="text-xs px-3 py-1 rounded bg-dark-700 text-gray-300 disabled:opacity-40"
      :disabled="page >= meta.last_page"
      @click="go(page + 1)"
    >
      بعدی
    </button>
  </div>
</template>
