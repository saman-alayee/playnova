<script setup lang="ts">
import type { Notification } from '~/types/api'

const auth = useAuthStore()
const api = useApi()
const route = useRoute()
const { formatDateTime } = usePersianDateTime()

const PREVIEW_LENGTH = 280
const POPUP_DISMISSED_KEY = 'playnova:home-popup-dismissed'

const visible = ref(false)
const notification = ref<Notification | null>(null)
const closing = ref(false)

const fullMessage = computed(() => {
  const n = notification.value
  return (n?.message || n?.body || '').trim()
})

const isLongMessage = computed(() => fullMessage.value.length > PREVIEW_LENGTH)

const previewMessage = computed(() => {
  if (!isLongMessage.value) return fullMessage.value
  return `${fullMessage.value.slice(0, PREVIEW_LENGTH).trim()}…`
})

function wasDismissedThisSession() {
  if (!import.meta.client) return false
  return sessionStorage.getItem(POPUP_DISMISSED_KEY) === '1'
}

function rememberDismissedSession() {
  if (!import.meta.client) return
  sessionStorage.setItem(POPUP_DISMISSED_KEY, '1')
}

async function loadPopup() {
  if (!auth.isAuthenticated || route.path !== '/' || wasDismissedThisSession()) return

  try {
    const item = await api.notifications.popup()
    if (!item?.id) {
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

async function closePopup() {
  if (closing.value) return

  const current = notification.value
  closing.value = true
  visible.value = false
  notification.value = null
  rememberDismissedSession()

  if (current?.id) {
    try {
      await api.notifications.markRead(current.id)
      if (auth.user) {
        auth.setUser({
          ...auth.user,
          unread_notifications_count: Math.max(0, (auth.user.unread_notifications_count ?? 1) - 1),
        })
      }
    } catch {
      // Popup already hidden; inbox can still mark read manually.
    }
  }

  closing.value = false
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && visible.value) {
    void closePopup()
  }
}

watch(visible, (open) => {
  if (!import.meta.client) return
  document.body.style.overflow = open ? 'hidden' : ''
})

onMounted(() => {
  if (auth.isAuthenticated) {
    void loadPopup()
  }
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  if (import.meta.client) {
    document.body.style.overflow = ''
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
        @click.self="closePopup"
      >
        <div class="home-announcement-card" @click.stop>
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

          <div class="home-announcement-card__scroll">
            <h2 id="home-announcement-title" class="home-announcement-card__title">
              {{ notification.title }}
            </h2>
            <p class="home-announcement-card__body">
              {{ previewMessage }}
            </p>
            <p v-if="isLongMessage" class="home-announcement-card__more">
              برای خواندن متن کامل، «مشاهده در اعلانات» را بزنید.
            </p>
            <p v-if="notification.created_at" class="home-announcement-card__time">
              {{ formatDateTime(notification.created_at) }}
            </p>
          </div>

          <div class="home-announcement-card__actions">
            <NuxtLink to="/notifications" class="home-announcement-card__link" @click="closePopup">
              مشاهده در اعلانات
            </NuxtLink>
            <button type="button" class="home-announcement-card__btn" @click="closePopup">
              بستن و ادامه
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
