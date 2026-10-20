// @vitest-environment node

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ErreurApi } from '@/services/api'

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

const tracerPartage = vi.fn()

vi.mock('@/services/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/services/api')>()),
  api: { tracerPartage: (...args: unknown[]) => tracerPartage(...args) },
}))

const { enfilerTrace, filePartages, retirerTrace, viderPartages } = await import(
  '@/services/filePartages'
)

beforeEach(() => {
  stockage.clear()
  tracerPartage.mockReset()
})

describe('file des traces de partage', () => {
  it('garde la trace tant qu elle n est pas partie', async () => {
    await enfilerTrace('01kzth', 'instagram', 'Sur le motif.')

    const attente = await filePartages()

    expect(attente).toHaveLength(1)
    expect(attente[0]).toMatchObject({ ulid: '01kzth', network: 'instagram', erreur: null })
  })

  it('envoie la trace avec la date du PARTAGE et non celle de l envoi', async () => {
    tracerPartage.mockResolvedValue({})

    const trace = await enfilerTrace('01kzth', 'whatsapp', null)

    expect(await viderPartages()).toEqual({ envoyes: 1, restants: 0 })
    expect(tracerPartage).toHaveBeenCalledWith('01kzth', {
      network: 'whatsapp',
      caption: null,
      posted_at: trace.postedAt,
    })
  })

  /** Un partage se fait souvent sans reseau : la trace doit survivre a la coupure. */
  it('garde tout en place sur une panne reseau et repart au retour', async () => {
    tracerPartage.mockRejectedValueOnce(new ErreurApi('Reseau indisponible', 0))

    await enfilerTrace('01kzth', 'instagram', null)

    expect(await viderPartages()).toEqual({ envoyes: 0, restants: 1 })
    expect((await filePartages())[0].erreur).toBeNull()

    tracerPartage.mockResolvedValue({})

    expect(await viderPartages()).toEqual({ envoyes: 1, restants: 0 })
  })

  it('n insiste pas sur un refus definitif et le montre', async () => {
    tracerPartage.mockRejectedValue(new ErreurApi('Oeuvre introuvable', 404))

    await enfilerTrace('01kzth', 'instagram', null)

    expect(await viderPartages()).toEqual({ envoyes: 0, restants: 1 })
    expect((await filePartages())[0].erreur).toBe('Oeuvre introuvable')

    await viderPartages()
    expect(tracerPartage).toHaveBeenCalledTimes(1)
  })

  it('laisse retirer une trace a la main', async () => {
    const { id } = await enfilerTrace('01kzth', 'autre', null)

    await retirerTrace(id)

    expect(await filePartages()).toEqual([])
  })
})
