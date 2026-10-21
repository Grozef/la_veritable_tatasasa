import type { Work } from '@/api/types'

export interface Vignette {
  srcset: string | null
  src: string | null
  /** Couleur dominante de la photo, pour que la case ne soit pas vide pendant le chargement. */
  fond: string | null
}

/**
 * Vignette du carnet, tiree de l'image principale deja livree par `GET /studio/works` :
 * `sources.webp` y est un srcset pret a poser (derivees 400w et 800w). Sans principale, ou
 * avant que la derivation ait tourne (`sources` a `null`), il ne reste que la couleur.
 */
export function vignette(oeuvre: Work): Vignette {
  const principale = oeuvre.media.find((media) => media.role === 'principal')
  const srcset = principale?.sources?.webp ?? null

  return {
    srcset,
    // La plus petite derivee, en tete du srcset : repli pour un navigateur qui ignorerait srcset.
    src: srcset ? srcset.split(',')[0].trim().split(/\s+/)[0] : null,
    fond: principale?.lqipHex ?? null,
  }
}
