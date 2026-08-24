<script setup lang="ts">
const route = useRoute()
await navigateTo(`/register/verify/${route.params.token}`, { redirectCode: 301 })
</script>
