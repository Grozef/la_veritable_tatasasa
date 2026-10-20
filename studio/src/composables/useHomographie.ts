/**
 * Redressement de perspective.
 *
 * Une aquarelle photographiee au telephone n'est jamais de face : la feuille sort en
 * trapeze. Aucune transformation affine (`transform: matrix`, `canvas.setTransform`) ne
 * corrige un trapeze — il faut une homographie, donc une division par la coordonnee
 * homogene, donc un calcul par pixel.
 */

export interface Point {
  x: number
  y: number
}

/**
 * Un plan de pixels RGBA, structurellement identique a `ImageData` : un `ImageData` du
 * navigateur en est un, sans conversion.
 *
 * Le type est declare ici plutot qu'emprunte au DOM pour que ce calcul — le coeur du
 * redressement — soit testable hors navigateur. `ImageData` n'existe pas dans le jsdom
 * du projet, faute du paquet natif `canvas`, et l'installer pour un constructeur serait
 * payer une compilation native a chaque `npm ci`.
 */
export interface Pixels {
  width: number
  height: number
  /**
   * `<ArrayBuffer>` explicite : le defaut `ArrayBufferLike` inclut `SharedArrayBuffer`,
   * que le constructeur `ImageData` refuse. Sans ce parametre, tout appel a
   * `new ImageData(pixels.data, ...)` ne compile pas.
   */
  data: Uint8ClampedArray<ArrayBuffer>
}

/** Matrice 3x3 en ligne d'abord, `h[8]` fixe a 1. */
export type Homographie = number[]

/**
 * Resout l'homographie qui envoie les 4 points `depuis` sur les 4 points `vers`.
 *
 * Huit inconnues pour quatre correspondances : chaque point donne deux equations.
 * Elimination de Gauss avec pivot partiel — sans le pivot, une paire de coins alignes
 * met un zero sur la diagonale et la resolution part en NaN.
 */
export function homographie(depuis: Point[], vers: Point[]): Homographie {
  if (depuis.length !== 4 || vers.length !== 4) {
    throw new Error('Une homographie demande exactement 4 points de chaque cote')
  }

  const a: number[][] = []

  for (let i = 0; i < 4; i++) {
    const { x, y } = depuis[i]
    const { x: u, y: v } = vers[i]

    a.push([x, y, 1, 0, 0, 0, -x * u, -y * u, u])
    a.push([0, 0, 0, x, y, 1, -x * v, -y * v, v])
  }

  for (let colonne = 0; colonne < 8; colonne++) {
    let pivot = colonne

    for (let ligne = colonne + 1; ligne < 8; ligne++) {
      if (Math.abs(a[ligne][colonne]) > Math.abs(a[pivot][colonne])) {
        pivot = ligne
      }
    }

    if (Math.abs(a[pivot][colonne]) < 1e-10) {
      throw new Error('Ces quatre points ne definissent pas un quadrilatere')
    }

    ;[a[colonne], a[pivot]] = [a[pivot], a[colonne]]

    for (let ligne = 0; ligne < 8; ligne++) {
      if (ligne === colonne) continue

      const facteur = a[ligne][colonne] / a[colonne][colonne]

      for (let k = colonne; k < 9; k++) {
        a[ligne][k] -= facteur * a[colonne][k]
      }
    }
  }

  const h = a.map((ligne, i) => ligne[8] / ligne[i])
  h.push(1)

  return h
}

/** Applique l'homographie a un point. */
export function projeter(h: Homographie, point: Point): Point {
  const poids = h[6] * point.x + h[7] * point.y + h[8]

  return {
    x: (h[0] * point.x + h[1] * point.y + h[2]) / poids,
    y: (h[3] * point.x + h[4] * point.y + h[5]) / poids,
  }
}

/**
 * Taille de la feuille redressee, deduite des quatre coins.
 *
 * On prend le PLUS GRAND des deux bords opposes : c'est le bord le plus proche de
 * l'objectif, donc celui qui porte le plus de detail. Retenir le plus petit reviendrait
 * a jeter de la resolution du cote net de la photo.
 *
 * `maximum` borne la sortie — le redressement se fait pixel par pixel en JavaScript, et
 * une photo de telephone en pleine resolution y passerait plusieurs secondes.
 */
export function tailleRedressee(coins: Point[], maximum = 2400): { largeur: number; hauteur: number } {
  const distance = (a: Point, b: Point) => Math.hypot(a.x - b.x, a.y - b.y)
  const [hg, hd, bd, bg] = coins

  let largeur = Math.max(distance(hg, hd), distance(bg, bd))
  let hauteur = Math.max(distance(hg, bg), distance(hd, bd))

  const debordement = Math.max(largeur, hauteur) / maximum

  if (debordement > 1) {
    largeur /= debordement
    hauteur /= debordement
  }

  return { largeur: Math.max(1, Math.round(largeur)), hauteur: Math.max(1, Math.round(hauteur)) }
}

/**
 * Redresse `source` vers un rectangle, en echantillonnant depuis les quatre coins
 * donnes dans l'ordre haut-gauche, haut-droit, bas-droit, bas-gauche.
 *
 * Le sens du calcul est INVERSE du sens intuitif : on resout l'homographie qui va du
 * rectangle de SORTIE vers le quadrilatere d'ENTREE, pour que chaque pixel de sortie
 * sache ou aller se chercher. Parcourir l'entree laisserait des trous dans la sortie.
 */
export function redresser(source: Pixels, coins: Point[], maximum = 2400): Pixels {
  const { largeur, hauteur } = tailleRedressee(coins, maximum)

  const inverse = homographie(
    [
      { x: 0, y: 0 },
      { x: largeur, y: 0 },
      { x: largeur, y: hauteur },
      { x: 0, y: hauteur },
    ],
    coins,
  )

  const sortie: Pixels = {
    width: largeur,
    height: hauteur,
    data: new Uint8ClampedArray(largeur * hauteur * 4),
  }

  for (let y = 0; y < hauteur; y++) {
    for (let x = 0; x < largeur; x++) {
      const { x: sx, y: sy } = projeter(inverse, { x: x + 0.5, y: y + 0.5 })

      echantillonner(source, sx - 0.5, sy - 0.5, sortie.data, (y * largeur + x) * 4)
    }
  }

  return sortie
}

/**
 * Echantillonnage bilineaire. Le plus proche voisin cranterait les bords du papier et
 * moucheterait le grain, qui est justement le sujet de la photo.
 */
function echantillonner(source: Pixels, x: number, y: number, cible: Uint8ClampedArray, position: number): void {
  const x0 = Math.floor(x)
  const y0 = Math.floor(y)
  const fx = x - x0
  const fy = y - y0

  for (let canal = 0; canal < 4; canal++) {
    const hg = pixel(source, x0, y0, canal)
    const hd = pixel(source, x0 + 1, y0, canal)
    const bg = pixel(source, x0, y0 + 1, canal)
    const bd = pixel(source, x0 + 1, y0 + 1, canal)

    cible[position + canal] =
      hg * (1 - fx) * (1 - fy) + hd * fx * (1 - fy) + bg * (1 - fx) * fy + bd * fx * fy
  }
}

/** Hors cadre, on prolonge le bord : un noir franc dessinerait un lisere au bord de la feuille. */
function pixel(source: Pixels, x: number, y: number, canal: number): number {
  const cx = Math.min(source.width - 1, Math.max(0, x))
  const cy = Math.min(source.height - 1, Math.max(0, y))

  return source.data[(cy * source.width + cx) * 4 + canal]
}
