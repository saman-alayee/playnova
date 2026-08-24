<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'پیام همگانی | PlayNova' })

const form = reactive({
  title: '',
  message: '',
})

function onSubmit() {
  /* preview only */
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">ارسال پیام همگانی</h1>

    <AdminApiNotice />

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 max-w-xl">
      <form class="space-y-3" @submit.prevent="onSubmit">
        <input
          v-model="form.title"
          type="text"
          placeholder="عنوان پیام"
          required
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
        >
        <textarea
          v-model="form.message"
          rows="5"
          placeholder="متن پیام"
          required
          class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
        />
        <button type="submit" class="bg-success hover:opacity-90 text-white rounded px-4 py-2 font-bold w-full opacity-60 cursor-not-allowed" disabled>
          ارسال به همه کاربران
        </button>
      </form>
    </div>
  </div>
</template>
