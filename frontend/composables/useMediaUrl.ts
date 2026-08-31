export function useMediaUrl() {
  const config = useRuntimeConfig()
  const backend = String(config.public.backendUrl || '').replace(/\/$/, '')

  function mediaUrl(path?: string | null, fallback: string | null = null): string | null {
    const value = path || fallback
    if (!value) return null
    if (/^https?:\/\//i.test(value) || value.startsWith('data:')) return value
    if (value.startsWith('//')) return `https:${value}`
    return `${backend}${value.startsWith('/') ? value : `/${value}`}`
  }

  /** Icons shipped in frontend/public (or nginx static) — avoid backend URL prefix. */
  function publicAssetUrl(path?: string | null): string | null {
    if (!path) return null
    const socialMatch = path.match(/\/(social-[^/?#]+)$/i)
    if (socialMatch) return `/${socialMatch[1]}`
    if (path.startsWith('/social-')) return path
    return mediaUrl(path)
  }

  return { mediaUrl, publicAssetUrl, backend }
}
