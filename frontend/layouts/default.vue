<script setup lang="ts">
const sidebarOpen = ref(false)

function closeSidebar() {
  sidebarOpen.value = false
}

function openSidebar() {
  sidebarOpen.value = true
}

provide('sidebar', { open: sidebarOpen, close: closeSidebar, openSidebar })

onMounted(() => {
  const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') closeSidebar()
  }
  window.addEventListener('keydown', onKeydown)
  onUnmounted(() => window.removeEventListener('keydown', onKeydown))
})
</script>

<template>
  <div>
    <AppHeader @open-sidebar="openSidebar" />
    <AppSidebar :open="sidebarOpen" @close="closeSidebar" />
    <FlashMessages />
    <main class="container mx-auto px-4 py-6 max-w-7xl">
      <slot />
    </main>
    <AppFooter />
    <TeamInviteBanner />
    <GameLoginModal />
    <RegisterTournamentModal />
  </div>
</template>
