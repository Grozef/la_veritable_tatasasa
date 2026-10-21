// @vitest-environment node

import { describe, expect, it, vi } from 'vitest'
import { creerRendu } from '@/services/filtres'

/** Faux contexte WebGL : chaque appel non prevu rend un objet, les compilations reussissent. */
function fauxContexte() {
  const loseContext = vi.fn()
  const appels: string[] = []
  const gl = new Proxy(
    { getExtension: (nom: string) => (nom === 'WEBGL_lose_context' ? { loseContext } : null) },
    {
      get(cible, nom: string) {
        if (nom in cible) {
          return cible[nom as keyof typeof cible]
        }

        return (..._args: unknown[]) => {
          appels.push(nom)

          return {}
        }
      },
    },
  )
  const canvas = { width: 4, height: 4, getContext: () => gl } as unknown as HTMLCanvasElement

  return { canvas, loseContext, appels }
}

describe('creerRendu', () => {
  it('detruire rend le contexte au navigateur', () => {
    const { canvas, loseContext, appels } = fauxContexte()
    const rendu = creerRendu(canvas)

    expect(loseContext).not.toHaveBeenCalled()

    rendu.detruire()

    expect(loseContext).toHaveBeenCalledOnce()
    expect(appels).toEqual(expect.arrayContaining(['deleteTexture', 'deleteBuffer', 'deleteProgram']))
  })

  it('sans contexte WebGL, le dit au lieu de rendre un apercu vide', () => {
    const canvas = { getContext: () => null } as unknown as HTMLCanvasElement

    expect(() => creerRendu(canvas)).toThrow('WebGL indisponible')
  })
})
