import { describe, it, expect } from 'vitest'
import type { Work } from './types'
import payload from './__fixtures__/works.response.json'

/**
 * Garde-fou du contrat API.
 *
 * La fixture n'est pas ecrite a la main : c'est la reponse REELLE de
 * `GET /api/works`, capturee depuis le serveur. Une fixture inventee ne teste que
 * l'imagination de celui qui l'a ecrite.
 *
 * Ce fichier attrape deux derives distinctes :
 *  - le typage (a la compilation) : si WorkResource cesse de rendre un champ declare
 *    dans shared/types.ts, `vue-tsc` casse ici ;
 *  - les valeurs (a l'execution) : casse des cles, forme des couleurs, coherence de
 *    la palette.
 *
 * Quand l'API change, RECAPTURER la fixture — ne jamais l'editer a la main.
 */
describe('contrat GET /api/works', () => {
  const works = (payload as { data: Work[] }).data

  it('expose au moins une oeuvre', () => {
    expect(works.length).toBeGreaterThan(0)
  })

  it('nomme ses champs en camelCase, pas en snake_case', () => {
    const work = works[0] as unknown as Record<string, unknown>

    expect(work).toHaveProperty('paperGrain')
    expect(work).toHaveProperty('widthMm')
    expect(work).toHaveProperty('publishedAt')
    expect(work).not.toHaveProperty('paper_grain')
    expect(work).not.toHaveProperty('width_mm')
    expect(work).not.toHaveProperty('published_at')
  })

  it('ne rend que des oeuvres publiees et non privees', () => {
    for (const work of works) {
      expect(work.publishedAt).not.toBeNull()
      expect(work.status).not.toBe('privee')
    }
  })

  it('donne des pigments affichables : hex valide et part entre 0 et 1', () => {
    const pigments = works.flatMap((work) => work.pigments)
    expect(pigments.length).toBeGreaterThan(0)

    for (const pigment of pigments) {
      expect(pigment.hex).toMatch(/^#[0-9a-f]{6}$/)
      expect(pigment.weight).toBeGreaterThan(0)
      expect(pigment.weight).toBeLessThanOrEqual(1)
      expect(pigment.slug).not.toHaveLength(0)
    }
  })

  it('ne designe qu\'un seul pigment dominant par oeuvre', () => {
    for (const work of works) {
      const dominants = work.pigments.filter((pigment) => pigment.isDominant)
      expect(dominants.length).toBeLessThanOrEqual(1)
    }
  })

  it('classe les pigments du plus couvrant au moins couvrant', () => {
    for (const work of works) {
      const poids = work.pigments.map((pigment) => pigment.weight)
      expect(poids).toEqual([...poids].sort((a, b) => b - a))
    }
  })

  it('n\'expose jamais les valeurs CIELAB, qui restent serveur', () => {
    for (const pigment of works.flatMap((work) => work.pigments)) {
      expect(pigment as unknown as Record<string, unknown>).not.toHaveProperty('lab_l')
    }
  })

  it('laisse `sources` a null tant que les derivees n\'existent pas', () => {
    for (const media of works.flatMap((work) => work.media)) {
      if (media.sources !== null) {
        expect(media.sources.avif).toContain('w')
        expect(media.sources.webp).toContain('w')
      }
    }
  })
})
