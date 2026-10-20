import { Preferences } from '@capacitor/preferences'
import type { MediaRole } from '@/api/types'
import { api, ErreurApi, type BrouillonOeuvre } from './api'
import { ecrire, lire, supprimer } from './fichiers'

/**
 * File d'envoi persistee.
 *
 * Une aquarelle se photographie dans l'atelier, pas devant une borne wifi : toute
 * ecriture passe ici et JAMAIS par un appel direct. La file survit a la fermeture de
 * l'app — c'est la difference entre « la photo partira » et « la photo est perdue ».
 */
const CLE = 'file-envois'

export interface FichierEnAttente {
  nom: string
  mime: string
  role: MediaRole
  position: number
  envoye: boolean
}

export interface Envoi {
  id: string
  oeuvre: BrouillonOeuvre
  /** Rempli des que l'oeuvre existe cote serveur : une reprise ne doit pas la creer deux fois. */
  ulid: string | null
  fichiers: FichierEnAttente[]
  creeLe: string
  /** Dernier refus DEFINITIF du serveur. Une panne reseau ne s'ecrit pas ici. */
  erreur: string | null
}

export async function file(): Promise<Envoi[]> {
  const { value } = await Preferences.get({ key: CLE })

  return value ? (JSON.parse(value) as Envoi[]) : []
}

async function ecrireFile(envois: Envoi[]): Promise<void> {
  await Preferences.set({ key: CLE, value: JSON.stringify(envois) })
}

/**
 * Met une publication en file. Les images sont ecrites AVANT l'entree de file : une
 * entree qui reference un fichier absent serait irrattrapable.
 */
export async function enfiler(
  oeuvre: BrouillonOeuvre,
  images: { blob: Blob; role: MediaRole; position: number }[],
): Promise<Envoi> {
  const id = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
  const fichiers: FichierEnAttente[] = []

  for (const image of images) {
    const nom = `${id}-${image.role}.${image.blob.type === 'image/png' ? 'png' : 'jpg'}`

    await ecrire(nom, image.blob)
    fichiers.push({ nom, mime: image.blob.type, role: image.role, position: image.position, envoye: false })
  }

  const envoi: Envoi = { id, oeuvre, ulid: null, fichiers, creeLe: new Date().toISOString(), erreur: null }

  await ecrireFile([...(await file()), envoi])

  return envoi
}

let enCours = false

/**
 * Vide la file, dans l'ordre d'arrivee.
 *
 * Deux natures d'echec, traitees differemment : une panne RESEAU arrete le vidage et
 * laisse tout en place pour la prochaine tentative ; un refus du SERVEUR (validation,
 * oeuvre supprimee) est definitif — reessayer en boucle ne le guerira pas, l'entree
 * reste en file avec son motif, a la vue de l'artiste.
 */
export async function vider(): Promise<{ envoyes: number; restants: number }> {
  if (enCours) {
    return { envoyes: 0, restants: (await file()).length }
  }

  enCours = true
  let envoyes = 0

  try {
    for (const envoi of await file()) {
      if (envoi.erreur !== null) {
        continue
      }

      try {
        await traiter(envoi)
        await retirer(envoi.id)
        envoyes++
      } catch (cause) {
        if (cause instanceof ErreurApi && cause.horsLigne) {
          break
        }

        await marquer(envoi.id, cause instanceof Error ? cause.message : 'Envoi refuse')
      }
    }
  } finally {
    enCours = false
  }

  return { envoyes, restants: (await file()).length }
}

async function traiter(envoi: Envoi): Promise<void> {
  if (envoi.ulid === null) {
    const oeuvre = await api.creerOeuvre(envoi.oeuvre)

    envoi.ulid = oeuvre.ulid
    await majEnvoi(envoi)
  }

  for (const fichier of envoi.fichiers) {
    if (fichier.envoye) {
      continue
    }

    await api.televerserMedia(
      envoi.ulid,
      await lire(fichier.nom, fichier.mime),
      fichier.role,
      fichier.position,
      fichier.nom,
    )

    // Marque au fil de l'eau : une coupure entre deux images ne doit pas faire
    // renvoyer celle qui est deja arrivee.
    fichier.envoye = true
    await majEnvoi(envoi)
    await supprimer(fichier.nom)
  }
}

async function majEnvoi(envoi: Envoi): Promise<void> {
  await ecrireFile((await file()).map((candidat) => (candidat.id === envoi.id ? envoi : candidat)))
}

async function marquer(id: string, erreur: string): Promise<void> {
  await ecrireFile((await file()).map((envoi) => (envoi.id === id ? { ...envoi, erreur } : envoi)))
}

export async function retirer(id: string): Promise<void> {
  const envois = await file()
  const condamne = envois.find((envoi) => envoi.id === id)

  for (const fichier of condamne?.fichiers ?? []) {
    await supprimer(fichier.nom)
  }

  await ecrireFile(envois.filter((envoi) => envoi.id !== id))
}

/** Remet en file un envoi refuse, une fois la cause corrigee. */
export async function reprendre(id: string): Promise<void> {
  await ecrireFile((await file()).map((envoi) => (envoi.id === id ? { ...envoi, erreur: null } : envoi)))
}
