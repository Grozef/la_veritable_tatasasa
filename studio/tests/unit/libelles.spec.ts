// @vitest-environment node

import { describe, expect, it } from 'vitest'
import {
  accorder,
  libelle,
  libellesGrain,
  libellesReseau,
  libellesStatut,
  valeurReglage,
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

describe('formats francais', () => {
  it('ecrit les reglages avec une virgule et deux decimales', () => {
    expect(valeurReglage(0)).toBe('0,00')
    expect(valeurReglage(0.25)).toBe('0,25')
    expect(valeurReglage(1.5)).toBe('1,50')
    expect(valeurReglage(-0.5)).toMatch(/^[-−]0,50$/)
  })

  it('accorde au singulier pour 0 et 1, au pluriel a partir de 2', () => {
    expect(accorder(0, 'image envoyée', 'images envoyées')).toBe('image envoyée')
    expect(accorder(1, 'image envoyée', 'images envoyées')).toBe('image envoyée')
    expect(accorder(2, 'image envoyée', 'images envoyées')).toBe('images envoyées')
  })
})
