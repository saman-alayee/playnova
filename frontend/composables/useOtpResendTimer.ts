import { toPersianDigits } from '~/utils/jalali'

const DEFAULT_SECONDS = 60

function readRetryAfter(errors: unknown): number {
  if (!errors || typeof errors !== 'object' || Array.isArray(errors)) {
    return 0
  }

  const raw = (errors as Record<string, unknown>).retry_after
  const value = Array.isArray(raw) ? raw[0] : raw
  const seconds = Number(value)

  return Number.isFinite(seconds) && seconds > 0 ? Math.ceil(seconds) : 0
}

export function useOtpResendTimer(storageKey: MaybeRefOrGetter<string>, seconds = DEFAULT_SECONDS) {
  const remaining = ref(0)
  let interval: ReturnType<typeof setInterval> | null = null

  const canResend = computed(() => remaining.value <= 0)

  const label = computed(() => {
    if (remaining.value <= 0) {
      return 'ارسال مجدد کد'
    }

    const minutes = Math.floor(remaining.value / 60)
    const secs = remaining.value % 60
    const clock = `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`

    return `ارسال مجدد تا ${toPersianDigits(clock)}`
  })

  function key() {
    return toValue(storageKey)
  }

  function persist(untilMs: number) {
    if (!import.meta.client) {
      return
    }
    sessionStorage.setItem(key(), String(untilMs))
  }

  function stop() {
    if (interval) {
      clearInterval(interval)
      interval = null
    }
  }

  function start(fromSeconds = seconds) {
    const next = Math.max(0, Math.ceil(fromSeconds))
    remaining.value = next
    persist(Date.now() + next * 1000)
    stop()
    if (next > 0) {
      interval = setInterval(() => {
        remaining.value = Math.max(0, remaining.value - 1)
        if (remaining.value <= 0) {
          stop()
        }
      }, 1000)
    }
  }

  function sync(fromSeconds?: number | null) {
    if (typeof fromSeconds === 'number' && fromSeconds > 0) {
      start(fromSeconds)
      return
    }

    if (typeof fromSeconds === 'number') {
      remaining.value = 0
      persist(0)
      stop()
    }
  }

  function restoreOrStart() {
    if (!import.meta.client) {
      start()
      return
    }

    const raw = sessionStorage.getItem(key())
    const until = raw ? Number(raw) : 0
    const left = until > Date.now() ? Math.ceil((until - Date.now()) / 1000) : 0

    if (left > 0) {
      start(left)
      return
    }

    if (raw) {
      remaining.value = 0
      return
    }

    start()
  }

  function applyError(err: { data?: { errors?: unknown } }) {
    const wait = readRetryAfter(err.data?.errors)
    if (wait > 0) {
      start(wait)
    }
  }

  onMounted(restoreOrStart)
  onBeforeUnmount(stop)

  return { remaining, canResend, label, start, sync, applyError }
}
