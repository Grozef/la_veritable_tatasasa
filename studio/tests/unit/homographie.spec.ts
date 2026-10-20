// @vitest-environment node
// Calcul pur : demarrer jsdom coute 19 secondes pour des tests qui ne touchent pas au DOM.

import { describe, expect, it } from 'vitest'
import {
  homographie,
  projeter,
  redresser,
  tailleRedressee,
  type Pixels,
  type Point,
} from '@/composables/useHomographie'

const carre = (cote: number): Point[] => [
  { x: 0, y: 0 },
  { x: cote, y: 0 },
  { x: cote, y: cote },
  { x: 0, y: cote },
]

/** Un plan uni, sur lequel on pose ensuite des pixels reperables. */
function plan(width: number, height: number, fond = 0): Pixels {
  const data = new Uint8ClampedArray(width * height * 4).fill(fond)

  for (let i = 3; i < data.length; i += 4) {
    data[i] = 255
  }

  return { width, height, data }
}

function poser(image: Pixels, x: number, y: number, rouge: number): void {
  image.data[(y * image.width + x) * 4] = rouge
}

function rouge(image: Pixels, x: number, y: number): number {
  return image.data[(y * image.width + x) * 4]
}

describe('homographie', () => {
  it('envoie chacun des quatre points sur sa cible', () => {
    // Trapeze quelconque : c'est le cas reel, une feuille photographiee de biais.
    const depuis: Point[] = [
      { x: 12, y: 30 },
      { x: 180, y: 8 },
      { x: 196, y: 150 },
      { x: 4, y: 128 },
    ]
    const vers = carre(100)

    const h = homographie(depuis, vers)

    depuis.forEach((point, i) => {
      const projete = projeter(h, point)

      expect(projete.x).toBeCloseTo(vers[i].x, 6)
      expect(projete.y).toBeCloseTo(vers[i].y, 6)
    })
  })

  it('rend l identite quand la source est deja la cible', () => {
    const h = homographie(carre(50), carre(50))
    const milieu = projeter(h, { x: 17, y: 41 })

    expect(milieu.x).toBeCloseTo(17, 6)
    expect(milieu.y).toBeCloseTo(41, 6)
  })

  it('refuse quatre points alignes plutot que de rendre des NaN', () => {
    const alignes: Point[] = [
      { x: 0, y: 0 },
      { x: 10, y: 0 },
      { x: 20, y: 0 },
      { x: 30, y: 0 },
    ]

    expect(() => homographie(alignes, carre(10))).toThrow(/quadrilatere/)
  })

  it('refuse un nombre de points different de quatre', () => {
    expect(() => homographie(carre(10).slice(0, 3), carre(10))).toThrow(/4 points/)
  })
})

describe('tailleRedressee', () => {
  it('retient le plus grand des deux bords opposes', () => {
    // Bord haut de 200, bord bas de 100 : le haut est plus proche de l objectif.
    const coins: Point[] = [
      { x: 0, y: 0 },
      { x: 200, y: 0 },
      { x: 150, y: 100 },
      { x: 50, y: 100 },
    ]

    expect(tailleRedressee(coins).largeur).toBe(200)
  })

  it('borne la sortie sans deformer le rapport', () => {
    const { largeur, hauteur } = tailleRedressee(
      [
        { x: 0, y: 0 },
        { x: 4000, y: 0 },
        { x: 4000, y: 2000 },
        { x: 0, y: 2000 },
      ],
      2400,
    )

    expect(largeur).toBe(2400)
    expect(hauteur).toBe(1200)
  })
})

describe('redresser', () => {
  it('rend la source telle quelle quand les coins sont ceux de l image', () => {
    const source = plan(8, 8)
    poser(source, 1, 1, 200)

    const sortie = redresser(source, carre(8))

    expect(sortie.width).toBe(8)
    expect(sortie.height).toBe(8)
    expect(rouge(sortie, 1, 1)).toBeGreaterThan(150)
    expect(rouge(sortie, 5, 5)).toBe(0)
  })

  /**
   * Le test qui compte : un trapeze doit ressortir rectangle. On peint une bande
   * verticale dans la SOURCE, penchee comme le serait le bord d une feuille vue de
   * biais, et on verifie qu elle ressort droite.
   */
  it('redresse un trapeze en rectangle', () => {
    const source = plan(64, 64)

    // Quadrilatere de la « feuille » : plus etroit en haut qu en bas.
    const coins: Point[] = [
      { x: 16, y: 8 },
      { x: 48, y: 8 },
      { x: 56, y: 56 },
      { x: 8, y: 56 },
    ]

    // Pave de 4x4 pose sur le coin haut-gauche de la feuille, et lui seul. Un marqueur
    // d un seul pixel tomberait a cote de l echantillon bilineaire du coin : le test
    // echouerait sans que le redressement soit faux.
    for (let y = 8; y < 12; y++) {
      for (let x = 16; x < 20; x++) {
        poser(source, x, y, 255)
      }
    }

    const sortie = redresser(source, coins)

    // Le coin marque doit se retrouver en haut a gauche de la sortie, pas ailleurs.
    expect(rouge(sortie, 0, 0)).toBeGreaterThan(50)
    expect(rouge(sortie, sortie.width - 1, 0)).toBe(0)
    expect(rouge(sortie, 0, sortie.height - 1)).toBe(0)
  })

  it('prolonge le bord plutot que de border l image de noir', () => {
    const source = plan(8, 8, 120)

    // Coins deliberement debordants : l echantillonnage sort du cadre.
    const sortie = redresser(source, [
      { x: -4, y: -4 },
      { x: 12, y: -4 },
      { x: 12, y: 12 },
      { x: -4, y: 12 },
    ])

    expect(rouge(sortie, 0, 0)).toBe(120)
  })
})
