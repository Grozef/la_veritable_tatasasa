import { computed, ref } from 'vue'
import type { Work } from '@/api/types'
import { api, ErreurApi } from '@/services/api'
import { filePartages, viderPartages, type Trace } from '@/services/filePartages'
import { file, vider, type Envoi } from '@/services/queue'
import { jeton } from '@/services/session'

/**
 * Etat partage de l'atelier.
 *
 * Un module reactif plutot que Pinia : l'app a UN etat, celui de l'artiste connectee, et
 * ajouter une dependance pour un seul magasin coute plus qu'il ne rapporte. La
 * convention de dossier (`stores/`) est conservee.
 */
const oeuvres = ref<Work[]>([])
const envois = ref<Envoi[]>([])
const traces = ref<Trace[]>([])
const connecte = ref(false)
const chargement = ref(false)
const erreur = ref<string | null>(null)

export function useAtelier() {
  /** Au demarrage : un jeton en stock ne prouve pas qu'il est encore valide, on le rejoue. */
  async function reprendreSession(): Promise<boolean> {
    if (!(await jeton())) {
      connecte.value = false

      return false
    }

    try {
      await rafraichir()
      connecte.value = true
    } catch (cause) {
      connecte.value = !(cause instanceof ErreurApi && cause.statut === 401)
    }

    return connecte.value
  }

  async function connexion(email: string, motDePasse: string, appareil: string): Promise<void> {
    await api.connexion(email, motDePasse, appareil)
    connecte.value = true
    await rafraichir()
  }

  async function deconnexion(): Promise<void> {
    await api.deconnexion()
    connecte.value = false
    oeuvres.value = []
  }

  async function rafraichir(): Promise<void> {
    chargement.value = true
    erreur.value = null

    try {
      oeuvres.value = await api.oeuvres()
    } catch (cause) {
      // Hors ligne, la liste deja chargee reste a l'ecran : l'artiste doit pouvoir
      // photographier dans un atelier sans reseau.
      erreur.value = cause instanceof Error ? cause.message : 'Chargement impossible'
      throw cause
    } finally {
      chargement.value = false
      await relireFile()
    }
  }

  async function relireFile(): Promise<void> {
    envois.value = await file()
    traces.value = await filePartages()
  }

  /**
   * Tente d'ecouler les DEUX files puis remet la liste a jour si quelque chose est parti.
   *
   * Les traces de partage ont leur propre file : un televersement de photo bloque ne doit
   * pas retenir une trace, qui ne pese que quelques octets.
   */
  async function ecoulerFile(): Promise<void> {
    const photos = await vider()
    const traces = await viderPartages()

    await relireFile()

    if (photos.envoyes + traces.envoyes > 0) {
      await rafraichir().catch(() => undefined)
    }
  }

  return {
    oeuvres,
    envois,
    traces,
    connecte,
    chargement,
    erreur,
    enAttente: computed(() => envois.value.length + traces.value.length),
    reprendreSession,
    connexion,
    deconnexion,
    rafraichir,
    relireFile,
    ecoulerFile,
  }
}
