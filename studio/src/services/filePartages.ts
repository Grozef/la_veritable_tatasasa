import { Preferences } from '@capacitor/preferences'
import type { SocialNetwork } from '@/api/types'
import { api, ErreurApi } from './api'

/**
 * File des traces de partage.
 *
 * Meme regle que pour les photos : aucune ecriture ne part en appel direct. Un partage
 * se fait souvent hors ligne — la feuille native depose le visuel dans une application
 * qui, elle, attendra le reseau — et la trace doit survivre a la fermeture de l'app,
 * sinon l'historique de l'atelier ment par omission.
 *
 * File SEPAREE de celle des photos : elle ne transporte que du texte, aucun fichier a
 * ecrire ni a nettoyer, et un envoi de photo bloque ne doit pas retenir une trace.
 */
const CLE = 'file-partages'

export interface Trace {
  id: string
  ulid: string
  network: SocialNetwork
  caption: string | null
  /** Date du PARTAGE, pas de l'envoi : c'est elle qui fait foi dans l'historique. */
  postedAt: string
  erreur: string | null
}

export async function filePartages(): Promise<Trace[]> {
  const { value } = await Preferences.get({ key: CLE })

  return value ? (JSON.parse(value) as Trace[]) : []
}

async function ecrire(traces: Trace[]): Promise<void> {
  await Preferences.set({ key: CLE, value: JSON.stringify(traces) })
}

export async function enfilerTrace(
  ulid: string,
  network: SocialNetwork,
  caption: string | null,
): Promise<Trace> {
  const trace: Trace = {
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    ulid,
    network,
    caption,
    postedAt: new Date().toISOString(),
    erreur: null,
  }

  await ecrire([...(await filePartages()), trace])

  return trace
}

let enCours = false

export async function viderPartages(): Promise<{ envoyes: number; restants: number }> {
  if (enCours) {
    return { envoyes: 0, restants: (await filePartages()).length }
  }

  enCours = true
  let envoyes = 0

  try {
    for (const trace of await filePartages()) {
      if (trace.erreur !== null) {
        continue
      }

      try {
        await api.tracerPartage(trace.ulid, {
          network: trace.network,
          caption: trace.caption,
          posted_at: trace.postedAt,
        })

        await ecrire((await filePartages()).filter((candidate) => candidate.id !== trace.id))
        envoyes++
      } catch (cause) {
        // Panne reseau : on garde tout en place et on reessaiera. Refus du serveur
        // (oeuvre supprimee, reseau inconnu) : insister ne le guerira pas.
        if (cause instanceof ErreurApi && cause.horsLigne) {
          break
        }

        await ecrire((await filePartages()).map((candidate) => (
          candidate.id === trace.id
            ? { ...candidate, erreur: cause instanceof Error ? cause.message : 'Trace refusée' }
            : candidate
        )))
      }
    }
  } finally {
    enCours = false
  }

  return { envoyes, restants: (await filePartages()).length }
}

export async function retirerTrace(id: string): Promise<void> {
  await ecrire((await filePartages()).filter((trace) => trace.id !== id))
}
