<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'لوگو | PlayNova' })

const api = useApi()
const flash = useState('flash')
const { data, refresh } = await useAsyncData('admin-logo', () => api.admin.logo())
const file = ref<File | null>(null)

async function upload() {
  if (!file.value) return
  const fd = new FormData()
  fd.append('logo', file.value)
  await api.admin.updateLogo(fd)
  flash.value = { success: 'لوگو به‌روز شد.' }
  await refresh()
}

async function remove() {
  await api.admin.deleteLogo()
  flash.value = { success: 'لوگو حذف شد.' }
  await refresh()
}
</script>

<template>
  <div class="max-w-md">
    <h1 class="text-2xl font-bold mb-6 text-white">مدیریت لوگو</h1>
    <img v-if="data?.logo_url" :src="data.logo_url" alt="لوگو" class="max-h-24 mb-4">
    <input type="file" accept="image/*" class="text-sm text-gray-400 mb-3" @change="file = ($event.target as HTMLInputElement).files?.[0] ?? null">
    <div class="flex gap-2">
      <button type="button" class="bg-success text-white rounded px-4 py-2 text-sm" @click="upload">آپلود</button>
      <button type="button" class="bg-danger text-white rounded px-4 py-2 text-sm" @click="remove">حذف</button>
    </div>
  </div>
</template>
