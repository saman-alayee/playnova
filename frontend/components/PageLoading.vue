<template>
  <div class="page-loading" role="status" aria-live="polite">
    <span class="page-loading__spinner" aria-hidden="true" />
    <p class="page-loading__text">در حال بارگذاری...</p>
  </div>
</template>

<style scoped>
.page-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.85rem;
  padding: 3.5rem 1rem;
}

.page-loading__spinner {
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 999px;
  border: 3px solid rgba(139, 92, 246, 0.22);
  border-top-color: #8b5cf6;
  animation: page-loading-spin 0.7s linear infinite;
}

.page-loading__text {
  margin: 0;
  color: #9ca3af;
  font-size: 0.9rem;
}

@keyframes page-loading-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
