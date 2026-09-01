<script setup lang="ts">
import type { TournamentResultAnalysis, TournamentResultParticipant } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })

const route = useRoute()
const router = useRouter()
const api = useApi()

const tournamentId = computed(() => String(route.params.id))

const { data: tournament } = await useAsyncData(
  () => `admin-tournament-result-${tournamentId.value}`,
  () => api.admin.tournament(tournamentId.value),
)

const { data: promptConfig, refresh: refreshPromptConfig } = await useAsyncData(
  () => `admin-tournament-result-prompt-${tournamentId.value}`,
  () => api.admin.tournamentResultAiConfig(tournamentId.value),
)

useHead({
  title: computed(() =>
    tournament.value ? `ثبت نتیجه AI — ${tournament.value.title}` : 'ثبت نتیجه با AI',
  ),
})

interface RankRow {
  key: string
  user_id: number | null
  username: string
  cod_id?: string | null
  kills: number | null
  rank: number | null
  detected_name?: string | null
  detected_uid?: string | null
  match_method?: string | null
  match_score?: number | null
}

const fileInput = ref<HTMLInputElement | null>(null)
const videoRef = ref<HTMLVideoElement | null>(null)
const selectedFile = ref<File | null>(null)
const previewUrl = ref<string | null>(null)
const isVideo = ref(false)
const analyzing = ref(false)
const applying = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const analysis = ref<TournamentResultAnalysis | null>(null)
const rankedRows = ref<RankRow[]>([])
const dragIndex = ref<number | null>(null)
const showPromptEditor = ref(true)
const savePrompt = ref(false)

const systemPrompt = ref('')
const userPrompt = ref('')

watch(promptConfig, (config) => {
  if (!config) return
  systemPrompt.value = config.system_prompt
  userPrompt.value = config.user_prompt
  savePrompt.value = config.has_saved_prompt
}, { immediate: true })

const availableParticipants = computed(() => analysis.value?.participants ?? [])

const unusedParticipants = computed(() => {
  const used = new Set(rankedRows.value.map((row) => row.user_id).filter(Boolean))
  return availableParticipants.value.filter((p) => !used.has(p.user_id))
})

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  selectedFile.value = file

  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }

  if (!file) {
    isVideo.value = false
    return
  }

  isVideo.value = file.type.startsWith('video/')
  previewUrl.value = URL.createObjectURL(file)
}

async function captureVideoFrame(): Promise<File | null> {
  const video = videoRef.value
  if (!video || video.videoWidth === 0) {
    error.value = 'ویدیو هنوز آماده نیست. چند ثانیه صبر کنید یا زمان ویدیو را جابه‌جا کنید.'
    return null
  }

  return snapshotVideoFrame(video, 'frame.jpg')
}

async function captureVideoFrames(count = 6): Promise<File[]> {
  const video = videoRef.value
  if (!video || video.videoWidth === 0 || !Number.isFinite(video.duration) || video.duration <= 0) {
    return []
  }

  const frames: File[] = []
  const duration = video.duration
  const offsets = Array.from({ length: count }, (_, i) =>
    Math.min(duration - 0.05, Math.max(0.05, (duration * (i + 1)) / (count + 1))),
  )

  const previousTime = video.currentTime
  for (let i = 0; i < offsets.length; i++) {
    video.currentTime = offsets[i]
    await new Promise<void>((resolve) => {
      const onSeeked = () => {
        video.removeEventListener('seeked', onSeeked)
        resolve()
      }
      video.addEventListener('seeked', onSeeked)
    })
    const frame = await snapshotVideoFrame(video, `frame-${i + 1}.jpg`)
    if (frame) frames.push(frame)
  }

  video.currentTime = previousTime
  return frames
}

function snapshotVideoFrame(video: HTMLVideoElement, filename: string): Promise<File | null> {
  const canvas = document.createElement('canvas')
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  const ctx = canvas.getContext('2d')
  if (!ctx) return Promise.resolve(null)

  ctx.drawImage(video, 0, 0, canvas.width, canvas.height)

  return new Promise((resolve) => {
    canvas.toBlob((blob) => {
      if (!blob) {
        resolve(null)
        return
      }
      resolve(new File([blob], filename, { type: 'image/jpeg' }))
    }, 'image/jpeg', 0.92)
  })
}

function buildRankedRows(result: TournamentResultAnalysis) {
  const rows: RankRow[] = []

  const matchedSorted = [...result.matched].sort((a, b) => a.rank - b.rank)
  for (const row of matchedSorted) {
    rows.push({
      key: `m-${row.user_id}`,
      user_id: row.user_id,
      username: row.username,
      cod_id: row.cod_id,
      kills: row.kills ?? null,
      rank: row.rank,
      detected_name: row.detected_name,
      detected_uid: row.detected_uid,
      match_method: row.match_method,
      match_score: row.match_score ?? null,
    })
  }

  const usedIds = new Set(rows.map((r) => r.user_id))
  const remaining = result.participants
    .filter((p) => !usedIds.has(p.user_id))
    .sort((a, b) => (a.seat_number ?? 999) - (b.seat_number ?? 999))

  for (const p of remaining) {
    rows.push({
      key: `p-${p.user_id}`,
      user_id: p.user_id,
      username: p.username,
      cod_id: p.cod_id,
      kills: null,
      rank: null,
    })
  }

  rankedRows.value = rows
}

async function analyze(mode: 'image' | 'video-frame' | 'video-multi' = 'image') {
  if (!selectedFile.value) {
    error.value = 'لطفاً تصویر یا ویدیو انتخاب کنید.'
    return
  }

  analyzing.value = true
  error.value = null
  success.value = null

  const form = new FormData()
  let fileToSend: File = selectedFile.value

  if (isVideo.value && mode === 'video-frame') {
    const frame = await captureVideoFrame()
    if (!frame) {
      analyzing.value = false
      return
    }
    fileToSend = frame
    form.append('screenshot', frame)
  } else if (isVideo.value && mode === 'video-multi') {
    const frames = await captureVideoFrames()
    if (frames.length === 0) {
      error.value = 'استخراج فریم از ویدیو ممکن نشد.'
      analyzing.value = false
      return
    }
    fileToSend = frames[0]
    form.append('screenshot', frames[0])
    for (const frame of frames.slice(1)) {
      form.append('frames[]', frame)
    }
  } else if (isVideo.value) {
    form.append('video', selectedFile.value)
  } else {
    form.append('screenshot', fileToSend)
  }

  if (systemPrompt.value.trim()) {
    form.append('system_prompt', systemPrompt.value.trim())
  }
  if (userPrompt.value.trim()) {
    form.append('user_prompt', userPrompt.value.trim())
  }
  if (savePrompt.value) {
    form.append('save_prompt', '1')
  }

  try {
    const result = await api.admin.analyzeTournamentResult(tournamentId.value, form)
    analysis.value = result
    buildRankedRows(result)
    if (savePrompt.value) {
      await refreshPromptConfig()
    }
  } catch (e: unknown) {
    const err = e as { message?: string }
    error.value = err.message || 'خطا در تحلیل رسانه'
    analysis.value = null
    rankedRows.value = []
  } finally {
    analyzing.value = false
  }
}

function moveRow(from: number, to: number) {
  if (from === to || from < 0 || to < 0 || from >= rankedRows.value.length || to >= rankedRows.value.length) {
    return
  }
  const rows = [...rankedRows.value]
  const [item] = rows.splice(from, 1)
  rows.splice(to, 0, item)
  rankedRows.value = rows
}

function onDragStart(index: number) {
  dragIndex.value = index
}

function onDrop(index: number) {
  if (dragIndex.value === null) return
  moveRow(dragIndex.value, index)
  dragIndex.value = null
}

function moveUp(index: number) {
  moveRow(index, index - 1)
}

function moveDown(index: number) {
  moveRow(index, index + 1)
}

function addParticipant(participant: TournamentResultParticipant) {
  rankedRows.value.push({
    key: `add-${participant.user_id}-${Date.now()}`,
    user_id: participant.user_id,
    username: participant.username,
    cod_id: participant.cod_id,
    kills: null,
    rank: null,
  })
}

function removeRow(index: number) {
  rankedRows.value.splice(index, 1)
}

const winnerPreview = computed(() =>
  rankedRows.value.find((row, index) => prizeRankForRow(index, row) === 1 && row.user_id) ?? rankedRows.value[0] ?? null,
)

const prizeTable = computed<Record<number, number>>(() => {
  const raw = analysis.value?.prize_table ?? promptConfig.value?.prize_table ?? {}
  const normalized: Record<number, number> = {}
  for (const [key, value] of Object.entries(raw)) {
    normalized[Number(key)] = Number(value)
  }
  return normalized
})

const hasPrizeTable = computed(() => Object.keys(prizeTable.value).length > 0)

const lastPrizeRank = computed(() => {
  const ranks = Object.keys(prizeTable.value).map(Number).filter((rank) => rank > 0)
  return ranks.length ? Math.max(...ranks) : 0
})

const totalPrizePreview = computed(() =>
  rankedRows.value.reduce((sum, row, index) => sum + prizeAmountForRow(index, row), 0),
)

function splitTeamShares(total: number, count: number): number[] {
  const safeTotal = Math.max(0, Math.round(total))
  const safeCount = Math.max(1, count)
  const base = Math.floor(safeTotal / safeCount)
  const remainder = safeTotal % safeCount
  const shares = Array.from({ length: safeCount }, () => base)
  shares[safeCount - 1] += remainder
  return shares
}

function prizeRankForRow(index: number, row: RankRow): number {
  if (row.rank && row.rank > 0) return row.rank
  return index + 1
}

function teammatesAtRank(rank: number): RankRow[] {
  return rankedRows.value.filter((row, index) => row.user_id && prizeRankForRow(index, row) === rank)
}

function teamPrizeTotal(rank: number): number {
  const amount = prizeTable.value[rank]
  if (amount !== undefined && amount > 0) return amount
  if (!hasPrizeTable.value && rank === 1) {
    return Number(tournament.value?.prize_pool ?? 0)
  }
  return 0
}

function prizeAmountForRow(index: number, row: RankRow): number {
  const rank = prizeRankForRow(index, row)
  const teamTotal = teamPrizeTotal(rank)
  if (teamTotal <= 0) return 0

  const seatMode = tournament.value?.seat_mode ?? 1
  if (seatMode <= 1) return teamTotal

  const teammates = teammatesAtRank(rank)
  const shares = splitTeamShares(teamTotal, Math.max(1, teammates.length))
  const position = teammates.findIndex((item) => item.user_id === row.user_id)
  return shares[position >= 0 ? position : 0] ?? 0
}

function formatToman(amount: number): string {
  return Number(amount || 0).toLocaleString('fa-IR')
}

function matchMethodLabel(method?: string | null) {
  switch (method) {
    case 'cod_id_uid':
    case 'uid':
      return 'تطبیق UID با آیدی کالاف'
    case 'cod_id_uid_suffix':
    case 'uid_suffix':
      return 'تطبیق بخشی UID'
    case 'cod_id_exact':
      return 'تطبیق دقیق آیدی کالاف'
    case 'cod_id_name':
      return 'تطبیق نام در بازی با آیدی کالاف'
    case 'cod_id_skeleton':
    case 'cod_id_uid_skeleton':
      return 'تطبیق آیدی کالاف (بدون نماد)'
    case 'cod_id_uid_name':
      return 'تطبیق UID/نام با آیدی کالاف'
    case 'cod_id_fuzzy_high':
    case 'cod_id_fuzzy':
      return 'تطبیق تقریبی آیدی کالاف'
    case 'cod_id_partial':
      return 'تطبیق جزئی آیدی کالاف'
    case 'team_number':
      return 'تطبیق شماره TEAM'
    default:
      return method || ''
  }
}

async function applyResult() {
  const winner = rankedRows.value[0]
  if (!winner?.user_id) {
    error.value = 'حداقل یک بازیکن در رتبه اول قرار دهید.'
    return
  }

  if (!confirm('نتیجه مسابقه با رتبه‌بندی فعلی ثبت شود؟ جوایز پس از تأیید ادمین واریز می‌شوند.')) {
    return
  }

  applying.value = true
  error.value = null
  success.value = null

  const playerStats = rankedRows.value
    .filter((row) => row.user_id)
    .map((row, index) => ({
      user_id: row.user_id!,
      rank: row.rank ?? prizeRankForRow(index, row),
      kills: row.kills ?? undefined,
    }))

  try {
    const result = await api.admin.applyTournamentResult(tournamentId.value, {
      winner_user_id: winner.user_id,
      player_stats: playerStats,
    })
    success.value = `نتیجه ثبت شد. جوایز در انتظار تأیید است. برنده: ${result.winner_username}`
    setTimeout(() => router.push(`/admin/tournaments/${tournamentId.value}/prizes`), 1500)
  } catch (e: unknown) {
    const err = e as { message?: string }
    error.value = err.message || 'خطا در ثبت نتیجه'
  } finally {
    applying.value = false
  }
}

onBeforeUnmount(() => {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
  }
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-white">ثبت نتیجه با AI</h1>
        <p v-if="tournament" class="text-sm text-gray-400 mt-1">
          {{ tournament.title }}
          <span v-if="promptConfig?.seat_mode_label" class="text-secondary"> — {{ promptConfig.seat_mode_label }}</span>
        </p>
      </div>
      <NuxtLink to="/admin/tournaments" class="text-sm text-secondary">← مسابقات</NuxtLink>
    </div>

    <div v-if="error" class="mb-4 rounded-xl border border-red-700/50 bg-red-900/20 px-4 py-3 text-red-300 text-sm">
      {{ error }}
    </div>
    <div v-if="success" class="mb-4 rounded-xl border border-green-700/50 bg-green-900/20 px-4 py-3 text-green-300 text-sm">
      {{ success }}
    </div>

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-white">۱. پرامپت تحلیل</h2>
        <button
          type="button"
          class="text-xs text-secondary"
          @click="showPromptEditor = !showPromptEditor"
        >
          {{ showPromptEditor ? 'بستن' : 'ویرایش پرامپت' }}
        </button>
      </div>

      <div v-show="showPromptEditor" class="space-y-3 mb-4">
        <div>
          <label class="block text-sm text-gray-400 mb-1">System Prompt</label>
          <textarea
            v-model="systemPrompt"
            rows="8"
            class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm font-mono"
          />
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">User Prompt</label>
          <textarea
            v-model="userPrompt"
            rows="5"
            class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm font-mono"
          />
          <p class="text-xs text-gray-500 mt-1">متغیرها: {tournament_title}، {seat_mode_label}، {participants}، {prize_table}، {last_prize_rank}، {prize_rank_count}</p>
        </div>
        <div v-if="promptConfig?.prize_table_text" class="rounded-lg border border-secondary/30 bg-secondary/5 p-3">
          <p class="text-xs text-secondary font-bold mb-1">جدول جایزه (از توضیحات مسابقه)</p>
          <pre class="text-xs text-gray-300 whitespace-pre-wrap font-sans">{{ promptConfig.prize_table_text }}</pre>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-300">
          <input v-model="savePrompt" type="checkbox">
          ذخیره پرامپت برای این مسابقه
        </label>
      </div>
    </div>

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
      <h2 class="font-bold text-white mb-2">۲. آپلود تصویر یا ویدیو</h2>
      <p class="text-sm text-gray-400 mb-4">
        اسکرین‌شات یا ویدیوی صفحه پایان مسابقه را آپلود کنید. برای ویدیو می‌توانید فریم دلخواه را انتخاب کنید.
        <span v-if="hasPrizeTable && lastPrizeRank > 0" class="block mt-1 text-amber-300/90">
          جایزه تا رتبه {{ lastPrizeRank }} پرداخت می‌شود — AI باید همه رتبه‌ها تا رتبه {{ lastPrizeRank }} را بخواند (نه فقط ۳ تای اول). برای ویدیوی اسکرول‌شده «تحلیل چند فریم» را بزنید.
        </span>
      </p>

      <div class="flex flex-wrap gap-3 items-start">
        <input
          ref="fileInput"
          type="file"
          accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime"
          class="text-sm text-gray-300"
          @change="onFileChange"
        >
        <button
          v-if="!isVideo"
          type="button"
          class="bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white rounded-lg px-4 py-2 text-sm font-bold"
          :disabled="analyzing || !selectedFile"
          @click="analyze('image')"
        >
          {{ analyzing ? 'در حال تحلیل...' : 'تحلیل تصویر' }}
        </button>
        <template v-else>
          <button
            type="button"
            class="bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white rounded-lg px-4 py-2 text-sm font-bold"
            :disabled="analyzing || !selectedFile"
            @click="analyze('video-multi')"
          >
            {{ analyzing ? 'در حال تحلیل...' : 'تحلیل ویدیو' }}
          </button>
          <button
            type="button"
            class="bg-dark-600 hover:bg-dark-500 disabled:opacity-50 text-white rounded-lg px-4 py-2 text-sm font-bold"
            :disabled="analyzing || !selectedFile"
            @click="analyze('video-frame')"
          >
            فقط فریم فعلی
          </button>
        </template>
      </div>

      <img
        v-if="previewUrl && !isVideo"
        :src="previewUrl"
        alt="پیش‌نمایش"
        class="mt-4 max-h-64 rounded-lg border border-dark-600"
      >

      <div v-if="previewUrl && isVideo" class="mt-4 space-y-2">
        <video
          ref="videoRef"
          :src="previewUrl"
          controls
          class="max-h-72 rounded-lg border border-dark-600 w-full max-w-2xl"
        />
        <p class="text-xs text-gray-500">زمان دلخواه را انتخاب کنید و «تحلیل فریم فعلی» را بزنید.</p>
      </div>
    </div>

    <div v-if="analysis" class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
      <h2 class="font-bold text-white mb-2">۳. مرتب‌سازی رتبه‌ها و پیش‌نمایش جوایز</h2>
      <p class="text-sm text-gray-400 mb-2">
        ردیف‌ها را بکشید یا با دکمه‌ها جابه‌جا کنید. رتبه ۱ = برنده.
      </p>
      <p v-if="analysis.vision_model" class="text-xs text-gray-500 mb-2">
        مدل تحلیل: <span dir="ltr">{{ analysis.vision_model }}</span>
      </p>
      <p v-if="hasPrizeTable" class="text-sm text-secondary mb-4">
        جوایز از توضیحات / جدول prize ranks خوانده می‌شود. در بازی تیمی هر مبلغ «جایزه کل تیم» است و بین هم‌تیمی‌ها تقسیم می‌شود.
        مجموع پیش‌نمایش: <span class="font-bold">{{ formatToman(totalPrizePreview) }} تومان</span>
      </p>
      <p v-else class="text-sm text-amber-300/90 mb-4">
        جدول جایزه در توضیحات مسابقه پیدا نشد؛ فقط جایزه نفر اول (prize pool) پیش‌نمایش می‌شود.
      </p>

      <div v-if="analysis.unmatched.length" class="mb-4 rounded-lg border border-amber-700/40 bg-amber-900/10 p-3">
        <p class="text-amber-300 text-sm font-bold mb-2">تشخیص‌های تطبیق‌نیافته AI</p>
        <ul class="text-xs text-gray-400 space-y-1">
          <li v-for="row in analysis.unmatched" :key="'u-' + row.rank">
            رتبه {{ row.rank }} — {{ row.detected_name || '—' }} (UID: {{ row.detected_uid || '—' }})
          </li>
        </ul>
      </div>

      <div class="space-y-2 mb-4">
        <div
          v-for="(row, index) in rankedRows"
          :key="row.key"
          draggable="true"
          class="flex flex-wrap items-center gap-2 bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 cursor-grab active:cursor-grabbing"
          @dragstart="onDragStart(index)"
          @dragover.prevent
          @drop.prevent="onDrop(index)"
        >
          <span class="w-8 h-8 flex items-center justify-center rounded-full bg-secondary text-white text-sm font-bold shrink-0">
            {{ prizeRankForRow(index, row) }}
          </span>
          <span class="text-gray-500 text-lg shrink-0">⠿</span>
          <div class="flex-1 min-w-[180px]">
            <p class="text-white font-bold" dir="ltr">{{ row.cod_id || '—' }}</p>
            <p v-if="row.username" class="text-xs text-gray-500">{{ row.username }}</p>
            <p v-if="row.detected_name" class="text-xs text-gray-400">
              تشخیص AI: {{ row.detected_name }}
              <span v-if="row.match_method" class="text-gray-500">
                — {{ matchMethodLabel(row.match_method) }}
                <span v-if="row.match_score"> ({{ Math.round(row.match_score * 100) }}%)</span>
              </span>
            </p>
          </div>
          <span v-if="row.detected_uid" class="text-xs text-gray-500 shrink-0" dir="ltr">UID: {{ row.detected_uid }}</span>
          <span
            v-if="prizeAmountForRow(index, row) > 0"
            class="text-xs font-bold text-green-300 shrink-0"
          >
            {{ formatToman(prizeAmountForRow(index, row)) }} ت
          </span>
          <input
            v-model.number="row.kills"
            type="number"
            min="0"
            placeholder="کیل"
            class="w-20 bg-dark-800 border border-dark-600 rounded px-2 py-1 text-white text-sm"
          >
          <div class="flex gap-1 shrink-0">
            <button type="button" class="px-2 py-1 text-xs bg-dark-600 rounded" :disabled="index === 0" @click="moveUp(index)">↑</button>
            <button type="button" class="px-2 py-1 text-xs bg-dark-600 rounded" :disabled="index === rankedRows.length - 1" @click="moveDown(index)">↓</button>
            <button type="button" class="px-2 py-1 text-xs bg-red-900/50 text-red-300 rounded" @click="removeRow(index)">×</button>
          </div>
        </div>
      </div>

      <div v-if="unusedParticipants.length" class="flex flex-wrap gap-2 items-center mb-6">
        <span class="text-sm text-gray-400">افزودن بازیکن:</span>
        <button
          v-for="p in unusedParticipants"
          :key="'add-' + p.user_id"
          type="button"
          class="text-xs bg-dark-600 hover:bg-dark-500 text-white rounded px-2 py-1"
          @click="addParticipant(p)"
        >
          + {{ p.cod_id || p.username }}
        </button>
      </div>

      <div class="flex flex-wrap gap-3 items-center border-t border-dark-600 pt-4">
        <div v-if="winnerPreview" class="text-sm text-green-300">
          برنده: <span class="font-bold" dir="ltr">{{ winnerPreview.cod_id || winnerPreview.username }}</span> (رتبه ۱)
        </div>
        <button
          type="button"
          class="bg-success hover:bg-green-700 disabled:opacity-50 text-white rounded-lg px-4 py-2 text-sm font-bold mr-auto"
          :disabled="applying || !winnerPreview?.user_id"
          @click="applyResult"
        >
          {{ applying ? 'در حال ثبت...' : 'ثبت نتیجه' }}
        </button>
      </div>
    </div>
  </div>
</template>
