<script setup lang="ts">
const sidebarOpen = ref(false)

function closeSidebar() {
  sidebarOpen.value = false
}

function openSidebar() {
  sidebarOpen.value = true
}

provide('sidebar', { open: sidebarOpen, close: closeSidebar, openSidebar })
</script>

<template>
  <div class="page-shell">
    <AppHeader @open-sidebar="openSidebar" />
    <AppSidebar :open="sidebarOpen" @close="closeSidebar" />
    <FlashMessages />
    <main class="page-main container mx-auto px-4 py-6 max-w-7xl">
      <AdminNav />
      <AdminHelpBanner />
      <div class="admin-page-content">
        <slot />
      </div>
    </main>
    <AppFooter />
  </div>
</template>

<style scoped>
.admin-page-content :deep(> *) {
  width: 100%;
  margin-inline: auto;
}
</style>
