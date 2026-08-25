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
      <slot />
    </main>
    <AppFooter />
  </div>
</template>
