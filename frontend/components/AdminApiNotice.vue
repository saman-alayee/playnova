<script setup lang="ts">
withDefaults(
  defineProps<{
    message?: string
  }>(),
  {
    message:
      'عملیات نوشتن (ایجاد، ویرایش، حذف) نیاز به توسعه Admin API در Laravel دارد. UI فقط برای پیش‌نمایش است.',
  },
)
</script>

<template>
  <div class="mb-4 rounded-lg border border-amber-600/40 bg-amber-900/20 px-4 py-3 text-sm text-amber-200">
    {{ message }}
  </div>
</template>
