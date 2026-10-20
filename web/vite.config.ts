import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

// `import.meta.dirname` et non `__dirname` : Vite 8 avertit que son futur chargeur
// de configuration natif ne fournira plus les globales CommonJS.
export default defineConfig({
  plugins: [vue()],
  // `/api` reste un chemin RELATIF dans le code : en developpement il passe par ce
  // proxy vers `php artisan serve`, en production le front et l'API partagent l'hote.
  server: {
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './src'),
      '@shared': path.resolve(import.meta.dirname, '../shared'),
    },
  },
})
