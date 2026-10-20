// @vitest-environment node

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ErreurApi } from '@/services/api'

/** Preferences en memoire : le comportement qui compte est la PERSISTANCE des entrees. */
const stockage = new Map<string, string>()

vi.mock('@capacitor/preferences', () => ({
  Preferences: {
    get: async ({ key }: { key: string }) => ({ value: stockage.get(key) ?? null }),
    set: async ({ key, value }: { key: string; value: string }) => {
      stockage.set(key, value)
    },
    remove: async ({ key }: { key: string }) => {
      stockage.delete(key)
    },
  },
}))

const disque = new Map<string, Blob>()

vi.mock('@/services/fichiers', () => ({
  ecrire: async (nom: string, blob: Blob) => {
    disque.set(nom, blob)
  },
  lire: async (nom: string) => {
    const blob = disque.get(nom)
    if (!blob) throw new Error(`fichier absent : ${nom}`)
    return blob
  },
  supprimer: async (nom: string) => {
    disque.delete(nom)
  },
}))

const creerOeuvre = vi.fn()
const televerserMedia = vi.fn()

vi.mock('@/services/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/services/api')>()),
  api: {
    creerOeuvre: (...args: unknown[]) => creerOeuvre(...args),
    televerserMedia: (...args: unknown[]) => televerserMedia(...args),
  },
}))

const { enfiler, file, reprendre, retirer, vider } = await import('@/services/queue')

const brouillon = { title: 'La chute lente', year: 2024, width_mm: 380, height_mm: 280 }

const images = () => [
  { blob: new Blob(['corrigee'], { type: 'image/jpeg' }), role: 'principal' as const, position: 0 },
  { blob: new Blob(['brute'], { type: 'image/jpeg' }), role: 'brute' as const, position: 1 },
]

beforeEach(() => {
  stockage.clear()
  disque.clear()
  creerOeuvre.mockReset()
  televerserMedia.mockReset()
})

describe('file d envoi', () => {
  it('garde l envoi et ses images tant que rien n est parti', async () => {
    await enfiler(brouillon, images())

    const attente = await file()

    expect(attente).toHaveLength(1)
    expect(attente[0].ulid).toBeNull()
    expect(attente[0].fichiers.map((f) => f.role)).toEqual(['principal', 'brute'])
    expect(disque.size).toBe(2)
  })

  it('cree l oeuvre puis televerse les deux images, dans l ordre', async () => {
    creerOeuvre.mockResolvedValue({ ulid: '01kzth' })
    televerserMedia.mockResolvedValue({})

    await enfiler(brouillon, images())
    const bilan = await vider()

    expect(creerOeuvre).toHaveBeenCalledTimes(1)
    expect(televerserMedia.mock.calls.map((appel) => appel[2])).toEqual(['principal', 'brute'])
    expect(bilan).toEqual({ envoyes: 1, restants: 0 })
    expect(disque.size).toBe(0)
  })

  /**
   * Le comportement pour lequel la file existe : coupure reseau, l app fermee, et rien
   * n est perdu ni renvoye en double au retour.
   */
  it('reprend ou elle s est arretee apres une coupure reseau', async () => {
    creerOeuvre.mockResolvedValue({ ulid: '01kzth' })
    televerserMedia
      .mockResolvedValueOnce({})
      .mockRejectedValueOnce(new ErreurApi('Reseau indisponible', 0))

    await enfiler(brouillon, images())

    expect(await vider()).toEqual({ envoyes: 0, restants: 1 })

    const interrompu = (await file())[0]
    expect(interrompu.ulid).toBe('01kzth')
    expect(interrompu.fichiers.map((f) => f.envoye)).toEqual([true, false])
    expect(interrompu.erreur).toBeNull()

    // Le reseau revient : l oeuvre n est pas recreee, seule l image manquante repart.
    televerserMedia.mockResolvedValue({})

    expect(await vider()).toEqual({ envoyes: 1, restants: 0 })
    expect(creerOeuvre).toHaveBeenCalledTimes(1)
    expect(televerserMedia).toHaveBeenCalledTimes(3)
  })

  it('n insiste pas sur un refus definitif du serveur, et le montre', async () => {
    creerOeuvre.mockRejectedValue(new ErreurApi('Le champ annee est invalide', 422))

    await enfiler(brouillon, images())

    expect(await vider()).toEqual({ envoyes: 0, restants: 1 })
    expect((await file())[0].erreur).toBe('Le champ annee est invalide')

    // Deuxieme passage : on ne rappelle pas le serveur avec la meme donnee fausse.
    await vider()
    expect(creerOeuvre).toHaveBeenCalledTimes(1)

    // Une fois la cause corrigee, l artiste relance l envoi.
    creerOeuvre.mockResolvedValue({ ulid: '01kzth' })
    televerserMedia.mockResolvedValue({})
    await reprendre((await file())[0].id)

    expect(await vider()).toEqual({ envoyes: 1, restants: 0 })
  })

  it('n arrete pas la file entiere sur un envoi refuse', async () => {
    creerOeuvre
      .mockRejectedValueOnce(new ErreurApi('refus', 422))
      .mockResolvedValueOnce({ ulid: '01kzth' })
    televerserMedia.mockResolvedValue({})

    await enfiler(brouillon, images())
    await enfiler({ ...brouillon, title: 'Roseaux bas' }, images())

    expect(await vider()).toEqual({ envoyes: 1, restants: 1 })
    expect((await file())[0].erreur).toBe('refus')
  })

  it('supprime les images restees sur le disque quand l artiste retire un envoi', async () => {
    const { id } = await enfiler(brouillon, images())

    await retirer(id)

    expect(await file()).toEqual([])
    expect(disque.size).toBe(0)
  })
})
