// @vitest-environment node

import { beforeEach, describe, expect, it, vi } from 'vitest'

const share = vi.fn()

vi.mock('@capacitor/filesystem', () => ({
  Directory: { Cache: 'CACHE' },
  Filesystem: {
    writeFile: async () => ({ uri: 'file:///cache/partage.jpg' }),
    getUri: async () => ({ uri: 'file:///cache/partage.jpg' }),
  },
}))

vi.mock('@capacitor/share', () => ({
  Share: { share: (...args: unknown[]) => share(...args) },
}))

vi.mock('@/services/fichiers', () => ({
  blobVersBase64: async () => 'AAAA',
}))

const { partagerVisuel } = await import('@/services/partage')

const visuel = new Blob(['jpeg'], { type: 'image/jpeg' })

beforeEach(() => {
  share.mockReset()
})

describe('feuille de partage', () => {
  it('rend la main en partage quand la feuille aboutit', async () => {
    share.mockResolvedValueOnce({ activityType: 'com.instagram.android' })

    expect(await partagerVisuel(visuel, 'Titre', 'Legende')).toBe('partage')
  })

  it('distingue la feuille refermee (message exact de @capacitor/share 8 sur Android et iOS)', async () => {
    share.mockRejectedValueOnce(new Error('Share canceled'))

    expect(await partagerVisuel(visuel, 'Titre', 'Legende')).toBe('annule')
  })

  it('distingue la feuille refermee sur le web (AbortError de navigator.share)', async () => {
    share.mockRejectedValueOnce(new DOMException('Share canceled', 'AbortError'))

    expect(await partagerVisuel(visuel, 'Titre', 'Legende')).toBe('annule')
  })

  it('signale l indisponibilite reelle', async () => {
    share.mockRejectedValueOnce(new Error('Share API not available in this browser'))

    expect(await partagerVisuel(visuel, 'Titre', 'Legende')).toBe('indisponible')
  })
})
