/**
 * Composition du visuel de partage.
 *
 * L'aquarelle ne part JAMAIS nue sur un reseau : elle part posee sur du papier, avec son
 * titre et son annee. Une image brute deposee sur Instagram est recadree par la
 * plateforme, souvent en plein milieu du lavis ; un visuel compose au bon format arrive
 * intact.
 *
 * La disposition est un calcul PUR, separe du dessin : c'est la seule partie qui peut
 * mentir sans qu'on le voie (un titre sous le bord de la toile, une image ecrasee), et
 * c'est donc la seule qui se teste.
 */

export type FormatPartage = 'carre' | 'portrait'

export interface Boite {
  x: number
  y: number
  largeur: number
  hauteur: number
}

export interface Disposition {
  toile: { largeur: number; hauteur: number }
  image: Boite
  filet: { x: number, largeur: number; y: number }
  titre: { x: number; y: number; taille: number }
  annee: { x: number; y: number; taille: number }
}

/** 1080 px de large : la taille que les reseaux servent sans reechantillonner. */
const COTE = 1080

/** Proportions retenues : le carre et le 4:5, le plus haut qu'Instagram accepte sans rogner. */
const PROPORTIONS: Record<FormatPartage, number> = {
  carre: 1,
  portrait: 5 / 4,
}

export function disposition(
  format: FormatPartage,
  imageLargeur: number,
  imageHauteur: number,
  cote = COTE,
): Disposition {
  const largeur = cote
  const hauteur = Math.round(cote * PROPORTIONS[format])

  const marge = Math.round(cote * 0.075)
  const tailleTitre = Math.round(cote * 0.042)
  const tailleAnnee = Math.round(cote * 0.03)

  // Bandeau du bas : filet, titre, annee. Reserve AVANT de placer l'image, sinon le
  // texte finirait par mordre dessus des que l'aquarelle est haute.
  const bandeau = marge + tailleTitre + tailleAnnee + Math.round(cote * 0.045)

  const cadre = {
    x: marge,
    y: marge,
    largeur: largeur - marge * 2,
    hauteur: hauteur - marge - bandeau,
  }

  // `contain` et jamais `cover` : rogner une aquarelle pour remplir un cadre reviendrait
  // a laisser la plateforme decider du cadrage, ce que ce visuel existe pour eviter.
  const reduction = Math.min(cadre.largeur / imageLargeur, cadre.hauteur / imageHauteur)
  const image = {
    largeur: Math.round(imageLargeur * reduction),
    hauteur: Math.round(imageHauteur * reduction),
    x: 0,
    y: 0,
  }

  image.x = Math.round(cadre.x + (cadre.largeur - image.largeur) / 2)
  image.y = Math.round(cadre.y + (cadre.hauteur - image.hauteur) / 2)

  const filetY = hauteur - bandeau + Math.round(cote * 0.028)

  return {
    toile: { largeur, hauteur },
    image,
    filet: { x: marge, largeur: largeur - marge * 2, y: filetY },
    titre: { x: marge, y: filetY + Math.round(cote * 0.052), taille: tailleTitre },
    annee: { x: largeur - marge, y: filetY + Math.round(cote * 0.052), taille: tailleAnnee },
  }
}

export interface VisuelDemande {
  image: CanvasImageSource
  imageLargeur: number
  imageHauteur: number
  titre: string
  annee: number
  format: FormatPartage
}

/** Memes valeurs que le portfolio public : le partage doit ressembler au site. */
const PAPIER = '#faf7f0'
const ENCRE = '#2b2724'
const ENCRE_PALE = '#6f665c'
const FILET = '#d8cfbe'

export function peindreVisuel(demande: VisuelDemande, toile: HTMLCanvasElement): Disposition {
  const plan = disposition(demande.format, demande.imageLargeur, demande.imageHauteur)

  toile.width = plan.toile.largeur
  toile.height = plan.toile.hauteur

  const contexte = toile.getContext('2d')

  if (!contexte) {
    throw new Error('Canvas 2D indisponible')
  }

  contexte.fillStyle = PAPIER
  contexte.fillRect(0, 0, plan.toile.largeur, plan.toile.hauteur)

  contexte.drawImage(demande.image, plan.image.x, plan.image.y, plan.image.largeur, plan.image.hauteur)

  contexte.fillStyle = FILET
  contexte.fillRect(plan.filet.x, plan.filet.y, plan.filet.largeur, 2)

  contexte.fillStyle = ENCRE
  contexte.font = `${plan.titre.taille}px Georgia, "Times New Roman", serif`
  contexte.textAlign = 'left'
  contexte.textBaseline = 'alphabetic'
  contexte.fillText(demande.titre, plan.titre.x, plan.titre.y, plan.filet.largeur * 0.75)

  contexte.fillStyle = ENCRE_PALE
  contexte.font = `${plan.annee.taille}px Georgia, "Times New Roman", serif`
  contexte.textAlign = 'right'
  contexte.fillText(String(demande.annee), plan.annee.x, plan.annee.y)

  return plan
}
