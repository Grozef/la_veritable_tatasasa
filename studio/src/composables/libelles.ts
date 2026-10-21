import type { PaperGrain, SocialNetwork, WorkStatus } from '@/api/types'

/**
 * Libelles affiches des valeurs d'enum de l'API. Les VALEURS restent celles du contrat
 * (`exposee`, `privee`...) : seul l'affichage passe ici. Un `Record` sur le type d'API
 * oblige a nommer toute valeur ajoutee au contrat.
 */
export const libellesStatut: Record<WorkStatus, string> = {
  atelier: 'Atelier',
  exposee: 'Exposée',
  vendue: 'Vendue',
  privee: 'Privée',
}

export const libellesGrain: Record<PaperGrain, string> = {
  torchon: 'Torchon',
  satine: 'Satiné',
  fin: 'Fin',
}

export const libellesReseau: Record<SocialNetwork, string> = {
  instagram: 'Instagram',
  facebook: 'Facebook',
  whatsapp: 'WhatsApp',
  autre: 'Autre',
}

/** Une valeur inconnue (API plus recente que l'app) s'affiche telle quelle plutot que vide. */
export function libelle<T extends string>(table: Record<T, string>, valeur: string): string {
  return (table as Record<string, string>)[valeur] ?? valeur
}

const deuxDecimales = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

/** Valeur d'un reglage a l'ecran : virgule decimale francaise (« 0,25 »), jamais le point de `toFixed`. */
export function valeurReglage(valeur: number): string {
  return deuxDecimales.format(valeur)
}

const regles = new Intl.PluralRules('fr-FR')

/** Accord en nombre a la francaise : singulier pour 0 et 1, pluriel a partir de 2. */
export function accorder(nombre: number, singulier: string, pluriel: string): string {
  return regles.select(nombre) === 'one' ? singulier : pluriel
}
