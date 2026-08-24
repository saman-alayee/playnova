<script setup lang="ts">
const {
  gameLoginOpen,
  gameLoginTitle,
  gameLoginContent,
  gameLoginSeat,
  closeGameLoginModal,
} = useModals()
</script>

<template>
  <div
    v-if="gameLoginOpen"
    class="modal-overlay"
    @click.self="closeGameLoginModal"
  >
    <div class="modal-panel">
      <h2 class="modal-panel__title">🎮 {{ gameLoginTitle.startsWith('اطلاعات') ? gameLoginTitle : `اطلاعات ورود — ${gameLoginTitle}` }}</h2>
      <div class="modal-panel__body">{{ gameLoginContent }}</div>
      <div v-if="gameLoginSeat" class="modal-panel__seat">
        <p class="text-xs text-blue-300 mb-1">جایگاه شما (غیرقابل تغییر)</p>
        <p class="text-xl font-extrabold text-blue-400 text-center font-mono" dir="ltr">{{ gameLoginSeat }}</p>
      </div>
      <button type="button" class="modal-panel__close" @click="closeGameLoginModal">بستن</button>
    </div>
  </div>
</template>
