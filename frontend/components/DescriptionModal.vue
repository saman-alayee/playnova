<script setup lang="ts">
const {
  descriptionOpen,
  descriptionTitle,
  descriptionContent,
  closeDescriptionModal,
} = useModals()
</script>

<template>
  <div
    v-if="descriptionOpen"
    class="modal-overlay"
    @click.self="closeDescriptionModal"
  >
    <div class="modal-panel" style="max-height: 85vh">
      <h2 class="modal-panel__title">📝 توضیحات — {{ descriptionTitle }}</h2>
      <div class="modal-panel__body">{{ descriptionContent }}</div>
      <button type="button" class="modal-panel__close" @click="closeDescriptionModal">بستن</button>
    </div>
  </div>
</template>
