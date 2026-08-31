/** Site logo — matches legacy playnova.ir (public/logo.png). */
export function useSiteLogo() {
  const auth = useAuthStore()
  const { mediaUrl } = useMediaUrl()

  const logoFailed = ref(false)
  const logoIndex = ref(0)

  const logoCandidates = computed(() => {
    const candidates: string[] = ['/logo.png', '/playnova-logo.png']

    const custom = auth.settings?.logo_url || auth.logoUrl
    if (custom && /\/storage\//i.test(custom)) {
      const resolved = mediaUrl(custom)
      if (resolved) candidates.push(resolved)
    }

    return [...new Set(candidates)]
  })

  const logoSrc = computed(() => logoCandidates.value[logoIndex.value] ?? null)

  function onLogoError() {
    if (logoIndex.value < logoCandidates.value.length - 1) {
      logoIndex.value += 1
      return
    }
    logoFailed.value = true
  }

  watch(logoCandidates, () => {
    logoIndex.value = 0
    logoFailed.value = false
  })

  return { logoSrc, logoFailed, onLogoError }
}
