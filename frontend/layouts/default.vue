<script setup lang="ts">
const sidebarOpen = ref(false)
const auth = useAuthStore()
const route = useRoute()
const {
  closeDescriptionModal,
  closeGameLoginModal,
  closeRegisterModal,
} = useModals()

function closeSidebar() {
  sidebarOpen.value = false
}

function openSidebar() {
  sidebarOpen.value = true
}

provide('sidebar', { open: sidebarOpen, close: closeSidebar, openSidebar })

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    closeSidebar()
    closeDescriptionModal()
    closeGameLoginModal()
    closeRegisterModal()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
})

watch(() => route.path, (path) => {
  closeSidebar()
  closeDescriptionModal()
  closeGameLoginModal()
  if (!path.includes('/select-seat')) {
    closeRegisterModal()
  }
})
</script>

<template>
  <div class="page-shell">
    <AppHeader @open-sidebar="openSidebar" />
    <AppSidebar :open="sidebarOpen" @close="closeSidebar" />
    <FlashMessages />
    <main class="page-main container mx-auto px-4 py-6 max-w-7xl">
      <slot />
    </main>
    <AppFooter />
    <LazyDescriptionModal />
    <ClientOnly>
      <LazyHomeNotificationPopup v-if="auth.isAuthenticated" />
      <LazyTeamInviteBanner v-if="auth.isAuthenticated" />
      <LazyGameLoginModal />
      <RegisterTournamentModal />
    </ClientOnly>
  </div>
</template>
