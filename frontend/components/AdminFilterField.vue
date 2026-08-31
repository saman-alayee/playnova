<template>
  <label class="admin-filter-field">
    <span v-if="$slots.default"><slot /></span>
    <span v-else-if="label">{{ label }}</span>
    <slot name="control" />
  </label>
</template>

<script setup lang="ts">
defineProps<{ label?: string }>()
</script>

<style scoped>
.admin-filter-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.72rem;
  color: #9ca3af;
  min-width: 9rem;
}

.admin-filter-field :deep(select),
.admin-filter-field :deep(input:not([type='checkbox'])) {
  background: #1f2937;
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.45rem;
  padding: 0.45rem 0.55rem;
  font-size: 0.82rem;
  color: #f3f4f6;
  outline: none;
}

.admin-filter-field :deep(select:focus),
.admin-filter-field :deep(input:not([type='checkbox']):focus) {
  border-color: rgba(139, 92, 246, 0.65);
}
</style>
