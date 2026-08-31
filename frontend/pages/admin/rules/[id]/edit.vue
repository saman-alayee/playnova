<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const flash = useState('flash')
const id = Number(route.params.id)
const saving = ref(false)

const { data: rules, pending } = await useAsyncData('admin-rules-edit', () => api.admin.rules())
const rule = computed(() => rules.value?.find((r) => r.id === id))
const content = ref(rule.value?.content || '')

watch(rule, (r) => {
  if (r) content.value = r.content
}, { immediate: true })

useHead({ title: () => `ویرایش بخش #${id} | قوانین` })

async function save() {
  if (!content.value.trim()) return
  saving.value = true
  try {
    await api.admin.updateRule(id, content.value)
    flash.value = { success: 'بخش ذخیره شد.' }
    await router.push('/admin/rules/manage')
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="rules-edit max-w-3xl">
    <div class="rules-edit__header">
      <div>
        <h1 class="rules-edit__title">ویرایش بخش قوانین</h1>
        <p v-if="rule" class="rules-edit__subtitle">شناسه #{{ rule.id.toLocaleString('fa-IR') }}</p>
      </div>
      <NuxtLink to="/admin/rules/manage" class="rules-edit__back">← بازگشت به لیست</NuxtLink>
    </div>

    <div v-if="pending" class="rules-edit__state">در حال بارگذاری...</div>
    <div v-else-if="!rule" class="rules-edit__state">بخش یافت نشد.</div>

    <form v-else class="rules-edit__form" @submit.prevent="save">
      <label class="rules-edit__label" for="rule-content">متن بخش</label>
      <textarea
        id="rule-content"
        v-model="content"
        rows="14"
        required
        class="rules-edit__textarea"
        placeholder="متن قوانین را وارد کنید..."
      />
      <div class="rules-edit__actions">
        <NuxtLink to="/admin/rules/manage" class="rules-edit__btn rules-edit__btn--ghost">
          انصراف
        </NuxtLink>
        <button type="submit" class="rules-edit__btn rules-edit__btn--save" :disabled="saving">
          {{ saving ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.rules-edit__header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.rules-edit__title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 800;
  color: #fff;
}

.rules-edit__subtitle {
  margin: 0.35rem 0 0;
  font-size: 0.82rem;
  color: #9ca3af;
}

.rules-edit__back {
  font-size: 0.875rem;
  color: #a78bfa;
  text-decoration: none;
}

.rules-edit__back:hover {
  text-decoration: underline;
}

.rules-edit__state {
  padding: 2rem 1rem;
  text-align: center;
  color: #9ca3af;
}

.rules-edit__form {
  padding: 1.25rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.85rem;
  background: rgba(17, 24, 39, 0.65);
}

.rules-edit__label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.82rem;
  font-weight: 700;
  color: #9ca3af;
}

.rules-edit__textarea {
  width: 100%;
  min-height: 18rem;
  margin-bottom: 1rem;
  padding: 0.85rem;
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.55rem;
  background: #1f2937;
  color: #f3f4f6;
  font-size: 0.9rem;
  line-height: 1.8;
  resize: vertical;
  outline: none;
}

.rules-edit__textarea:focus {
  border-color: rgba(139, 92, 246, 0.65);
}

.rules-edit__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  justify-content: flex-end;
}

.rules-edit__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.65rem;
  min-width: 8.5rem;
  padding: 0.6rem 1.1rem;
  border-radius: 0.55rem;
  font-size: 0.875rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  border: 1px solid transparent;
}

.rules-edit__btn--ghost {
  background: #374151;
  border-color: rgba(75, 85, 99, 0.8);
  color: #e5e7eb;
}

.rules-edit__btn--ghost:hover {
  background: #4b5563;
}

.rules-edit__btn--save {
  background: #16a34a;
  color: #fff;
}

.rules-edit__btn--save:hover:not(:disabled) {
  background: #15803d;
}

.rules-edit__btn--save:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>
