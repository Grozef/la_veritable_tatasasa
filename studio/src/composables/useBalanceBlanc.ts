/**
 * Balance des blancs a la pipette.
 *
 * L'artiste designe une zone de RESERVE — un endroit ou le papier est reste nu. On sait
 * donc de quelle couleur ce pixel devrait etre ; le gain qui l'y ramene corrige du meme
 * coup la dominante de toute la photo. C'est ce reglage, bien plus que les filtres, qui
 * fait qu'une aquarelle numerisee rend juste : une lampe d'atelier jaunit un lavis bleu
 * jusqu'a le faire passer pour un gris.
 */

import type { Pixels } from './useHomographie'

export interface Rvb {
  r: number
  g: number
  b: number
}

/** Le blanc du papier du portfolio (`--papier` de `web`), jamais `#fff` pur. */
export const BLANC_PAPIER: Rvb = { r: 250, g: 247, b: 240 }

/**
 * Bornes du gain. Sans elles, une pipette posee par erreur dans une ombre profonde
 * multiplierait un canal par 40 et rendrait l'image fluorescente ; et un canal mesure a
 * 0 ferait une division par zero.
 */
const GAIN_MIN = 0.25
const GAIN_MAX = 4

export function gainsBalanceBlanc(mesure: Rvb, cible: Rvb = BLANC_PAPIER): Rvb {
  return {
    r: gain(cible.r, mesure.r),
    g: gain(cible.g, mesure.g),
    b: gain(cible.b, mesure.b),
  }
}

function gain(cible: number, mesure: number): number {
  if (mesure <= 0) {
    return GAIN_MAX
  }

  return Math.min(GAIN_MAX, Math.max(GAIN_MIN, cible / mesure))
}

/**
 * Moyenne d'un carre de `rayon` pixels autour du point designe.
 *
 * Un pixel isole ne suffit pas : le grain du papier fait varier une reserve de plusieurs
 * unites d'un pixel a l'autre, et un doigt sur un ecran de telephone ne vise pas au
 * pixel pres.
 */
export function mesurerPastille(image: Pixels, x: number, y: number, rayon = 4): Rvb {
  let r = 0
  let g = 0
  let b = 0
  let n = 0

  for (let py = y - rayon; py <= y + rayon; py++) {
    for (let px = x - rayon; px <= x + rayon; px++) {
      if (px < 0 || py < 0 || px >= image.width || py >= image.height) {
        continue
      }

      const position = (py * image.width + px) * 4

      r += image.data[position]
      g += image.data[position + 1]
      b += image.data[position + 2]
      n++
    }
  }

  if (n === 0) {
    throw new Error('Le point de mesure tombe hors de l image')
  }

  return { r: r / n, g: g / n, b: b / n }
}

/** Applique les gains en place. Les canaux sont bornes par `Uint8ClampedArray`. */
export function appliquerGains(image: Pixels, gains: Rvb): Pixels {
  for (let position = 0; position < image.data.length; position += 4) {
    image.data[position] *= gains.r
    image.data[position + 1] *= gains.g
    image.data[position + 2] *= gains.b
  }

  return image
}
