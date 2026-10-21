// @vitest-environment node

import { describe, expect, it } from 'vitest'
import {
  libelle,
  libellesGrain,
  libellesReseau,
  libellesStatut,
} from '@/composables/libelles'

describe('libelles des valeurs d enum', () => {
  it('affiche les statuts en francais accentue, sans toucher aux valeurs', () => {
    expect(libellesStatut).toEqual({
      atelier: 'Atelier',
      exposee: 'Exposée',
      vendue: 'Vendue',
      privee: 'Privée',
    })
  })

  it('nomme les grains et les reseaux', () => {
    expect(libelle(libellesGrain, 'satine')).toBe('Satiné')
    expect(libelle(libellesReseau, 'instagram')).toBe('Instagram')
    expect(libelle(libellesReseau, 'whatsapp')).toBe('WhatsApp')
  })

  it('rend une valeur inconnue telle quelle plutot que vide', () => {
    expect(libelle(libellesStatut, 'restauree')).toBe('restauree')
  })
})
