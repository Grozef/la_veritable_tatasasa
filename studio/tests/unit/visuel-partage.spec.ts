// @vitest-environment node

import { describe, expect, it } from 'vitest'
import { disposition } from '@/composables/useVisuelPartage'

describe('disposition du visuel de partage', () => {
  it('rend un carre et un 4:5 aux proportions exactes', () => {
    expect(disposition('carre', 100, 100).toile).toEqual({ largeur: 1080, hauteur: 1080 })
    expect(disposition('portrait', 100, 100).toile).toEqual({ largeur: 1080, hauteur: 1350 })
  })

  it('conserve le rapport de l image, sans jamais la rogner', () => {
    const paysage = disposition('carre', 1600, 1200)
    const portrait = disposition('carre', 1200, 1600)

    expect(paysage.image.largeur / paysage.image.hauteur).toBeCloseTo(1600 / 1200, 2)
    expect(portrait.image.largeur / portrait.image.hauteur).toBeCloseTo(1200 / 1600, 2)
  })

  it('borne une image tres large par la largeur et une image tres haute par la hauteur', () => {
    const plan = disposition('portrait', 4000, 500)
    const cadreLargeur = 1080 - plan.image.x * 2

    expect(plan.image.largeur).toBe(cadreLargeur)

    const haute = disposition('portrait', 500, 4000)

    expect(haute.image.hauteur).toBeLessThanOrEqual(haute.filet.y - haute.image.y)
  })

  /** Le defaut invisible : un titre pose sous le bord de la toile, ou par-dessus l image. */
  it('garde le texte dans la toile et sous l image, quel que soit le format', () => {
    for (const format of ['carre', 'portrait'] as const) {
      for (const [largeur, hauteur] of [[1600, 1200], [1200, 1600], [4000, 500], [500, 4000]]) {
        const plan = disposition(format, largeur, hauteur)

        expect(plan.image.y + plan.image.hauteur).toBeLessThanOrEqual(plan.filet.y)
        expect(plan.titre.y).toBeGreaterThan(plan.filet.y)
        expect(plan.titre.y).toBeLessThanOrEqual(plan.toile.hauteur)
        expect(plan.annee.y).toBeLessThanOrEqual(plan.toile.hauteur)
      }
    }
  })

  it('centre l image horizontalement et garde des marges symetriques', () => {
    const plan = disposition('portrait', 800, 600)

    expect(plan.image.x).toBe(Math.round((1080 - plan.image.largeur) / 2))
    expect(plan.filet.x + plan.filet.largeur).toBe(1080 - plan.filet.x)
    expect(plan.annee.x).toBe(1080 - plan.filet.x)
  })

  it('reste proportionnel quand on change le cote de sortie', () => {
    const grand = disposition('carre', 1600, 1200, 1080)
    const petit = disposition('carre', 1600, 1200, 540)

    expect(petit.toile.largeur).toBe(540)
    expect(petit.image.largeur / petit.toile.largeur).toBeCloseTo(grand.image.largeur / grand.toile.largeur, 2)
  })
})
