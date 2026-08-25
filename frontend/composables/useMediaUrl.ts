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

  return { mediaUrl, backend }
}
