<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'ارسال اعلان | PlayNova' })

const api = useApi()
const flash = useState<{ success?: string; error?: string } | null>('flash')

const tab = ref<'broadcast' | 'personal'>('broadcast')
const loading = ref(false)

const broadcastForm = reactive({ title: '', message: '' })
const personalForm = reactive({ search: '', title: '', message: '' })

async function submitBroadcast() {
  loading.value = true
  try {
    await api.admin.sendBroadcast(broadcastForm)
    flash.value = { success: 'اعلان کلی در صف ارسال قرار گرفت.' }
    broadcastForm.title = ''
    broadcastForm.message = ''
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    loading.value = false
  }
}

async function submitPersonal() {
  loading.value = true
  try {
    await api.admin.sendPersonalNotification(personalForm)
    flash.value = { success: 'اعلان شخصی در صف ارسال قرار گرفت.' }
    personalForm.search = ''
    personalForm.title = ''
    personalForm.message = ''
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <h1 class="text-2xl font-bold text-white">ارسال اعلان</h1>
      <NuxtLink to="/admin/broadcast-manage" class="text-sm text-secondary">مدیریت اعلان‌ها →</NuxtLink>
    </div>

    <div class="flex gap-2 mb-6">
      <button
        type="button"
        class="text-sm px-4 py-2 rounded-lg transition"
        :class="tab === 'broadcast' ? 'bg-secondary text-white' : 'bg-dark-700 text-gray-400'"
        @click="tab = 'broadcast'"
      >
        اعلان کلی
      </button>
      <button
        type="button"
        class="text-sm px-4 py-2 rounded-lg transition"
        :class="tab === 'personal' ? 'bg-secondary text-white' : 'bg-dark-700 text-gray-400'"
        @click="tab = 'personal'"
      >
        اعلان شخصی
      </button>
    </div>

    <form
      v-if="tab === 'broadcast'"
      class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-3"
      @submit.prevent="submitBroadcast"
    >
      <p class="text-sm text-gray-400">این پیام برای همه کاربران ارسال می‌شود.</p>
      <input
        v-model="broadcastForm.title"
        required
        placeholder="عنوان"
        class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
      >
      <textarea
        v-model="broadcastForm.message"
        required
        rows="5"
        placeholder="متن اعلان"
        class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
      />
      <button type="submit" class="bg-success text-white rounded px-4 py-2 font-bold" :disabled="loading">
        ارسال اعلان کلی
      </button>
    </form>

    <form
      v-else
      class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-3"
      @submit.prevent="submitPersonal"
    >
      <p class="text-sm text-gray-400">شناسه کاربر، موبایل، نام کاربری یا ایمیل را وارد کنید.</p>
      <input
        v-model="personalForm.search"
        required
        placeholder="مثلاً 0912... یا username"
        class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
      >
      <input
        v-model="personalForm.title"
        required
        placeholder="عنوان"
        class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
      >
      <textarea
        v-model="personalForm.message"
        required
        rows="5"
        placeholder="متن اعلان"
        class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white"
      />
      <button type="submit" class="bg-primary text-white rounded px-4 py-2 font-bold" :disabled="loading">
        ارسال اعلان شخصی
      </button>
    </form>
  </div>
</template>
