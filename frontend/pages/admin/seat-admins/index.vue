<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'ادمین جایگاه | PlayNova' })

interface SeatAdmin {
  id: number
  username: string
  email: string
}

const seatAdmins = ref<SeatAdmin[]>([])

const form = reactive({ email: '' })

function onSubmit() {
  /* preview only */
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">ادمین‌های مشاهده جایگاه</h1>

    <AdminApiNotice />

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
      <h2 class="font-bold mb-3 text-white">افزودن ادمین جایگاه</h2>
      <p class="text-xs text-gray-400 mb-3">
        این کاربر فقط به صفحه مشاهده جایگاه‌های هر مسابقه دسترسی دارد (بدون سایر بخش‌های ادمین).
      </p>
      <form class="flex flex-wrap gap-2" @submit.prevent="onSubmit">
        <input
          v-model="form.email"
          type="email"
          required
          placeholder="ایمیل کاربر"
          class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white min-w-[240px]"
        >
        <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold opacity-60 cursor-not-allowed" disabled>
          افزودن
        </button>
      </form>
    </div>

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
      <h2 class="font-bold mb-3 text-white">لیست ادمین‌های جایگاه</h2>
      <p v-if="!seatAdmins.length" class="text-gray-500 text-sm">ادمین جایگاهی ثبت نشده است.</p>
      <div
        v-for="admin in seatAdmins"
        :key="admin.id"
        class="flex items-center justify-between py-2 border-b border-dark-600 last:border-0"
      >
        <span>
          {{ admin.username }}
          <span class="text-gray-500 text-xs">({{ admin.email }})</span>
        </span>
        <button type="button" class="text-xs text-red-400 opacity-50 cursor-not-allowed" disabled>حذف دسترسی</button>
      </div>
    </div>
  </div>
</template>
