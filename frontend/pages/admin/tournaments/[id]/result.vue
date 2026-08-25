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
  detected_name?: string | null
  detected_uid?: string | null
  match_method?: string | null
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

  const canvas = document.createElement('canvas')
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  const ctx = canvas.getContext('2d')
  if (!ctx) return null

  ctx.drawImage(video, 0, 0, canvas.width, canvas.height)

  return new Promise((resolve) => {
    canvas.toBlob((blob) => {
      if (!blob) {
        resolve(null)
        return
      }
      resolve(new File([blob], 'frame.jpg', { type: 'image/jpeg' }))
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
      detected_name: row.detected_name,
      detected_uid: row.detected_uid,
      match_method: row.match_method,
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
    })
  }

  rankedRows.value = rows
}

async function analyze(useVideoFrame = false) {
  if (!selectedFile.value) {
    error.value = 'لطفاً تصویر یا ویدیو انتخاب کنید.'
    return
  }

  analyzing.value = true
  error.value = null
  success.value = null

  const form = new FormData()
  let fileToSend: File = selectedFile.value

  if (isVideo.value && useVideoFrame) {
    const frame = await captureVideoFrame()
    if (!frame) {
      analyzing.value = false
      return
    }
    fileToSend = frame
    form.append('screenshot', frame)
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
  })
}

function removeRow(index: number) {
  rankedRows.value.splice(index, 1)
}

const winnerPreview = computed(() => rankedRows.value[0] ?? null)

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
      rank: index + 1,
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
          <p class="text-xs text-gray-500 mt-1">متغیرها: {tournament_title}، {seat_mode_label}، {participants}</p>
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
          @click="analyze(false)"
        >
          {{ analyzing ? 'در حال تحلیل...' : 'تحلیل تصویر' }}
        </button>
        <template v-else>
          <button
            type="button"
            class="bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white rounded-lg px-4 py-2 text-sm font-bold"
            :disabled="analyzing || !selectedFile"
            @click="analyze(true)"
          >
            {{ analyzing ? 'در حال تحلیل...' : 'تحلیل فریم فعلی' }}
          </button>
          <button
            type="button"
            class="bg-dark-600 hover:bg-dark-500 disabled:opacity-50 text-white rounded-lg px-4 py-2 text-sm font-bold"
            :disabled="analyzing || !selectedFile"
            @click="analyze(false)"
          >
            ارسال ویدیو (ffmpeg)
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
      <h2 class="font-bold text-white mb-2">۳. مرتب‌سازی رتبه‌ها</h2>
      <p class="text-sm text-gray-400 mb-4">
        ردیف‌ها را بکشید یا با دکمه‌ها جابه‌جا کنید. رتبه ۱ = برنده.
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
            {{ index + 1 }}
          </span>
          <span class="text-gray-500 text-lg shrink-0">⠿</span>
          <div class="flex-1 min-w-[180px]">
            <p class="text-white font-bold">{{ row.username || '—' }}</p>
            <p v-if="row.detected_name" class="text-xs text-gray-400">تشخیص: {{ row.detected_name }}</p>
          </div>
          <span class="text-xs text-gray-500" dir="ltr">{{ row.cod_id || '—' }}</span>
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
          + {{ p.username }}
        </button>
      </div>

      <div class="flex flex-wrap gap-3 items-center border-t border-dark-600 pt-4">
        <div v-if="winnerPreview" class="text-sm text-green-300">
          برنده: <span class="font-bold">{{ winnerPreview.username }}</span> (رتبه ۱)
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
