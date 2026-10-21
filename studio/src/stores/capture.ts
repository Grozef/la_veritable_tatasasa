import { reactive, ref, shallowRef } from 'vue'
import { appliquerGains, BLANC_PAPIER, type Rvb } from '@/composables/useBalanceBlanc'
import type { Pixels, Point } from '@/composables/useHomographie'
import { creerRendu, REGLAGES_NEUTRES, reglagesNeutres, type Reglages } from '@/services/filtres'

/**
 * Le brouillon d'une prise de vue, d'un ecran a l'autre du flux.
 *
 * NON DESTRUCTIF : `brute` n'est jamais reecrite, `redressee` sert de base fixe, et les
 * deux reglages qui suivent ne sont que des nombres. Revenir a l'ecran precedent ne
 * coute donc jamais une photo.
 */
export interface Metadonnees {
  title: string
  year: number
  width_mm: number
  height_mm: number
  medium: string
  paper_grain: string
  status: string
  notes: string
  publier: boolean
}

const brute = ref<Blob | null>(null)
// Pixels en shallowRef : remplaces d'un bloc, jamais modifies sur place. Un ref profond ferait passer
// chaque lecture de pixel par le proxy Vue et figerait le redressement (27 s mesurees sur un banc Node pour 3000x2250, 0,26 s en shallowRef).
const source = shallowRef<Pixels | null>(null)
const coins = ref<Point[]>([])
const redressee = shallowRef<Pixels | null>(null)
const gains = ref<Rvb>({ r: 1, g: 1, b: 1 })
const reglages = reactive<Reglages>({ ...REGLAGES_NEUTRES })

const metadonnees = reactive<Metadonnees>({
  title: '',
  year: new Date().getFullYear(),
  width_mm: 300,
  height_mm: 400,
  medium: 'aquarelle',
  paper_grain: 'torchon',
  status: 'atelier',
  notes: '',
  publier: true,
})

export function useCapture() {
  function poserPhoto(blob: Blob, pixels: Pixels): void {
    brute.value = blob
    source.value = pixels
    redressee.value = null
    gains.value = { r: 1, g: 1, b: 1 }
    Object.assign(reglages, REGLAGES_NEUTRES)

    // Coins par defaut : les bords de la photo. L'artiste les tire sur ceux de la feuille.
    coins.value = [
      { x: 0, y: 0 },
      { x: pixels.width, y: 0 },
      { x: pixels.width, y: pixels.height },
      { x: 0, y: pixels.height },
    ]
  }

  function oublier(): void {
    brute.value = null
    source.value = null
    redressee.value = null
    coins.value = []
    gains.value = { r: 1, g: 1, b: 1 }
    Object.assign(reglages, REGLAGES_NEUTRES)
    Object.assign(metadonnees, { title: '', notes: '', publier: true })
  }

  /** Copie des pixels redresses, gains appliques. La base reste intacte. */
  function corrigee(): Pixels {
    if (!redressee.value) {
      throw new Error('Aucune image redressee')
    }

    const copie: Pixels = {
      width: redressee.value.width,
      height: redressee.value.height,
      data: new Uint8ClampedArray(redressee.value.data),
    }

    return appliquerGains(copie, gains.value)
  }

  /**
   * Rendu final : redressement, balance des blancs, puis filtres, dans cet ordre.
   *
   * Les filtres passent en DERNIER parce qu'ils travaillent sur une image deja neutre —
   * remonter le contraste avant d'avoir corrige une dominante jaune ne ferait
   * qu'accentuer le jaune.
   */
  async function exporter(qualite = 0.92): Promise<Blob> {
    const pixels = corrigee()
    const toile = document.createElement('canvas')
    toile.width = pixels.width
    toile.height = pixels.height

    const contexte = toile.getContext('2d')

    if (!contexte) {
      throw new Error('Canvas 2D indisponible')
    }

    contexte.putImageData(new ImageData(pixels.data, pixels.width, pixels.height), 0, 0)

    if (!reglagesNeutres(reglages)) {
      const rendue = document.createElement('canvas')
      rendue.width = pixels.width
      rendue.height = pixels.height

      const rendu = creerRendu(rendue)
      rendu.dessiner(toile, reglages)
      rendu.detruire()

      return versBlob(rendue, qualite)
    }

    return versBlob(toile, qualite)
  }

  return {
    brute,
    source,
    coins,
    redressee,
    gains,
    reglages,
    metadonnees,
    blancPapier: BLANC_PAPIER,
    poserPhoto,
    oublier,
    corrigee,
    exporter,
  }
}

function versBlob(toile: HTMLCanvasElement, qualite: number): Promise<Blob> {
  return new Promise((resoudre, rejeter) => {
    toile.toBlob(
      (blob) => (blob ? resoudre(blob) : rejeter(new Error('Encodage JPEG impossible'))),
      'image/jpeg',
      qualite,
    )
  })
}
