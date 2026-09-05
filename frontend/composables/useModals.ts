import { ref } from 'vue'
import type { Tournament } from '~/types/api'

const gameLoginOpen = ref(false)
const gameLoginTitle = ref('')
const gameLoginContent = ref('')
const gameLoginSeat = ref<string | null>(null)
const gameLoginTournamentId = ref<number | string | null>(null)
const gameLoginLoading = ref(false)

const descriptionOpen = ref(false)
const descriptionTitle = ref('')
const descriptionContent = ref('')

const registerOpen = ref(false)
const registerTournament = ref<Tournament | null>(null)
/** Blocks ghost clicks on card buttons right after register modal closes. */
const suppressDescriptionUntil = ref(0)
/** Blocks reopening the register modal right after submit/navigation. */
const suppressRegisterUntil = ref(0)
const registrationNavigating = ref(false)

export function useModals() {
  const api = useApi()

  function armDescriptionSuppression(ms = 600) {
    suppressDescriptionUntil.value = Date.now() + ms
  }

  function armRegisterSuppression(ms = 2000) {
    suppressRegisterUntil.value = Date.now() + ms
  }

  function openDescriptionModal(title: string, content: string) {
    if (
      registerOpen.value
      || registrationNavigating.value
      || Date.now() < suppressDescriptionUntil.value
    ) {
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
    gameLoginTournamentId.value = null
  }

  async function openGameLoginModalById(tournamentId: number | string) {
    gameLoginOpen.value = true
    gameLoginLoading.value = true
    gameLoginTitle.value = 'در حال بارگذاری...'
    gameLoginContent.value = ''
    gameLoginSeat.value = null
    gameLoginTournamentId.value = tournamentId

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
    if (registrationNavigating.value || Date.now() < suppressRegisterUntil.value) {
      return
    }
    if (tournament.pending_seat) {
      void navigateTo(`/tournaments/${tournament.id}/select-seat`)
      return
    }
    closeDescriptionModal()
    armDescriptionSuppression(1500)
    registerTournament.value = tournament
    registerOpen.value = true
  }

  function clearRegisterBodyLock() {
    registrationNavigating.value = false
    if (import.meta.client) {
      document.body.classList.remove('register-modal-active')
      document.body.classList.remove('registration-navigating')
    }
  }

  function beginRegistrationNavigation() {
    registrationNavigating.value = true
    armDescriptionSuppression(5000)
    if (import.meta.client) {
      document.body.classList.remove('register-modal-active')
      document.body.classList.add('registration-navigating')
    }
  }

  function closeRegisterModal(options?: { keepNavigating?: boolean }) {
    registerOpen.value = false
    registerTournament.value = null
    if (options?.keepNavigating) {
      armDescriptionSuppression(5000)
      if (import.meta.client) {
        document.body.classList.remove('register-modal-active')
        document.body.classList.add('registration-navigating')
      }
    } else {
      clearRegisterBodyLock()
    }
    armDescriptionSuppression(options?.keepNavigating ? 5000 : 2000)
    armRegisterSuppression(2000)
  }

  return {
    gameLoginOpen,
    gameLoginTitle,
    gameLoginContent,
    gameLoginSeat,
    gameLoginTournamentId,
    gameLoginLoading,
    descriptionOpen,
    descriptionTitle,
    descriptionContent,
    registerOpen,
    registerTournament,
    registrationNavigating,
    openDescriptionModal,
    closeDescriptionModal,
    openGameLoginModal,
    closeGameLoginModal,
    openGameLoginModalById,
    openRegisterModal,
    closeRegisterModal,
    beginRegistrationNavigation,
    armDescriptionSuppression,
    armRegisterSuppression,
    clearRegisterBodyLock,
  }
}
