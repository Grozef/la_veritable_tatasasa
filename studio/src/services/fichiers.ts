import { Directory, Filesystem } from '@capacitor/filesystem'

/**
 * Stockage des images en attente d'envoi.
 *
 * PAS dans `Preferences` : une photo d'aquarelle pese plusieurs megaoctets et
 * SharedPreferences comme `localStorage` sont faits pour des valeurs courtes — au mieux
 * c'est lent, au pire l'ecriture est refusee. Les octets vont dans le systeme de
 * fichiers, la file ne garde que des noms.
 */
const DOSSIER = 'envois'

/** Conversion sans `FileReader` : la promesse est plus simple a enchainer. */
export async function blobVersBase64(blob: Blob): Promise<string> {
  const octets = new Uint8Array(await blob.arrayBuffer())
  let binaire = ''

  // Par tranches : `String.fromCharCode(...tableau)` sur plusieurs megaoctets depasse la
  // taille maximale de la pile d'appels.
  for (let debut = 0; debut < octets.length; debut += 8192) {
    binaire += String.fromCharCode(...octets.subarray(debut, debut + 8192))
  }

  return btoa(binaire)
}

export function base64VersBlob(base64: string, mime: string): Blob {
  const binaire = atob(base64)
  const octets = new Uint8Array(binaire.length)

  for (let i = 0; i < binaire.length; i++) {
    octets[i] = binaire.charCodeAt(i)
  }

  return new Blob([octets], { type: mime })
}

export async function ecrire(nom: string, blob: Blob): Promise<void> {
  await Filesystem.writeFile({
    path: `${DOSSIER}/${nom}`,
    data: await blobVersBase64(blob),
    directory: Directory.Data,
    recursive: true,
  })
}

export async function lire(nom: string, mime: string): Promise<Blob> {
  const { data } = await Filesystem.readFile({
    path: `${DOSSIER}/${nom}`,
    directory: Directory.Data,
  })

  // Sur le web le greffon rend un Blob, sur mobile une chaine base64.
  return typeof data === 'string' ? base64VersBlob(data, mime) : data
}

export async function supprimer(nom: string): Promise<void> {
  // Un fichier deja absent ne doit pas bloquer le vidage de la file.
  await Filesystem.deleteFile({ path: `${DOSSIER}/${nom}`, directory: Directory.Data }).catch(
    () => undefined,
  )
}
