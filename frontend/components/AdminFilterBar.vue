<script setup lang="ts">
const search = defineModel<string>('search', { default: '' })

withDefaults(
  defineProps<{
    searchPlaceholder?: string
    showReset?: boolean
    showSearch?: boolean
  }>(),
  {
    searchPlaceholder: 'جستجو...',
    showReset: false,
    showSearch: true,
  },
)

const emit = defineEmits<{ apply: []; reset: [] }>()
</script>

<template>
  <form class="admin-filter-bar" @submit.prevent="emit('apply')">
    <div v-if="showSearch" class="admin-filter-bar__row">
      <input
        v-model="search"
        type="text"
        :placeholder="searchPlaceholder"
        class="admin-filter-bar__search"
      >
      <button type="submit" class="admin-filter-bar__btn admin-filter-bar__btn--primary">جستجو</button>
      <button
        v-if="showReset"
        type="button"
        class="admin-filter-bar__btn admin-filter-bar__btn--ghost"
        @click="emit('reset')"
      >
        پاک کردن فیلترها
      </button>
    </div>
    <div v-if="$slots.filters" class="admin-filter-bar__row admin-filter-bar__row--compact">
      <slot name="filters" />
    </div>
  </form>
</template>

<style scoped>
.admin-filter-bar {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
  padding: 1rem;
  border: 1px solid rgba(75, 85, 99, 0.45);
  border-radius: 0.75rem;
  background: rgba(17, 24, 39, 0.55);
}

.admin-filter-bar__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}

.admin-filter-bar__row--compact {
  gap: 0.65rem 0.85rem;
}

.admin-filter-bar__search {
  flex: 1 1 220px;
  min-width: 0;
  background: #1f2937;
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.5rem;
  padding: 0.55rem 0.75rem;
  font-size: 0.875rem;
  color: #f3f4f6;
  outline: none;
}

.admin-filter-bar__search:focus {
  border-color: rgba(139, 92, 246, 0.65);
}

.admin-filter-bar__btn {
  border-radius: 0.5rem;
  padding: 0.55rem 0.9rem;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid transparent;
}

.admin-filter-bar__btn--primary {
  background: #7c3aed;
  color: #fff;
}

.admin-filter-bar__btn--primary:hover {
  background: #6d28d9;
}

.admin-filter-bar__btn--ghost {
  background: transparent;
  border-color: rgba(75, 85, 99, 0.7);
  color: #9ca3af;
}

.admin-filter-bar__btn--ghost:hover {
  color: #e5e7eb;
  border-color: rgba(139, 92, 246, 0.45);
}
</style>
