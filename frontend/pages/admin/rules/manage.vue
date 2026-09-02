<script setup lang="ts">
import type { RuleSection } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت قوانین | PlayNova' })

const api = useApi()
const flash = useState('flash')
const adding = ref(false)
const deletingId = ref<number | null>(null)

const { data, pending, refresh } = usePageData('admin-rules', () => api.admin.rules())
const rules = computed(() => (data.value ?? []) as RuleSection[])
const newContent = ref('')

function previewText(html: string): string {
  if (!html) return ''
  const text = html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
  return text.length > 220 ? `${text.slice(0, 220)}…` : text
}

async function addRule() {
  if (!newContent.value.trim()) return
  adding.value = true
  try {
    await api.admin.createRule(newContent.value)
    newContent.value = ''
    flash.value = { success: 'بخش اضافه شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    adding.value = false
  }
}

async function remove(rule: RuleSection) {
  if (!confirm(`بخش #${rule.id} حذف شود؟`)) return
  deletingId.value = rule.id
  try {
    await api.admin.deleteRule(rule.id)
    flash.value = { success: 'بخش حذف شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div class="rules-manage">
    <div class="rules-manage__header">
      <div>
        <h1 class="rules-manage__title">مدیریت قوانین</h1>
        <p v-if="!pending" class="rules-manage__subtitle">
          {{ rules.length.toLocaleString('fa-IR') }} بخش ثبت شده
        </p>
      </div>
      <NuxtLink to="/admin" class="rules-manage__back">← داشبورد</NuxtLink>
    </div>

    <form class="rules-manage__create" @submit.prevent="addRule">
      <h2 class="rules-manage__create-title">افزودن بخش جدید</h2>
      <textarea
        v-model="newContent"
        rows="5"
        required
        placeholder="متن بخش جدید را بنویسید..."
        class="rules-manage__textarea"
      />
      <button type="submit" class="rules-manage__btn rules-manage__btn--primary" :disabled="adding">
        {{ adding ? 'در حال افزودن...' : '+ افزودن بخش' }}
      </button>
    </form>

    <div v-if="pending" class="rules-manage__state">در حال بارگذاری...</div>
    <div v-else-if="!rules.length" class="rules-manage__state rules-manage__state--empty">
      هنوز بخشی ثبت نشده است. اولین بخش را از فرم بالا اضافه کنید.
    </div>

    <div v-else class="rules-manage__list">
      <article v-for="(rule, index) in rules" :key="rule.id" class="rule-card">
        <div class="rule-card__head">
          <span class="rule-card__badge">بخش {{ (index + 1).toLocaleString('fa-IR') }}</span>
          <span class="rule-card__id">#{{ rule.id.toLocaleString('fa-IR') }}</span>
        </div>

        <div class="rule-card__preview">
          <p v-if="previewText(rule.content)" class="rule-card__text">{{ previewText(rule.content) }}</p>
          <div v-else class="rule-card__html prose prose-invert max-w-none text-sm" v-html="rule.content" />
        </div>

        <div class="rule-card__actions">
          <NuxtLink :to="`/admin/rules/${rule.id}/edit`" class="rule-card__btn rule-card__btn--edit">
            ✏️ ویرایش
          </NuxtLink>
          <button
            type="button"
            class="rule-card__btn rule-card__btn--delete"
            :disabled="deletingId === rule.id"
            @click="remove(rule)"
          >
            {{ deletingId === rule.id ? 'در حال حذف...' : '🗑️ حذف' }}
          </button>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.rules-manage__header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.rules-manage__title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 800;
  color: #fff;
}

.rules-manage__subtitle {
  margin: 0.35rem 0 0;
  font-size: 0.82rem;
  color: #9ca3af;
}

.rules-manage__back {
  font-size: 0.875rem;
  color: #a78bfa;
  text-decoration: none;
}

.rules-manage__back:hover {
  text-decoration: underline;
}

.rules-manage__create {
  margin-bottom: 1.5rem;
  padding: 1.25rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.85rem;
  background: rgba(17, 24, 39, 0.65);
}

.rules-manage__create-title {
  margin: 0 0 0.85rem;
  font-size: 1rem;
  font-weight: 700;
  color: #e5e7eb;
}

.rules-manage__textarea {
  width: 100%;
  min-height: 7rem;
  margin-bottom: 0.85rem;
  padding: 0.75rem;
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.55rem;
  background: #1f2937;
  color: #f3f4f6;
  font-size: 0.9rem;
  line-height: 1.7;
  resize: vertical;
  outline: none;
}

.rules-manage__textarea:focus {
  border-color: rgba(139, 92, 246, 0.65);
}

.rules-manage__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.5rem;
  padding: 0.55rem 1.1rem;
  border-radius: 0.55rem;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background 0.15s, border-color 0.15s, opacity 0.15s;
}

.rules-manage__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.rules-manage__btn--primary {
  background: #16a34a;
  color: #fff;
}

.rules-manage__btn--primary:hover:not(:disabled) {
  background: #15803d;
}

.rules-manage__state {
  padding: 2rem 1rem;
  text-align: center;
  color: #9ca3af;
  font-size: 0.9rem;
}

.rules-manage__state--empty {
  border: 1px dashed rgba(75, 85, 99, 0.55);
  border-radius: 0.85rem;
  background: rgba(17, 24, 39, 0.45);
}

.rules-manage__list {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.rule-card {
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.85rem;
  background: rgba(17, 24, 39, 0.75);
  overflow: hidden;
}

.rule-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.7rem 1rem;
  border-bottom: 1px solid rgba(75, 85, 99, 0.35);
  background: rgba(31, 41, 55, 0.55);
}

.rule-card__badge {
  font-size: 0.78rem;
  font-weight: 800;
  color: #c4b5fd;
}

.rule-card__id {
  font-size: 0.72rem;
  color: #6b7280;
  font-family: ui-monospace, monospace;
}

.rule-card__preview {
  padding: 1rem 1rem 0.85rem;
}

.rule-card__text {
  margin: 0;
  color: #d1d5db;
  font-size: 0.9rem;
  line-height: 1.8;
  white-space: pre-wrap;
}

.rule-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  padding: 0 1rem 1rem;
}

.rule-card__btn {
  flex: 1 1 8rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.65rem;
  padding: 0.6rem 1rem;
  border-radius: 0.55rem;
  font-size: 0.875rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background 0.15s, border-color 0.15s, transform 0.1s;
}

.rule-card__btn:hover:not(:disabled) {
  transform: translateY(-1px);
}

.rule-card__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.rule-card__btn--edit {
  background: rgba(124, 58, 237, 0.18);
  border-color: rgba(167, 139, 250, 0.45);
  color: #ddd6fe;
}

.rule-card__btn--edit:hover {
  background: rgba(124, 58, 237, 0.32);
  border-color: rgba(167, 139, 250, 0.7);
}

.rule-card__btn--delete {
  background: rgba(220, 38, 38, 0.12);
  border-color: rgba(248, 113, 113, 0.4);
  color: #fecaca;
}

.rule-card__btn--delete:hover:not(:disabled) {
  background: rgba(220, 38, 38, 0.22);
  border-color: rgba(248, 113, 113, 0.65);
}

@media (min-width: 640px) {
  .rule-card__actions {
    justify-content: flex-end;
  }

  .rule-card__btn {
    flex: 0 1 auto;
    min-width: 8.5rem;
  }
}
</style>
