// @vitest-environment node

import { describe, expect, it } from 'vitest'
import {
  appliquerGains,
  BLANC_PAPIER,
  gainsBalanceBlanc,
  mesurerPastille,
} from '@/composables/useBalanceBlanc'
import type { Pixels } from '@/composables/useHomographie'

function uni(width: number, height: number, r: number, g: number, b: number): Pixels {
  const data = new Uint8ClampedArray(width * height * 4)

  for (let i = 0; i < data.length; i += 4) {
    data[i] = r
    data[i + 1] = g
    data[i + 2] = b
    data[i + 3] = 255
  }

  return { width, height, data }
}

describe('gainsBalanceBlanc', () => {
  it('ramene la mesure exactement sur le blanc papier', () => {
    // Reserve photographiee sous une lampe chaude : le bleu manque.
    const mesure = { r: 232, g: 214, b: 180 }
    const gains = gainsBalanceBlanc(mesure)

    expect(mesure.r * gains.r).toBeCloseTo(BLANC_PAPIER.r, 6)
    expect(mesure.g * gains.g).toBeCloseTo(BLANC_PAPIER.g, 6)
    expect(mesure.b * gains.b).toBeCloseTo(BLANC_PAPIER.b, 6)
  })

  it('corrige la dominante et ne se contente pas d eclaircir', () => {
    const gains = gainsBalanceBlanc({ r: 232, g: 214, b: 180 })

    // Le canal le plus faible doit etre le plus rehausse, sinon la dominante reste.
    expect(gains.b).toBeGreaterThan(gains.g)
    expect(gains.g).toBeGreaterThan(gains.r)
  })

  it('ne touche a rien quand la reserve est deja au blanc papier', () => {
    const gains = gainsBalanceBlanc({ ...BLANC_PAPIER })

    expect(gains.r).toBeCloseTo(1, 6)
    expect(gains.g).toBeCloseTo(1, 6)
    expect(gains.b).toBeCloseTo(1, 6)
  })

  /** Une pipette posee par erreur dans une ombre ne doit pas rendre l image fluorescente. */
  it('borne le gain sur une mesure sombre ou nulle', () => {
    expect(gainsBalanceBlanc({ r: 2, g: 2, b: 2 }).r).toBe(4)
    expect(gainsBalanceBlanc({ r: 0, g: 0, b: 0 }).g).toBe(4)
    expect(Number.isFinite(gainsBalanceBlanc({ r: 0, g: 0, b: 0 }).b)).toBe(true)
  })

  it('borne aussi le gain vers le bas sur une mesure brulee', () => {
    expect(gainsBalanceBlanc({ r: 255, g: 255, b: 255 }, { r: 20, g: 20, b: 20 }).r).toBe(0.25)
  })
})

describe('mesurerPastille', () => {
  it('moyenne le voisinage plutot que de lire un pixel isole', () => {
    const image = uni(20, 20, 100, 100, 100)

    // Un pixel aberrant au centre : le grain du papier fait exactement ca.
    image.data[(10 * 20 + 10) * 4] = 255

    const mesure = mesurerPastille(image, 10, 10, 4)

    expect(mesure.r).toBeGreaterThan(100)
    expect(mesure.r).toBeLessThan(103)
    expect(mesure.g).toBe(100)
  })

  it('ignore le hors-cadre quand la pipette est posee au bord', () => {
    const image = uni(20, 20, 80, 90, 100)
    const mesure = mesurerPastille(image, 0, 0, 4)

    expect(mesure.r).toBe(80)
    expect(mesure.b).toBe(100)
  })

  it('refuse un point entierement hors de l image', () => {
    expect(() => mesurerPastille(uni(4, 4, 0, 0, 0), 99, 99, 1)).toThrow(/hors de l image/)
  })
})

describe('appliquerGains', () => {
  it('amene une reserve mesuree sur le blanc papier, alpha intact', () => {
    const image = uni(4, 4, 232, 214, 180)

    appliquerGains(image, gainsBalanceBlanc({ r: 232, g: 214, b: 180 }))

    expect(image.data[0]).toBe(BLANC_PAPIER.r)
    expect(image.data[1]).toBe(BLANC_PAPIER.g)
    expect(image.data[2]).toBe(BLANC_PAPIER.b)
    expect(image.data[3]).toBe(255)
  })

  it('ecrete au lieu de reboucler quand le gain fait deborder un canal', () => {
    const image = uni(2, 2, 200, 200, 200)

    appliquerGains(image, { r: 3, g: 1, b: 1 })

    // 600 tronque a 8 bits ferait 88 : le lavis clair virerait au sombre.
    expect(image.data[0]).toBe(255)
  })
})
