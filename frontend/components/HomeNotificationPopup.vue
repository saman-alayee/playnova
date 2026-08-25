<script setup lang="ts">
import type { Notification } from '~/types/api'

const auth = useAuthStore()
const api = useApi()
const route = useRoute()
const { formatDateTime } = usePersianDateTime()

const visible = ref(false)
const notification = ref<Notification | null>(null)
const closing = ref(false)

const DISMISSED_KEY = 'playnova:dismissed-popup-ids'

function getDismissedIds(): number[] {
  if (!import.meta.client) return []
  try {
    const raw = sessionStorage.getItem(DISMISSED_KEY)
    const parsed = raw ? JSON.parse(raw) : []
    return Array.isArray(parsed) ? parsed.filter((id) => Number.isFinite(id)) : []
  } catch {
    return []
  }
}

function rememberDismissed(id: number) {
  if (!import.meta.client) return
  const ids = new Set(getDismissedIds())
  ids.add(id)
  sessionStorage.setItem(DISMISSED_KEY, JSON.stringify([...ids]))
}

async function loadPopup() {
  if (!auth.isAuthenticated || route.path !== '/') return

  try {
    const item = await api.notifications.popup()
    if (!item?.id) {
      notification.value = null
      visible.value = false
      return
    }

    if (getDismissedIds().includes(item.id)) {
      notification.value = null
      visible.value = false
      return
    }

    notification.value = item
    visible.value = true
  } catch {
    notification.value = null
    visible.value = false
  }
}

function closePopup() {
  if (!notification.value || closing.value) return

  closing.value = true
  const current = notification.value
  rememberDismissed(current.id)
  visible.value = false
  notification.value = null
  closing.value = false
}

onMounted(() => {
  if (auth.isAuthenticated) {
    void loadPopup()
  }
})

watch(() => auth.isAuthenticated, (loggedIn) => {
  if (loggedIn) void loadPopup()
  else {
    visible.value = false
    notification.value = null
  }
})

watch(() => route.path, (path) => {
  if (path === '/' && auth.isAuthenticated) {
    void loadPopup()
  } else {
    visible.value = false
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="visible && notification"
        class="home-announcement-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="home-announcement-title"
      >
        <div class="home-announcement-card">
          <div class="home-announcement-card__header">
            <span class="home-announcement-card__badge">اعلان</span>
            <button
              type="button"
              class="home-announcement-card__close"
              aria-label="بستن اعلان"
              @click="closePopup"
            >
              ×
            </button>
          </div>
          <h2 id="home-announcement-title" class="home-announcement-card__title">
            {{ notification.title }}
          </h2>
          <p class="home-announcement-card__body">
            {{ notification.message || notification.body }}
          </p>
          <p v-if="notification.created_at" class="home-announcement-card__time">
            {{ formatDateTime(notification.created_at) }}
          </p>
          <div class="home-announcement-card__actions">
            <NuxtLink to="/notifications" class="home-announcement-card__link" @click="closePopup">
              مشاهده در اعلانات
            </NuxtLink>
            <button type="button" class="home-announcement-card__btn" @click="closePopup">
              بستن
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
