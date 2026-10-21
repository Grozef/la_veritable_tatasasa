// @vitest-environment node

import { describe, expect, it } from 'vitest'
import { echelleAjustee } from '@/composables/useToile'

describe('echelleAjustee', () => {
  it('portrait : la largeur borne une photo paysage', () => {
    expect(echelleAjustee(1200, 900, { largeur: 316, hauteur: 600 })).toBeCloseTo(316 / 1200)
  })

  it('paysage : la hauteur borne, la photo tient entiere', () => {
    const echelle = echelleAjustee(1200, 900, { largeur: 560, hauteur: 256 })

    expect(echelle).toBeCloseTo(256 / 900)
    expect(1200 * echelle).toBeLessThanOrEqual(560)
  })

  it('agrandit une petite photo comme le faisait la largeur a 100 %', () => {
    expect(echelleAjustee(100, 100, { largeur: 300, hauteur: 400 })).toBe(3)
  })

  it('boite pas encore mesuree : echelle neutre', () => {
    expect(echelleAjustee(1200, 900, { largeur: 0, hauteur: 0 })).toBe(1)
  })
})
