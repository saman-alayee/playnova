<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت ادمین‌ها | پنل مدیریت' })

interface AdminUser {
  id: number
  name?: string
  username?: string
  email: string
}

const auth = useAuthStore()
const showAddForm = ref(false)
const admins = ref<AdminUser[]>([])

const form = reactive({ email: '' })

function onSubmit() {
  /* preview only */
}
</script>

<template>
  <div class="max-w-4xl mx-auto">
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-primary">👑 مدیریت ادمین‌ها</h2>
        <button type="button" class="btn-glow-success text-sm" @click="showAddForm = !showAddForm">
          ➕ افزودن ادمین جدید
        </button>
      </div>

      <AdminApiNotice />

      <div
        v-show="showAddForm"
        class="bg-dark-900/50 p-4 rounded-lg border border-gray-700 mb-6"
      >
        <h3 class="text-lg font-bold text-secondary mb-3">افزودن کاربر به عنوان ادمین</h3>
        <form class="flex flex-wrap gap-3" @submit.prevent="onSubmit">
          <input
            v-model="form.email"
            type="email"
            placeholder="ایمیل کاربر"
            required
            class="flex-1 min-w-[200px] bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary"
          >
          <button type="submit" class="btn-glow-success px-6 py-2 text-sm opacity-60 cursor-not-allowed" disabled>
            افزودن
          </button>
        </form>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-dark-900/50 border-b border-gray-700">
            <tr>
              <th class="text-right py-3 px-4 text-gray-400">نام</th>
              <th class="text-right py-3 px-4 text-gray-400">ایمیل</th>
              <th class="text-center py-3 px-4 text-gray-400">نقش</th>
              <th class="text-center py-3 px-4 text-gray-400">عملیات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!admins.length">
              <td colspan="4" class="py-8 text-center text-gray-500 text-sm">هیچ ادمینی یافت نشد.</td>
            </tr>
            <tr
              v-for="admin in admins"
              :key="admin.id"
              class="border-b border-gray-700/50 hover:bg-gray-800/30 transition"
            >
              <td class="py-3 px-4">{{ admin.name ?? admin.username ?? '-' }}</td>
              <td class="py-3 px-4">{{ admin.email }}</td>
              <td class="py-3 px-4 text-center">
                <span class="px-2 py-1 rounded-full text-xs bg-primary/20 text-primary">ادمین</span>
              </td>
              <td class="py-3 px-4 text-center">
                <span v-if="admin.id === auth.user?.id" class="text-gray-500 text-xs">(شما)</span>
                <span v-else class="text-danger opacity-50 text-sm">🚫 حذف دسترسی</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
