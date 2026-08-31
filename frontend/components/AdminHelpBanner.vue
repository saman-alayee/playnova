<script setup lang="ts">
const route = useRoute()
const { help } = useAdminHelp()

const storageKey = computed(() => `admin-help-collapsed:${route.path}`)

const collapsed = ref(false)

onMounted(() => {
  if (import.meta.client) {
    collapsed.value = localStorage.getItem(storageKey.value) === '1'
  }
})

watch(storageKey, () => {
  if (import.meta.client) {
    collapsed.value = localStorage.getItem(storageKey.value) === '1'
  }
})

function toggle() {
  collapsed.value = !collapsed.value
  if (import.meta.client) {
    localStorage.setItem(storageKey.value, collapsed.value ? '1' : '0')
  }
}
</script>

<template>
  <div v-if="help" class="admin-help">
    <button type="button" class="admin-help__toggle" @click="toggle">
      <span class="admin-help__toggle-title">
        <span class="admin-help__icon" aria-hidden="true">💡</span>
        {{ help.title || 'راهنمای این بخش' }}
      </span>
      <span class="admin-help__toggle-action">
        {{ collapsed ? 'نمایش راهنما' : 'بستن' }}
        <span class="admin-help__chevron" :class="{ 'admin-help__chevron--open': !collapsed }">▾</span>
      </span>
    </button>

    <div v-show="!collapsed" class="admin-help__body">
      <ul class="admin-help__list">
        <li v-for="(tip, i) in help.tips" :key="i">{{ tip }}</li>
      </ul>
      <div v-if="help.links?.length" class="admin-help__links">
        <NuxtLink
          v-for="link in help.links"
          :key="link.to"
          :to="link.to"
          class="admin-help__link"
        >
          {{ link.label }} ←
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-help {
  margin-bottom: 1rem;
  border: 1px solid rgba(59, 130, 246, 0.35);
  border-radius: 0.65rem;
  background: rgba(30, 58, 138, 0.12);
  overflow: hidden;
}

.admin-help__toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  width: 100%;
  padding: 0.65rem 0.9rem;
  border: none;
  background: transparent;
  color: #bfdbfe;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  text-align: right;
}

.admin-help__toggle:hover {
  background: rgba(59, 130, 246, 0.08);
}

.admin-help__toggle-title {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.admin-help__icon {
  font-size: 1rem;
  line-height: 1;
}

.admin-help__toggle-action {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.72rem;
  font-weight: 600;
  color: #93c5fd;
}

.admin-help__chevron {
  display: inline-block;
  transition: transform 0.2s;
  transform: rotate(-90deg);
}

.admin-help__chevron--open {
  transform: rotate(0deg);
}

.admin-help__body {
  padding: 0 0.9rem 0.75rem;
}

.admin-help__list {
  margin: 0;
  padding: 0 1.15rem 0 0;
  font-size: 0.78rem;
  line-height: 1.85;
  color: #dbeafe;
}

.admin-help__list li + li {
  margin-top: 0.2rem;
}

.admin-help__links {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1rem;
  margin-top: 0.65rem;
  padding-top: 0.55rem;
  border-top: 1px solid rgba(59, 130, 246, 0.2);
}

.admin-help__link {
  font-size: 0.75rem;
  font-weight: 600;
  color: #60a5fa;
  text-decoration: none;
}

.admin-help__link:hover {
  color: #93c5fd;
  text-decoration: underline;
}
</style>
