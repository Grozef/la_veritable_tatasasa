/**
 * Contrat partage entre le portfolio public (web/) et l'app atelier (studio/).
 *
 * ATTENTION : ces types decrivent ce que les ressources API DOIVENT rendre, ils ne
 * sont pas la preuve de ce qu'elles rendent. A confronter au JSON reel de
 * `GET /api/works` des que WorkResource existe (Phase 1) — un type qui ment est
 * pire qu'un type absent.
 */

export type WorkStatus = 'atelier' | 'exposee' | 'vendue' | 'privee';
export type PaperGrain = 'torchon' | 'satine' | 'fin';
/**
 * `brute` = la photo telle que sortie de l'appareil, archivee par l'app atelier avant
 * redressement et balance des blancs. Elle ne sort JAMAIS des routes publiques : le
 * portfolio ne la verra pas, seul l'atelier la liste.
 */
export type MediaRole = 'principal' | 'detail' | 'verso' | 'in_situ' | 'brute';

/** Une nuance du nuancier : c'est la navigation du site public. */
export interface Pigment {
  slug: string;
  name: string;
  /** Couleur d'affichage de la pastille, format `#rrggbb`. */
  hex: string;
  brand: string | null;
}

/** Part d'un pigment dans une oeuvre, telle que calculee par PaletteExtractor. */
export interface WorkPigment extends Pigment {
  /** Part de la surface peinte, hors reserve blanche. 0 a 1. */
  weight: number;
  isDominant: boolean;
}

export interface Media {
  role: MediaRole;
  width: number;
  height: number;
  /** Couleur de fond pendant le chargement — `dominantColor()` cote serveur. */
  lqipHex: string | null;
  /**
   * Sources par format, chacune en `srcset` prete a poser dans le <img>.
   * `null` tant que le job de derivation n'a pas tourne : a distinguer d'un
   * traitement termine sans resultat.
   */
  sources: {
    avif: string;
    webp: string;
  } | null;
  /**
   * Derivee haute definition, reservee au zoom de la fiche : URL simples (pas de
   * `srcset`), a ne demander qu'une fois l'image agrandie. `width` = largeur de
   * l'original plafonnee a 3000 px, jamais agrandie. `null` dans les memes cas que
   * `sources`.
   */
  hd: {
    width: number;
    avif: string;
    webp: string;
  } | null;
}

export interface Series {
  slug: string;
  title: string;
  year: number;
  notes: string | null;
  /** Present seulement sur la route serie, ou la relation est chargee. */
  works?: Work[];
}

/** Ou l'artiste declare avoir partage une oeuvre. */
export type SocialNetwork = 'instagram' | 'facebook' | 'whatsapp' | 'autre';

export interface SocialPost {
  network: SocialNetwork;
  postedAt: string;
  caption: string | null;
  /**
   * Identifiant de publication cote reseau. Toujours `null` en v1 : la feuille de
   * partage native ne rend rien. Ne sera renseigne que par une auto-publication.
   */
  externalId: string | null;
}

export interface Work {
  ulid: string;
  slug: string;
  title: string;
  year: number;
  medium: string;
  paperGrain: PaperGrain;
  /** Dimensions du papier en millimetres. */
  widthMm: number;
  heightMm: number;
  status: WorkStatus;
  /** Note d'atelier en markdown — la note manuscrite en marge du carnet. */
  notes: string | null;
  series: Series | null;
  pigments: WorkPigment[];
  media: Media[];
  /**
   * Historique des partages. Cle ABSENTE des routes publiques — pas `[]`, absente : le
   * portfolio ne charge pas la relation, ou une aquarelle a ete partagee ne regarde pas
   * le visiteur. Seul l'atelier la recoit.
   */
  socialPosts?: SocialPost[];
  publishedAt: string | null;
}
