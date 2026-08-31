<script setup lang="ts">
import type { PaginationMeta } from '~/types/api'

const props = defineProps<{
  page: number
  meta?: PaginationMeta | null
}>()

const emit = defineEmits<{ 'update:page': [number] }>()

const lastPage = computed(() => props.meta?.last_page ?? 1)
const total = computed(() => props.meta?.total ?? 0)
const perPage = computed(() => props.meta?.per_page ?? 0)

const rangeStart = computed(() => {
  if (!total.value) return 0
  return (props.page - 1) * perPage.value + 1
})

const rangeEnd = computed(() => {
  if (!total.value) return 0
  return Math.min(props.page * perPage.value, total.value)
})

const pageNumbers = computed(() => {
  const last = lastPage.value
  const current = props.page
  const pages = new Set<number>([1, last, current, current - 1, current + 1])
  return [...pages]
    .filter((n) => n >= 1 && n <= last)
    .sort((a, b) => a - b)
})

function go(next: number) {
  if (next < 1 || next > lastPage.value || next === props.page) return
  emit('update:page', next)
}
</script>

<template>
  <div
    v-if="meta"
    class="admin-pagination"
    :class="{ 'admin-pagination--single': lastPage <= 1 }"
  >
    <p class="admin-pagination__summary">
      <template v-if="total">
        نمایش
        <strong>{{ rangeStart.toLocaleString('fa-IR') }}</strong>
        تا
        <strong>{{ rangeEnd.toLocaleString('fa-IR') }}</strong>
        از
        <strong>{{ total.toLocaleString('fa-IR') }}</strong>
        مورد
      </template>
      <template v-else>موردی یافت نشد</template>
    </p>

    <div v-if="lastPage > 1" class="admin-pagination__controls">
      <button
        type="button"
        class="admin-pagination__btn"
        :disabled="page <= 1"
        title="صفحه اول"
        @click="go(1)"
      >
        «
      </button>
      <button
        type="button"
        class="admin-pagination__btn"
        :disabled="page <= 1"
        @click="go(page - 1)"
      >
        قبلی
      </button>

      <div class="admin-pagination__pages">
        <template v-for="(num, index) in pageNumbers" :key="num">
          <span
            v-if="index > 0 && num - pageNumbers[index - 1] > 1"
            class="admin-pagination__ellipsis"
          >…</span>
          <button
            type="button"
            class="admin-pagination__page"
            :class="{ 'is-active': num === page }"
            @click="go(num)"
          >
            {{ num.toLocaleString('fa-IR') }}
          </button>
        </template>
      </div>

      <button
        type="button"
        class="admin-pagination__btn"
        :disabled="page >= lastPage"
        @click="go(page + 1)"
      >
        بعدی
      </button>
      <button
        type="button"
        class="admin-pagination__btn"
        :disabled="page >= lastPage"
        title="صفحه آخر"
        @click="go(lastPage)"
      >
        »
      </button>
    </div>

    <p v-else class="admin-pagination__single">صفحه ۱ از ۱</p>
  </div>
</template>

<style scoped>
.admin-pagination {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem 1rem;
  padding: 0.9rem 1rem;
  border-top: 1px solid rgba(75, 85, 99, 0.45);
  background: rgba(17, 24, 39, 0.65);
}

.admin-pagination--single {
  justify-content: center;
}

.admin-pagination__summary {
  margin: 0;
  font-size: 0.82rem;
  color: #9ca3af;
}

.admin-pagination__summary strong {
  color: #e5e7eb;
  font-weight: 800;
}

.admin-pagination__single {
  margin: 0;
  font-size: 0.78rem;
  color: #6b7280;
}

.admin-pagination__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
}

.admin-pagination__btn,
.admin-pagination__page {
  border: 1px solid rgba(75, 85, 99, 0.7);
  background: #1f2937;
  color: #e5e7eb;
  border-radius: 0.5rem;
  min-height: 2rem;
  padding: 0.35rem 0.7rem;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.admin-pagination__page {
  min-width: 2rem;
  padding-inline: 0.55rem;
}

.admin-pagination__btn:hover:not(:disabled),
.admin-pagination__page:hover:not(.is-active) {
  background: #374151;
  border-color: rgba(167, 139, 250, 0.45);
}

.admin-pagination__page.is-active {
  background: #7c3aed;
  border-color: #8b5cf6;
  color: #fff;
}

.admin-pagination__btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.admin-pagination__pages {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.admin-pagination__ellipsis {
  color: #6b7280;
  padding-inline: 0.15rem;
  user-select: none;
}
</style>
