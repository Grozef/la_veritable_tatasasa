import { Directory, Filesystem } from '@capacitor/filesystem'
import { Share } from '@capacitor/share'
import { blobVersBase64 } from './fichiers'

/**
 * Feuille de partage native.
 *
 * v1 volontairement sans API Meta : `Share.share` ouvre la feuille du systeme, qui ne
 * demande aucune validation, aucun compte Professionnel et aucun jeton a renouveler.
 * L'artiste choisit elle-meme la destination — c'est aussi pourquoi le reseau est
 * DECLARE ensuite et non devine.
 */
export type ResultatPartage = 'partage' | 'annule' | 'indisponible'

/**
 * Message de rejet de `@capacitor/share` 8 quand l'artiste ferme la feuille sans rien
 * choisir (SharePlugin.java `RESULT_CANCELED`, SharePlugin.swift). Sur le web,
 * `navigator.share` rejette une `AbortError`. Ce n'est pas une panne : on ne dit rien.
 */
const ANNULATION = 'Share canceled'

export function estAnnulation(cause: unknown): boolean {
  return (
    cause instanceof Error && (cause.message === ANNULATION || cause.name === 'AbortError')
  )
}

export async function partagerVisuel(
  visuel: Blob,
  titre: string,
  texte: string,
): Promise<ResultatPartage> {
  try {
    // La feuille de partage veut un chemin de FICHIER, pas un blob : elle passe l'image a
    // une autre application, qui doit pouvoir la lire apres coup. Le dossier de cache
    // suffit, le visuel est reproductible a volonte depuis l'oeuvre.
    const nom = `partage-${Date.now()}.jpg`

    await Filesystem.writeFile({
      path: nom,
      data: await blobVersBase64(visuel),
      directory: Directory.Cache,
    })

    const { uri } = await Filesystem.getUri({ path: nom, directory: Directory.Cache })

    await Share.share({ title: titre, text: texte, files: [uri] })

    return 'partage'
  } catch (cause) {
    if (estAnnulation(cause)) {
      return 'annule'
    }

    /*
     * La plateforme ne sait pas partager de fichier (c'est le cas de tout navigateur de
     * bureau). Le visuel reste affiche a l'ecran et enregistrable a la main — on ne perd
     * rien, on ne pretend rien.
     */
    return 'indisponible'
  }
}
