import { ref } from 'vue'
import type { Tournament } from '~/types/api'

const gameLoginOpen = ref(false)
const gameLoginTitle = ref('')
const gameLoginContent = ref('')
const gameLoginSeat = ref<string | null>(null)
const gameLoginLoading = ref(false)

const descriptionOpen = ref(false)
const descriptionTitle = ref('')
const descriptionContent = ref('')

const registerOpen = ref(false)
const registerTournament = ref<Tournament | null>(null)
/** Blocks ghost clicks on card buttons right after register modal closes. */
const suppressDescriptionUntil = ref(0)

export function useModals() {
  const api = useApi()

  function armDescriptionSuppression(ms = 600) {
    suppressDescriptionUntil.value = Date.now() + ms
  }

  function openDescriptionModal(title: string, content: string) {
    if (registerOpen.value || Date.now() < suppressDescriptionUntil.value) {
      return
    }
    descriptionTitle.value = title
    descriptionContent.value = content
    descriptionOpen.value = true
  }

  function closeDescriptionModal() {
    descriptionOpen.value = false
  }

  function openGameLoginModal(title: string, content: string, seatLabel?: string | null) {
    gameLoginTitle.value = title
    gameLoginContent.value = content
    gameLoginSeat.value = seatLabel ?? null
    gameLoginOpen.value = true
  }

  function closeGameLoginModal() {
    gameLoginOpen.value = false
  }

  async function openGameLoginModalById(tournamentId: number | string) {
    gameLoginOpen.value = true
    gameLoginLoading.value = true
    gameLoginTitle.value = 'در حال بارگذاری...'
    gameLoginContent.value = ''
    gameLoginSeat.value = null

    try {
      const data = await api.tournaments.gameLogin(tournamentId)
      if (data.error) {
        gameLoginTitle.value = 'خطا'
        gameLoginContent.value = data.error
        return
      }
      openGameLoginModal(data.title, data.content, data.seat_label || String(data.seat_number ?? ''))
    } catch {
      gameLoginTitle.value = 'خطا'
      gameLoginContent.value = 'بارگذاری اطلاعات ورود ممکن نشد.'
    } finally {
      gameLoginLoading.value = false
    }
  }

  function openRegisterModal(tournament: Tournament) {
    const auth = useAuthStore()
    if (!auth.isAuthenticated) {
      void navigateTo('/login')
      return
    }
    closeDescriptionModal()
    armDescriptionSuppression(1200)
    registerTournament.value = tournament
    registerOpen.value = true
  }

  function closeRegisterModal() {
    registerOpen.value = false
    registerTournament.value = null
    armDescriptionSuppression(1200)
  }

  return {
    gameLoginOpen,
    gameLoginTitle,
    gameLoginContent,
    gameLoginSeat,
    gameLoginLoading,
    descriptionOpen,
    descriptionTitle,
    descriptionContent,
    registerOpen,
    registerTournament,
    openDescriptionModal,
    closeDescriptionModal,
    openGameLoginModal,
    closeGameLoginModal,
    openGameLoginModalById,
    openRegisterModal,
    closeRegisterModal,
    armDescriptionSuppression,
  }
}
