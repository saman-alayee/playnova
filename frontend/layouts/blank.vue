<template>
  <div class="blank-shell">
    <FlashMessages />
    <slot />
  </div>
</template>

<style scoped>
.blank-shell {
  min-height: 100dvh;
  background: #000;
}
</style>
