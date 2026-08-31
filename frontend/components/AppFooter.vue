<script setup lang="ts">
const auth = useAuthStore()
const year = new Date().getFullYear()
const menuReady = useAuthMenuReady()
</script>

<template>
  <footer class="site-footer">
    <div class="site-footer__glow" />
    <div class="container mx-auto px-4 max-w-7xl site-footer__inner">
      <div class="site-footer__grid">
        <div>
          <SiteLogoImage img-class="site-footer__logo" :width="180" :height="64" />
          <p class="site-footer__desc">
            پلتفرم برگزاری مسابقات آنلاین Call of Duty Mobile — رقابت، هیجان و جوایز نقدی.
          </p>
        </div>

        <div>
          <h3 class="site-footer__title">دسترسی سریع</h3>
          <ul class="site-footer__links">
            <li><NuxtLink to="/">خانه</NuxtLink></li>
            <li><NuxtLink to="/#special">مسابقات ویژه</NuxtLink></li>
            <li><NuxtLink to="/leaderboard">رتبه‌بندی</NuxtLink></li>
            <li><NuxtLink to="/history">تاریخچه مسابقات</NuxtLink></li>
            <li><NuxtLink to="/rules">قوانین</NuxtLink></li>
          </ul>
        </div>

        <div>
          <h3 class="site-footer__title">حساب کاربری</h3>
          <ul class="site-footer__links">
            <template v-if="menuReady && auth.isAuthenticated">
              <li><NuxtLink to="/profile">پروفایل</NuxtLink></li>
              <li><NuxtLink to="/wallet">کیف پول</NuxtLink></li>
              <li><NuxtLink to="/tickets">سوالات متداول</NuxtLink></li>
            </template>
            <template v-else-if="menuReady">
              <li><NuxtLink to="/login">ورود</NuxtLink></li>
              <li><NuxtLink to="/register">ثبت‌نام</NuxtLink></li>
              <li><NuxtLink to="/wallet">کیف پول</NuxtLink></li>
            </template>
          </ul>
        </div>

        <div>
          <h3 class="site-footer__title">اعتماد و امنیت</h3>
          <div class="site-footer__trust">
            <a
              referrerpolicy="origin"
              target="_blank"
              rel="noopener noreferrer"
              href="https://trustseal.enamad.ir/?id=766546&Code=sORWoyVCo0DL6d7gLFAqrTrHvwchtiBu"
            >
              <img
                referrerpolicy="origin"
                src="https://trustseal.enamad.ir/logo.aspx?id=766546&Code=sORWoyVCo0DL6d7gLFAqrTrHvwchtiBu"
                alt="نماد اعتماد"
                width="125"
                height="136"
                loading="lazy"
                decoding="async"
                style="cursor:pointer"
              >
            </a>
          </div>
        </div>
      </div>

      <div class="site-footer__bottom">
        تمامی حقوق محفوظ است © {{ year }} PlayNova.ir
      </div>
    </div>
  </footer>
</template>
