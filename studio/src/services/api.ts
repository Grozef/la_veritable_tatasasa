import type { MediaRole, SocialNetwork, SocialPost, Work } from '@/api/types'
import { jeton, oublierJeton, poserJeton } from './session'

/**
 * Seul point de sortie reseau de l'app atelier. Aucune vue n'appelle `fetch`.
 *
 * L'adresse est injectee au build (`VITE_API_URL`) : une application empaquetee ne peut
 * pas deviner l'hote de l'API, et un chemin relatif ne veut rien dire dans une WebView
 * qui sert ses fichiers depuis `capacitor://`.
 */
const BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

export class ErreurApi extends Error {
  constructor(
    message: string,
    public readonly statut: number,
    /** Messages de validation par champ, tels que renvoyes par Laravel en 422. */
    public readonly champs: Record<string, string[]> = {},
  ) {
    super(message)
    this.name = 'ErreurApi'
  }

  /** Vrai quand la requete n'a jamais atteint le serveur : c'est le cas a mettre en file. */
  get horsLigne(): boolean {
    return this.statut === 0
  }
}

interface Enveloppe<T> {
  data: T
}

async function appeler<T>(chemin: string, options: RequestInit = {}): Promise<T> {
  const entetes = new Headers(options.headers)
  entetes.set('Accept', 'application/json')

  const porteur = await jeton()

  if (porteur) {
    entetes.set('Authorization', `Bearer ${porteur}`)
  }

  let reponse: Response

  try {
    reponse = await fetch(`${BASE}${chemin}`, { ...options, headers: entetes })
  } catch (cause) {
    // Panne reseau, pas reponse du serveur : c'est ce que la file d'attente doit retenir.
    throw new ErreurApi(cause instanceof Error ? cause.message : 'Reseau indisponible', 0)
  }

  if (reponse.status === 401) {
    // Le jeton a ete revoque depuis un autre appareil : le garder ferait boucler l'app
    // sur des 401 sans jamais reproposer l'ecran de connexion.
    await oublierJeton()
    throw new ErreurApi('Session expiree', 401)
  }

  if (!reponse.ok) {
    const corps = await reponse.json().catch(() => ({}))

    throw new ErreurApi(
      corps.message ?? `${chemin} a rendu ${reponse.status}`,
      reponse.status,
      corps.errors ?? {},
    )
  }

  if (reponse.status === 204) {
    return undefined as T
  }

  return ((await reponse.json()) as Enveloppe<T>).data
}

function json(corps: unknown): RequestInit {
  return {
    method: 'POST',
    body: JSON.stringify(corps),
    headers: { 'Content-Type': 'application/json' },
  }
}

/** Brouillon d'oeuvre, en snake_case : c'est ce qu'attend le Form Request de l'API. */
export interface BrouillonOeuvre {
  title: string
  year: number
  width_mm: number
  height_mm: number
  medium?: string
  paper_grain?: string
  status?: string
  notes?: string | null
  series_id?: number | null
  published_at?: string | null
}

export const api = {
  async connexion(email: string, motDePasse: string, appareil: string): Promise<void> {
    const { token } = await appeler<{ token: string }>(
      '/studio/token',
      json({ email, password: motDePasse, device_name: appareil }),
    )

    await poserJeton(token)
  },

  async deconnexion(): Promise<void> {
    // Le jeton local tombe meme si le serveur est injoignable : rester « connecte » a un
    // serveur qu'on ne joint pas n'aide personne.
    await appeler<void>('/studio/token', { method: 'DELETE' }).catch(() => undefined)
    await oublierJeton()
  },

  oeuvres: () => appeler<Work[]>('/studio/works'),

  creerOeuvre: (brouillon: BrouillonOeuvre) => appeler<Work>('/studio/works', json(brouillon)),

  /**
   * Televersement multipart. Pas d'en-tete `Content-Type` pose a la main : le navigateur
   * doit ecrire lui-meme la frontiere du multipart, et l'imposer casse le decodage cote
   * serveur.
   */
  televerserMedia(ulid: string, fichier: Blob, role: MediaRole, position: number, nom: string) {
    const corps = new FormData()
    corps.append('file', fichier, nom)
    corps.append('role', role)
    corps.append('position', String(position))

    return appeler<{ role: MediaRole; sources: unknown }>(`/studio/works/${ulid}/media`, {
      method: 'POST',
      body: corps,
    })
  },

  /**
   * L'image principale, servie par l'API et non par `storage`.
   *
   * Les fichiers de `storage` sortent SANS en-tete CORS (verifie sur la reponse d'une
   * derivee). Une image d'une autre origine souille le canvas qui la dessine : le visuel
   * de partage se composerait sans erreur visible, puis son export leverait. Ici les
   * octets arrivent par `fetch`, donc sans notion d'origine pour le canvas.
   */
  async apercu(ulid: string): Promise<Blob> {
    const entetes = new Headers({ Accept: 'image/*' })
    const porteur = await jeton()

    if (porteur) {
      entetes.set('Authorization', `Bearer ${porteur}`)
    }

    let reponse: Response

    try {
      reponse = await fetch(`${BASE}/studio/works/${ulid}/apercu`, { headers: entetes })
    } catch (cause) {
      throw new ErreurApi(cause instanceof Error ? cause.message : 'Reseau indisponible', 0)
    }

    if (!reponse.ok) {
      throw new ErreurApi(`Apercu indisponible (${reponse.status})`, reponse.status)
    }

    return reponse.blob()
  },

  /**
   * Trace un partage. L'API ne publie RIEN : la feuille de partage native a deja fait le
   * travail, cet appel ne fait qu'en garder la memoire.
   */
  tracerPartage: (
    ulid: string,
    trace: { network: SocialNetwork; caption: string | null; posted_at: string },
  ) => appeler<SocialPost>(`/studio/works/${ulid}/social-posts`, json(trace)),

  /**
   * Relit l'oeuvre pour recuperer le nuancier que le job vient de calculer.
   *
   * Par la liste, faute de route `GET /studio/works/{ulid}` : l'atelier d'une artiste
   * tient en quelques centaines d'oeuvres, ajouter une route pour eviter ce filtrage
   * couterait plus qu'il ne rapporte.
   */
  oeuvre: (ulid: string) => appeler<Work[]>('/studio/works').then(
    (oeuvres) => oeuvres.find((oeuvre) => oeuvre.ulid === ulid) ?? null,
  ),
}
