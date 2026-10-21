import { watch } from 'vue'

const SITE = 'La veritable Tatasasa'

/**
 * Titre de l'onglet. C'est aussi la premiere chose qu'annonce un lecteur d'ecran apres
 * une navigation : un titre identique partout ne dit pas ou l'on est arrive.
 */
export function poserTitre(partie?: string | null): void {
  document.title = partie ? `${partie} — ${SITE}` : `${SITE} — carnet d'aquarelles`
}

/**
 * Titre REEL d'une vue dont la donnee arrive apres coup (oeuvre, pigment, serie).
 * Tant que la source rend `null`, le titre generique pose par le routeur reste en place.
 *
 * La route n'est volontairement pas observee : en la quittant, la vue sortante
 * reecrirait son titre par-dessus celui de la page d'arrivee avant d'etre demontee.
 */
export function useTitre(source: () => string | null | undefined): void {
  watch(
    source,
    (partie) => {
      if (partie) {
        poserTitre(partie)
      }
    },
    { immediate: true },
  )
}
