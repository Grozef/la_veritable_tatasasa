// @vitest-environment node

import { describe, expect, it } from 'vitest'
import type { Media, Work } from '@/api/types'
import { vignette } from '@/composables/vignette'

// Forme relevee sur la reponse reelle de GET /studio/works (2026-10-21).
const principale = {
  role: 'principal',
  width: 1000,
  height: 719,
  lqipHex: '#c6c5cf',
  sources: {
    avif: 'http://localhost:8000/storage/works/a/x-400.avif 400w, http://localhost:8000/storage/works/a/x-800.avif 800w',
    webp: 'http://localhost:8000/storage/works/a/x-400.webp 400w, http://localhost:8000/storage/works/a/x-800.webp 800w',
  },
} as Media
const brute = { role: 'brute', width: 1600, height: 1200, lqipHex: '#6b5e4e', sources: null } as Media

const oeuvre = (media: Media[]) => ({ media }) as Work

describe('vignette', () => {
  it('prend le srcset webp de la principale, la plus petite derivee en repli', () => {
    expect(vignette(oeuvre([brute, principale]))).toEqual({
      srcset: principale.sources!.webp,
      src: 'http://localhost:8000/storage/works/a/x-400.webp',
      fond: '#c6c5cf',
    })
  })

  it('principale pas encore derivee : la couleur seule', () => {
    expect(vignette(oeuvre([{ ...principale, sources: null }]))).toEqual({
      srcset: null,
      src: null,
      fond: '#c6c5cf',
    })
  })

  it("sans principale, rien : la brute ne s'affiche pas", () => {
    expect(vignette(oeuvre([brute]))).toEqual({ srcset: null, src: null, fond: null })
    expect(vignette(oeuvre([]))).toEqual({ srcset: null, src: null, fond: null })
  })
})
