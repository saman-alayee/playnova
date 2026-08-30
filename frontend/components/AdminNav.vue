<script setup lang="ts">
const route = useRoute()
const auth = useAuthStore()

function isActive(path: string) {
  return route.path === path || route.path.startsWith(`${path}/`)
}
</script>

<template>
  <div class="flex flex-wrap gap-2 mb-6 text-sm">
    <NuxtLink to="/admin" class="px-3 py-1.5 rounded transition" :class="isActive('/admin') && route.path === '/admin' ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      داشبورد
    </NuxtLink>
    <NuxtLink to="/admin/tournaments" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/tournaments') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      مسابقات
    </NuxtLink>
    <NuxtLink
      v-if="auth.isSeatAdmin || auth.isAdmin"
      to="/admin/tournament-seats"
      class="px-3 py-1.5 rounded transition"
      :class="isActive('/admin/tournament-seats') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'"
    >
      🗺️ جایگاه‌ها
    </NuxtLink>
    <NuxtLink to="/admin/users" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/users') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      کاربران
    </NuxtLink>
    <NuxtLink to="/admin/withdrawals" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/withdrawals') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      برداشت‌ها
    </NuxtLink>
    <NuxtLink to="/admin/kyc" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/kyc') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      احراز هویت
    </NuxtLink>
    <NuxtLink to="/admin/errors" class="px-3 py-1.5 rounded transition relative" :class="isActive('/admin/errors') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      ⚠️ خطاهای API
    </NuxtLink>
    <NuxtLink to="/admin/site-settings" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/site-settings') || isActive('/admin/settings/site') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      تنظیمات سایت
    </NuxtLink>
    <NuxtLink to="/admin/discounts" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/discounts') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      کدهای تخفیف
    </NuxtLink>
    <NuxtLink to="/admin/news" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/news') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      اخبار
    </NuxtLink>
    <NuxtLink to="/admin/broadcast" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/broadcast') && !route.path.includes('manage') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      📣 ارسال اعلان
    </NuxtLink>
    <NuxtLink to="/admin/broadcast-manage" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/broadcast-manage') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      📋 مدیریت اعلان‌ها
    </NuxtLink>
    <NuxtLink to="/admin/tickets" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/tickets') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      تیکت‌ها
    </NuxtLink>
    <NuxtLink to="/admin/rules/manage" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/rules') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      📋 مدیریت قوانین
    </NuxtLink>
    <NuxtLink to="/admin/logo" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/logo') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      🖼️ لوگو
    </NuxtLink>
    <NuxtLink to="/admin/payment-gateway" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/payment-gateway') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      💳 درگاه پرداخت
    </NuxtLink>
    <NuxtLink to="/admin/admins" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/admins') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      👑 ادمین‌ها
    </NuxtLink>
    <NuxtLink to="/admin/seat-admins" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/seat-admins') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      🗺️ ادمین جایگاه
    </NuxtLink>
    <NuxtLink to="/admin/sms-settings" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/sms-settings') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      📱 پیامک
    </NuxtLink>
    <NuxtLink to="/admin/ai-settings" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/ai-settings') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      🤖 هوش مصنوعی
    </NuxtLink>
    <NuxtLink to="/admin/referral-settings" class="px-3 py-1.5 rounded transition" :class="isActive('/admin/referral-settings') ? 'bg-secondary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'">
      🔗 تنظیمات دعوت
    </NuxtLink>
  </div>
</template>
