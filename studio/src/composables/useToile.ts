import type { Pixels } from './useHomographie'

/**
 * Le pont entre les calculs (qui ne connaissent que des tableaux d'octets) et le
 * navigateur (qui ne sait afficher que des `canvas`).
 */

/** Cote maximal d'une photo decodee : au-dela, le redressement pixel par pixel traine. */
const COTE_MAXIMAL = 3000

/**
 * Decode une photo en pixels, en la reduisant si elle depasse `COTE_MAXIMAL`.
 *
 * `createImageBitmap` plutot qu'un `<img>` : le decodage sort du fil principal, ce qui
 * evite de figer l'interface plusieurs secondes sur une photo de telephone.
 */
export async function decoder(blob: Blob): Promise<Pixels> {
  const image = await createImageBitmap(blob)

  const reduction = Math.min(1, COTE_MAXIMAL / Math.max(image.width, image.height))
  const width = Math.round(image.width * reduction)
  const height = Math.round(image.height * reduction)

  const toile = document.createElement('canvas')
  toile.width = width
  toile.height = height

  const contexte = contexte2d(toile)
  contexte.drawImage(image, 0, 0, width, height)
  image.close()

  const donnees = contexte.getImageData(0, 0, width, height)

  return { width, height, data: donnees.data }
}

export function versToile(pixels: Pixels, toile = document.createElement('canvas')): HTMLCanvasElement {
  toile.width = pixels.width
  toile.height = pixels.height

  contexte2d(toile).putImageData(new ImageData(pixels.data, pixels.width, pixels.height), 0, 0)

  return toile
}

/**
 * Copie reduite, pour les apercus qui se recalculent a chaque geste. Recalculer une
 * balance des blancs sur 9 megapixels a chaque mouvement de doigt saccaderait.
 */
export function reduire(pixels: Pixels, cote: number): Pixels {
  const reduction = Math.min(1, cote / Math.max(pixels.width, pixels.height))

  if (reduction === 1) {
    return { ...pixels, data: new Uint8ClampedArray(pixels.data) }
  }

  const width = Math.max(1, Math.round(pixels.width * reduction))
  const height = Math.max(1, Math.round(pixels.height * reduction))

  const petite = document.createElement('canvas')
  petite.width = width
  petite.height = height

  const contexte = contexte2d(petite)
  contexte.drawImage(versToile(pixels), 0, 0, width, height)

  const donnees = contexte.getImageData(0, 0, width, height)

  return { width, height, data: donnees.data }
}

function contexte2d(toile: HTMLCanvasElement): CanvasRenderingContext2D {
  const contexte = toile.getContext('2d', { willReadFrequently: true })

  if (!contexte) {
    throw new Error('Canvas 2D indisponible')
  }

  return contexte
}
