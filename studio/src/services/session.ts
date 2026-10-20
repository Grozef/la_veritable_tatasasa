import { Preferences } from '@capacitor/preferences'

/**
 * Le jeton Sanctum de l'atelier.
 *
 * `@capacitor/preferences` n'est PAS un stockage chiffre : sur Android c'est un
 * SharedPreferences en clair, lisible sur un appareil root ou par une sauvegarde. C'est
 * le stockage prevu au plan et il tient pour un compte unique d'artiste ; le jour ou ce
 * jeton ouvrira autre chose qu'un portfolio, il faudra passer au Keystore.
 */
const CLE = 'jeton-atelier'

/**
 * Copie en memoire : chaque requete a besoin du jeton, et Preferences est asynchrone
 * sur toutes les plateformes. Sans ce cache, chaque appel API paierait un aller-retour
 * de stockage.
 */
let cache: string | null = null
let charge = false

export async function jeton(): Promise<string | null> {
  if (!charge) {
    cache = (await Preferences.get({ key: CLE })).value
    charge = true
  }

  return cache
}

export async function poserJeton(valeur: string): Promise<void> {
  cache = valeur
  charge = true
  await Preferences.set({ key: CLE, value: valeur })
}

export async function oublierJeton(): Promise<void> {
  cache = null
  charge = true
  await Preferences.remove({ key: CLE })
}
