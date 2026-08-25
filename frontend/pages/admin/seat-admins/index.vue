<script setup lang="ts">
import type { User } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'ادمین جایگاه | PlayNova' })

const api = useApi()
const flash = useState('flash')
const { data, refresh } = await useAsyncData('admin-seat-admins', () => api.admin.seatAdmins())
const list = computed(() => (data.value ?? []) as User[])
const email = ref('')

async function add() {
  await api.admin.addSeatAdmin(email.value)
  email.value = ''
  flash.value = { success: 'اضافه شد.' }
  await refresh()
}

async function remove(userId: number) {
  await api.admin.removeSeatAdmin(userId)
  await refresh()
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">ادمین‌های جایگاه</h1>
    <form class="flex gap-2 mb-6 max-w-md" @submit.prevent="add">
      <input v-model="email" type="email" required placeholder="ایمیل" class="flex-1 bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white">
      <button type="submit" class="bg-success text-white px-4 py-2 rounded font-bold">افزودن</button>
    </form>
    <div v-for="a in list" :key="a.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4 mb-2 flex justify-between">
      <span>{{ a.username }}</span>
      <button type="button" class="text-xs text-red-400" @click="remove(a.id)">حذف</button>
    </div>
  </div>
</template>
