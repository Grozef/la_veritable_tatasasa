import type { Pigment, Series, Work } from './types'

/**
 * Seul point de sortie reseau du portfolio. Aucun composant n'appelle `fetch`.
 *
 * Chemin relatif : en developpement le proxy de `vite.config.ts` renvoie sur
 * `php artisan serve`, en production le front et l'API partagent le meme hote.
 */
const BASE = '/api'

/** Les ressources Laravel enveloppent toujours leur charge dans `data`. */
interface Enveloppe<T> {
  data: T
}

async function get<T>(chemin: string): Promise<T> {
  const reponse = await fetch(`${BASE}${chemin}`, {
    headers: { Accept: 'application/json' },
  })

  if (!reponse.ok) {
    throw new Error(`${chemin} a rendu ${reponse.status}`)
  }

  return ((await reponse.json()) as Enveloppe<T>).data
}

export const api = {
  /** Le carnet : toutes les oeuvres publiees, non paginees. */
  works: () => get<Work[]>('/works'),

  /** Le nuancier, deja restreint aux pigments portes par une oeuvre publiee. */
  pigments: () => get<Pigment[]>('/pigments'),

  series: (slug: string) => get<Series>(`/series/${slug}`),
}
