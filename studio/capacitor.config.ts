import type { CapacitorConfig } from '@capacitor/cli';

// CAP_DEV=1 au moment de `cap sync` : build de developpement sur telephone, contre l'API locale
// en http sur le reseau (VITE_API_URL=http://<ip du poste>:8000/api). La WebView sert l'app en
// https : sans ces deux options, Android refuse le http en clair et le contenu mixte.
const dev = process.env.CAP_DEV === '1';

const config: CapacitorConfig = {
  appId: 'fr.laveritabletatasasa.atelier',
  appName: 'studio',
  webDir: 'dist',
  ...(dev && {
    server: { cleartext: true },
    android: { allowMixedContent: true },
  }),
};

export default config;
