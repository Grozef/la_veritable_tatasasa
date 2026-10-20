import { mergeConfig, defineConfig } from 'vitest/config'
// Extension explicite : le chargeur natif de Vite 8 n'infere plus `./vite.config`.
import viteConfig from './vite.config.ts'

// Fusion plutot que duplication : les alias `@` et `@shared` sont definis une seule
// fois, dans vite.config.ts. Un alias qui diverge entre build et tests donne des
// tests qui passent sur un module que l'application ne charge jamais.
export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      // `node` par defaut : demarrer jsdom coutait 53 s pour des tests qui ne
      // touchent pas au DOM. Les specs de composants (Phase 3) l'activeront
      // fichier par fichier avec `// @vitest-environment jsdom`.
      environment: 'node',
      include: ['src/**/*.spec.ts'],
      root: import.meta.dirname,
    },
  }),
)
