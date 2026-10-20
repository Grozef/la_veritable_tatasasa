import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/api/client'
import type { Pigment, Work } from '@/api/types'

/**
 * Cache du carnet et du nuancier.
 *
 * Le filtrage se fait COTE CLIENT une fois les oeuvres chargees : cliquer une pastille
 * doit reduire la grille instantanement. Un aller-retour reseau par pastille rendrait
 * la navigation par la couleur — qui remplace le menu — poussive.
 */
export const useWorksStore = defineStore('works', () => {
  const works = ref<Work[]>([])
  const pigments = ref<Pigment[]>([])
  const selection = ref<string[]>([])
  const chargement = ref(false)
  const erreur = ref<string | null>(null)

  let promesse: Promise<void> | null = null

  /** Idempotent : plusieurs vues peuvent l'appeler, le reseau n'est sollicite qu'une fois. */
  function charger(): Promise<void> {
    promesse ??= (async () => {
      chargement.value = true
      erreur.value = null

      try {
        const [oeuvres, nuancier] = await Promise.all([api.works(), api.pigments()])
        works.value = oeuvres
        pigments.value = nuancier
      } catch (cause) {
        erreur.value = cause instanceof Error ? cause.message : 'Chargement impossible'
        // Sans cela, un echec reseau resterait definitif : plus aucun appel ne repartirait.
        promesse = null
      } finally {
        chargement.value = false
      }
    })()

    return promesse
  }

  /**
   * INTERSECTION et non union : selectionner outremer puis ocre demande les oeuvres qui
   * portent les deux. C'est la promesse du nuancier — croiser des teintes, pas les cumuler.
   */
  const filtrees = computed(() =>
    selection.value.length === 0
      ? works.value
      : works.value.filter((work) =>
          selection.value.every((slug) => work.pigments.some((pigment) => pigment.slug === slug)),
        ),
  )

  function basculer(slug: string): void {
    selection.value = selection.value.includes(slug)
      ? selection.value.filter((retenu) => retenu !== slug)
      : [...selection.value, slug]
  }

  function selectionner(slugs: string[]): void {
    selection.value = slugs
  }

  const estSelectionne = (slug: string) => selection.value.includes(slug)

  const parSlug = (slug: string) => works.value.find((work) => work.slug === slug) ?? null

  return {
    works,
    pigments,
    selection,
    chargement,
    erreur,
    filtrees,
    charger,
    basculer,
    selectionner,
    estSelectionne,
    parSlug,
  }
})
