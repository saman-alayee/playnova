<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'تغییر لوگوی سایت | PlayNova' })

const api = useApi()
const auth = useAuthStore()
const flash = useState('flash')
const { mediaUrl } = useMediaUrl()

const fileInput = ref<HTMLInputElement | null>(null)
const file = ref<File | null>(null)
const fileLabel = ref('No file chosen')
const saving = ref(false)
const deleting = ref(false)

const { data, pending, refresh } = usePageData('admin-logo', () => api.admin.logo())

const previewUrl = computed(() => {
  const url = data.value?.logo_url
  if (!url) return '/logo.png'
  if (url.startsWith('/')) return url
  return mediaUrl(url) || url
})

const previewKey = ref(0)

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const selected = input.files?.[0] ?? null
  file.value = selected
  fileLabel.value = selected?.name || 'No file chosen'
}

async function saveLogo() {
  if (!file.value) {
    flash.value = { error: 'لطفاً یک فایل تصویر انتخاب کنید.' }
    return
  }

  saving.value = true
  try {
    const fd = new FormData()
    fd.append('logo', file.value)
    await api.admin.updateLogo(fd)
    flash.value = { success: 'لوگو با موفقیت ذخیره شد.' }
    file.value = null
    fileLabel.value = 'No file chosen'
    if (fileInput.value) fileInput.value.value = ''
    auth.settings = null
    await auth.fetchSettings()
    await refresh()
    previewKey.value += 1
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    saving.value = false
  }
}

async function removeLogo() {
  if (!confirm('لوگو حذف شود و به حالت پیش‌فرض بازگردد؟')) return

  deleting.value = true
  try {
    await api.admin.deleteLogo()
    flash.value = { success: 'لوگو به حالت پیش‌فرض بازگشت.' }
    file.value = null
    fileLabel.value = 'No file chosen'
    if (fileInput.value) fileInput.value.value = ''
    auth.settings = null
    await auth.fetchSettings()
    await refresh()
    previewKey.value += 1
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="logo-admin">
    <div class="logo-admin__card">
      <h1 class="logo-admin__title">🖼️ تغییر لوگوی سایت</h1>

      <div v-if="pending" class="logo-admin__state">در حال بارگذاری...</div>

      <template v-else>
        <p class="logo-admin__label">لوگوی فعلی:</p>
        <div class="logo-admin__preview">
          <img
            :key="previewKey"
            :src="previewUrl"
            alt="لوگوی فعلی"
            class="logo-admin__preview-img"
            @error="($event.target as HTMLImageElement).src = '/logo.png'"
          >
        </div>

        <form class="logo-admin__form" @submit.prevent="saveLogo">
          <label class="logo-admin__upload-label" for="logo-file">
            آپلود لوگوی جدید (png, jpg, svg — حداکثر ۲ مگابایت)
          </label>

          <div class="logo-admin__file-wrap">
            <input
              id="logo-file"
              ref="fileInput"
              type="file"
              accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp"
              class="logo-admin__file"
              @change="onFileChange"
            >
            <span class="logo-admin__file-name">{{ fileLabel }}</span>
            <span class="logo-admin__file-btn">Choose File</span>
          </div>

          <button
            type="submit"
            class="logo-admin__save"
            :disabled="saving || !file"
          >
            {{ saving ? 'در حال ذخیره...' : '💾 ذخیره لوگو' }}
          </button>
        </form>

        <button
          type="button"
          class="logo-admin__delete"
          :disabled="deleting"
          @click="removeLogo"
        >
          {{ deleting ? 'در حال حذف...' : '🗑️ حذف لوگو و بازگشت به حالت پیش‌فرض' }}
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.logo-admin {
  display: flex;
  justify-content: center;
  padding: 0.5rem 0 2rem;
}

.logo-admin__card {
  width: 100%;
  max-width: 28rem;
  padding: 1.75rem 1.5rem 1.5rem;
  border: 1px solid rgba(75, 85, 99, 0.55);
  border-radius: 0.85rem;
  background: rgba(17, 24, 39, 0.75);
  text-align: center;
}

.logo-admin__title {
  margin: 0 0 1.5rem;
  font-size: 1.35rem;
  font-weight: 800;
  color: #fff;
}

.logo-admin__state {
  padding: 2rem 0;
  color: #9ca3af;
  font-size: 0.9rem;
}

.logo-admin__label {
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  color: #d1d5db;
}

.logo-admin__preview {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 7.5rem;
  height: 7.5rem;
  margin: 0 auto 1.5rem;
  padding: 0.5rem;
  border: 1px solid rgba(107, 114, 128, 0.55);
  border-radius: 0.65rem;
  background: #0a0a0a;
}

.logo-admin__preview-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.logo-admin__form {
  text-align: right;
}

.logo-admin__upload-label {
  display: block;
  margin-bottom: 0.55rem;
  font-size: 0.82rem;
  color: #e5e7eb;
  line-height: 1.7;
}

.logo-admin__file-wrap {
  position: relative;
  display: flex;
  align-items: center;
  min-height: 2.65rem;
  margin-bottom: 1rem;
  padding: 0.35rem 0.35rem 0.35rem 0.75rem;
  border: 1px solid rgba(75, 85, 99, 0.7);
  border-radius: 0.55rem;
  background: #1e1e2d;
  overflow: hidden;
}

.logo-admin__file {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.logo-admin__file-name {
  flex: 1;
  font-size: 0.78rem;
  color: #9ca3af;
  text-align: left;
  direction: ltr;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.logo-admin__file-btn {
  flex-shrink: 0;
  padding: 0.35rem 0.75rem;
  border-radius: 0.35rem;
  background: #fff;
  color: #111827;
  font-size: 0.78rem;
  font-weight: 600;
}

.logo-admin__save {
  width: 100%;
  min-height: 2.75rem;
  margin-bottom: 0.85rem;
  border: none;
  border-radius: 0.55rem;
  background: #16a34a;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 800;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
}

.logo-admin__save:hover:not(:disabled) {
  background: #15803d;
}

.logo-admin__save:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.logo-admin__delete {
  width: 100%;
  padding: 0.35rem 0.5rem;
  border: none;
  background: transparent;
  color: #f87171;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: color 0.15s, opacity 0.15s;
}

.logo-admin__delete:hover:not(:disabled) {
  color: #fca5a5;
}

.logo-admin__delete:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>
