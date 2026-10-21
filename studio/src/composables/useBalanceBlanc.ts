/**
 * Balance des blancs a la pipette.
 *
 * L'artiste designe une zone de RESERVE — un endroit ou le papier est reste nu. On sait
 * donc de quelle couleur ce pixel devrait etre ; le gain qui l'y ramene corrige du meme
 * coup la dominante de toute la photo. C'est ce reglage, bien plus que les filtres, qui
 * fait qu'une aquarelle numerisee rend juste : une lampe d'atelier jaunit un lavis bleu
 * jusqu'a le faire passer pour un gris.
 */

import type { Pixels, Point } from './useHomographie'

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

/**
 * Seuils d'une pastille candidate a la reserve.
 *
 * Saturation `(max - min) / max` : un papier nu jauni par une lampe d'atelier reste sous
 * 0,25 ; un lavis jaune ou rouge franc depasse 0,6. Le plus clair seul ne suffit pas : un
 * jaune sature (250, 240, 50) est plus lumineux qu'une reserve grisee (220, 205, 170), et
 * le prendre pour du blanc pousserait le bleu au gain maximal.
 *
 * Elle se mesure pixel par pixel puis se moyenne : la moyenne d'une pastille a cheval sur
 * un jaune et un bleu francs donne un gris clair, qui passerait pour du papier.
 *
 * Clarte (luminance Rec. 709, 0-255) : en dessous, ce n'est pas du papier mais une ombre
 * ou un lavis, et le gain eclaircirait toute la photo.
 */
const SATURATION_MAX = 0.35
const CLARTE_MIN = 140

/**
 * Zone de reserve trouvee sans viser : parmi les pastilles d'une grille (moyennes, pas des
 * pixels) assez claires ET peu saturees, la plus claire. C'est l'alternative a la pipette
 * pour qui ne peut pas toucher un point precis de l'image.
 *
 * `null` quand aucune pastille ne qualifie : mieux vaut ne rien corriger que ramener un
 * lavis au blanc.
 *
 * Le bord (5 %) est ecarte : apres redressement il peut garder un liseré de table ou d'ombre.
 */
export function chercherPapier(image: Pixels, cases = 24): Point | null {
  const marge = 0.05
  let meilleur: Point | null = null
  let clarteMax = -1

  for (let ligne = 0; ligne <= cases; ligne++) {
    for (let colonne = 0; colonne <= cases; colonne++) {
      const x = Math.round(image.width * (marge + ((1 - 2 * marge) * colonne) / cases))
      const y = Math.round(image.height * (marge + ((1 - 2 * marge) * ligne) / cases))
      const pastille = mesurerPastille(image, x, y)
      const clarte = 0.2126 * pastille.r + 0.7152 * pastille.g + 0.0722 * pastille.b

      if (clarte >= CLARTE_MIN && clarte > clarteMax && saturationMoyenne(image, x, y) <= SATURATION_MAX) {
        clarteMax = clarte
        meilleur = { x, y }
      }
    }
  }

  return meilleur
}

/** Moyenne des saturations `(max - min) / max` des pixels du carre de `rayon` autour du point. */
function saturationMoyenne(image: Pixels, x: number, y: number, rayon = 4): number {
  let somme = 0
  let n = 0

  for (let py = Math.max(0, y - rayon); py <= Math.min(image.height - 1, y + rayon); py++) {
    for (let px = Math.max(0, x - rayon); px <= Math.min(image.width - 1, x + rayon); px++) {
      const position = (py * image.width + px) * 4
      const r = image.data[position]
      const g = image.data[position + 1]
      const b = image.data[position + 2]
      const haut = Math.max(r, g, b)

      somme += haut > 0 ? (haut - Math.min(r, g, b)) / haut : 0
      n++
    }
  }

  return n > 0 ? somme / n : 1
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
