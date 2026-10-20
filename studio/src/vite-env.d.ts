/// <reference types="vite/client" />

interface ImportMetaEnv {
  /**
   * Adresse de l'API, prefixe `/api` compris.
   *
   * Injectee au BUILD : une application empaquetee ne peut pas deviner l'hote de l'API,
   * et un chemin relatif ne veut rien dire dans une WebView qui sert ses fichiers depuis
   * `capacitor://`. Changer d'adresse impose donc de reconstruire l'APK.
   */
  readonly VITE_API_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
