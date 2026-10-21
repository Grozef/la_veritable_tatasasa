<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useWorksStore } from '@/stores/works'
import { useTitre } from '@/composables/titre'

const props = defineProps<{ slug: string }>()

const store = useWorksStore()

onMounted(() => store.charger())

const work = computed(() => store.parSlug(props.slug))
// Absente d'un carnet bien charge : introuvable. Carnet en echec : indisponible, a reessayer.
useTitre(
  () =>
    work.value?.title ??
    (store.chargement ? null : store.erreur ? 'Œuvre indisponible' : 'Œuvre introuvable'),
)

const media = computed(
  () => work.value?.media.find((m) => m.role === 'principal') ?? work.value?.media[0] ?? null,
)

/*
 * Derivee haute definition : demandee au premier agrandissement, jamais au chargement.
 * Le srcset suffit a l'echelle 1 ; au-dela il etirerait au mieux 800 px. Une fois
 * chargee elle reste en place (recadrer puis rezoomer ne la redemande pas). Retenue par
 * URL, pour qu'une autre fiche reparte du srcset. Fichier absent (media derive avant la
 * HD) : la HD avif manquante cede a la HD webp, la HD webp manquante au srcset, plutot
 * qu'une image cassee. Chaque echec est retenu : aucune URL n'est redemandee.
 */
const hdPour = ref<string | null>(null)
const hdEchouee = ref<string | null>(null)
const hdAvifEchouee = ref<string | null>(null)
const hd = computed(() => {
  const source = media.value?.hd
  return source && hdPour.value === source.webp && hdEchouee.value !== source.webp ? source : null
})
const hdAvif = computed(() =>
  hd.value && hdAvifEchouee.value !== hd.value.avif ? hd.value.avif : null,
)

function hdIndisponible(event: Event): void {
  if (!hd.value) {
    return
  }

  // Seule l'avif a echoue : on retire sa source, le navigateur passe a la HD webp de l'image.
  const echec = (event.target as HTMLImageElement).currentSrc
  if (hdAvif.value && echec === new URL(hdAvif.value, document.baseURI).href) {
    hdAvifEchouee.value = hdAvif.value
    return
  }

  hdEchouee.value = hd.value.webp
}

const grains: Record<string, string> = {
  torchon: 'grain torchon',
  satine: 'grain satiné',
  fin: 'grain fin',
}

/*
 * Zoom et deplacement a la main. Le grain du papier EST le sujet d'une aquarelle :
 * une vue qui ne permet pas d'y entrer ne montre pas l'oeuvre. Deux doigts pour
 * l'echelle, un doigt ou la souris pour se deplacer, Ctrl + molette au pointeur, double
 * clic pour revenir au cadre.
 *
 * A l'echelle 1 l'image est un passage de la page : un doigt ou la molette la font
 * defiler. Le cadre ne garde ces gestes pour lui qu'une fois l'image agrandie.
 */
const echelle = ref(1)
const dx = ref(0)
const dy = ref(0)
const pointeurs = new Map<number, { x: number; y: number }>()
let ecartInitial = 0
let echelleInitiale = 1

const transformation = computed(() => ({
  transform: `translate(${dx.value}px, ${dy.value}px) scale(${echelle.value})`,
  cursor: echelle.value > 1 ? 'grab' : 'zoom-in',
}))

const borner = (valeur: number) => Math.min(6, Math.max(1, valeur))

watch(echelle, (valeur) => {
  if (valeur > 1 && media.value?.hd) {
    hdPour.value = media.value.hd.webp
  }
})

/** Virgule decimale a la francaise : « 2,5× » et non « 2.5x ». */
const echelleLue = computed(() =>
  echelle.value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
)

function zoomer(facteur: number, px: number, py: number): void {
  const suivante = borner(echelle.value * facteur)
  const rapport = suivante / echelle.value

  // Le point vise reste sous le doigt : sans cette correction, zoomer ramene toujours
  // au centre et on perd la zone qu'on observait.
  dx.value = px - (px - dx.value) * rapport
  dy.value = py - (py - dy.value) * rapport
  echelle.value = suivante

  if (suivante === 1) {
    dx.value = 0
    dy.value = 0
  }
}

const ecart = () => {
  const [a, b] = [...pointeurs.values()]
  return a && b ? Math.hypot(a.x - b.x, a.y - b.y) : 0
}

const milieu = () => {
  const [a, b] = [...pointeurs.values()]
  return a && b ? { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 } : { x: 0, y: 0 }
}

const local = (event: PointerEvent | WheelEvent) => {
  const cadre = (event.currentTarget as HTMLElement).getBoundingClientRect()
  return { x: event.clientX - cadre.left, y: event.clientY - cadre.top }
}

function saisir(event: PointerEvent): void {
  ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
  pointeurs.set(event.pointerId, local(event))

  if (pointeurs.size === 2) {
    ecartInitial = ecart()
    echelleInitiale = echelle.value
  }
}

function deplacer(event: PointerEvent): void {
  const precedent = pointeurs.get(event.pointerId)

  if (!precedent) {
    return
  }

  const courant = local(event)
  pointeurs.set(event.pointerId, courant)

  if (pointeurs.size === 2 && ecartInitial > 0) {
    const centre = milieu()
    zoomer((echelleInitiale * (ecart() / ecartInitial)) / echelle.value, centre.x, centre.y)

    return
  }

  if (echelle.value > 1) {
    dx.value += courant.x - precedent.x
    dy.value += courant.y - precedent.y
  }
}

function lacher(event: PointerEvent): void {
  pointeurs.delete(event.pointerId)
  ecartInitial = 0
}

/*
 * `touch-action: pan-y` laisse le defilement vertical au navigateur : un pincement qui derive
 * a la verticale serait pris pour un defilement (pointercancel, zoom perdu). Des que deux
 * doigts sont poses, le geste est garde pour l'image.
 */
function deuxDoigts(event: TouchEvent): void {
  if (event.touches.length > 1) {
    event.preventDefault()
  }
}

function molette(event: WheelEvent): void {
  // Ctrl + molette (et le pincement d'un pave tactile, qui arrive ainsi) zoome toujours.
  if (echelle.value === 1 && !event.ctrlKey) {
    return
  }

  event.preventDefault()
  const point = local(event)
  zoomer(event.deltaY < 0 ? 1.15 : 1 / 1.15, point.x, point.y)
}

function recadrer(): void {
  echelle.value = 1
  dx.value = 0
  dy.value = 0
}

/*
 * Le meme zoom au clavier, sur le cadre focalise : + et - autour du centre, 0 pour
 * recadrer, fleches pour se deplacer une fois l'image agrandie. A l'echelle 1 les
 * fleches sont laissees a la page, qui doit encore defiler.
 */
const PAS = 40

function clavier(event: KeyboardEvent): void {
  // Ctrl/Cmd +, -, 0 : le zoom de PAGE du navigateur, dont dependent les malvoyants.
  if (event.ctrlKey || event.metaKey || event.altKey) {
    return
  }

  const cadre = event.currentTarget as HTMLElement
  const [cx, cy] = [cadre.clientWidth / 2, cadre.clientHeight / 2]
  const deplacements: Record<string, [number, number]> = {
    ArrowLeft: [PAS, 0],
    ArrowRight: [-PAS, 0],
    ArrowUp: [0, PAS],
    ArrowDown: [0, -PAS],
  }

  if (event.key === '+' || event.key === '=') {
    zoomer(1.25, cx, cy)
  } else if (event.key === '-') {
    zoomer(1 / 1.25, cx, cy)
  } else if (event.key === '0') {
    recadrer()
  } else if (deplacements[event.key] && echelle.value > 1) {
    const [x, y] = deplacements[event.key]!
    dx.value += x
    dy.value += y
  } else {
    return
  }

  event.preventDefault()
}
</script>

<template>
  <article v-if="work" class="oeuvre">
    <div
      class="cadre"
      :class="{ agrandi: echelle > 1 }"
      :tabindex="media?.sources ? 0 : undefined"
      :role="media?.sources ? 'group' : undefined"
      :aria-label="media?.sources ? `Zoom sur ${work.title}` : undefined"
      :aria-describedby="media?.sources ? 'consigne-zoom' : undefined"
      @keydown="clavier"
      @pointerdown="saisir"
      @pointermove="deplacer"
      @pointerup="lacher"
      @pointercancel="lacher"
      @lostpointercapture="lacher"
      @touchstart="deuxDoigts"
      @touchmove="deuxDoigts"
      @wheel="molette"
      @dblclick="recadrer"
    >
      <!-- width/height : le navigateur reserve la place de l'image avant son arrivee.
           Une fois zoomee, la HD remplace le srcset (URL seule, densite 1x). -->
      <picture v-if="media?.sources">
        <source
          v-if="!hd || hdAvif"
          type="image/avif"
          :srcset="hdAvif ?? media.sources.avif"
          :sizes="hd ? undefined : '(min-width: 70rem) 65vw, 100vw'"
        />
        <img
          :src="hd ? hd.webp : media.sources.webp.split(', ')[0]?.split(' ')[0]"
          :srcset="hd ? hd.webp : media.sources.webp"
          :sizes="hd ? undefined : '(min-width: 70rem) 65vw, 100vw'"
          :width="media.width"
          :height="media.height"
          :alt="`${work.title}, ${work.year}`"
          :style="[transformation, { viewTransitionName: `oeuvre-${work.slug}` }]"
          @error="hdIndisponible"
        />
      </picture>
      <p v-else class="etat">Les dérivées de cette image ne sont pas encore prêtes.</p>
    </div>

    <!-- Visible des que le cadre a le focus clavier : la touche doit se trouver sans
         avoir a la deviner. -->
    <p v-if="media?.sources" id="consigne-zoom" class="aide consigne">
      Touches + et − pour agrandir, flèches pour se déplacer, 0 pour revenir au cadre.
    </p>

    <!-- Le geste de retour depend du pointeur : au doigt, le double-clic n'existe pas. -->
    <p v-if="echelle > 1" class="aide">
      Échelle {{ echelleLue }}× —
      <span class="geste-souris">double-clic pour recadrer</span>
      <span class="geste-doigts">resserrez deux doigts pour recadrer</span>
    </p>

    <div class="fiche">
      <h1>{{ work.title }}</h1>

      <dl>
        <dt>Année</dt>
        <dd>{{ work.year }}</dd>

        <dt>Technique</dt>
        <dd>{{ work.medium }}, {{ grains[work.paperGrain] ?? work.paperGrain }}</dd>

        <dt>Dimensions</dt>
        <dd>{{ work.widthMm }}&nbsp;×&nbsp;{{ work.heightMm }}&nbsp;mm</dd>

        <!-- Statut masque sur le site public : son affichage (dont « vendue ») est une
             decision ouverte avec l'artiste, voir PRODUCT.md, « Decisions ouvertes ». -->

        <template v-if="work.series">
          <dt>Série</dt>
          <dd>
            <RouterLink :to="{ name: 'serie', params: { slug: work.series.slug } }">
              {{ work.series.title }}
            </RouterLink>
          </dd>
        </template>
      </dl>

      <p v-if="work.notes" class="note">{{ work.notes }}</p>

      <!-- Un nuancier vide est un resultat legitime (feuille vierge, lavis qu'aucun tube
           n'approche) : pas de titre au-dessus d'une liste vide. -->
      <template v-if="work.pigments.length">
        <h2>Pigments</h2>
        <ul class="pigments">
          <li v-for="pigment in work.pigments" :key="pigment.slug">
            <RouterLink :to="{ name: 'pigment', params: { slug: pigment.slug } }">
              <span class="pastille" :style="{ backgroundColor: pigment.hex }" aria-hidden="true" />
              {{ pigment.name }}
              <span class="part">{{ Math.round(pigment.weight * 100) }}&nbsp;% de la surface peinte</span>
            </RouterLink>
          </li>
        </ul>
      </template>
    </div>
  </article>

  <p v-else-if="store.chargement" class="etat reserve">Ouverture de l'œuvre…</p>

  <div v-else-if="store.erreur" class="etat reserve">
    <h1 class="lecteur-seul">Œuvre indisponible</h1>
    <p role="alert">
      Le carnet n'a pas pu s'ouvrir.
      <button type="button" @click="store.charger()">Réessayer</button>
    </p>
  </div>

  <div v-else class="etat reserve">
    <h1 class="lecteur-seul">Œuvre introuvable</h1>
    <p>
      Cette œuvre n'est pas au carnet. <RouterLink :to="{ name: 'carnet' }">Y revenir</RouterLink>
    </p>
  </div>
</template>

<style scoped>
.oeuvre {
  padding: var(--marge);
  display: grid;
  gap: clamp(1.5rem, 4vw, 3rem);
}

@media (min-width: 70rem) {
  .oeuvre {
    grid-template-columns: minmax(0, 2fr) minmax(16rem, 1fr);
    align-items: start;
  }

  .aide {
    grid-column: 1;
  }

  .fiche {
    grid-column: 2;
    grid-row: 1 / span 3;
  }
}

.cadre {
  overflow: hidden;
  background: var(--papier-ombre);
  /* Un doigt fait defiler la page ; deux doigts restent au zoom de l'image. */
  touch-action: pan-y;
  box-shadow: 0 1px 0 var(--filet);
}

.cadre.agrandi {
  touch-action: none;
}

/* `picture` est en ligne par defaut : sans cela le `width: 100%` de l'image se
   mesurerait sur une boite retrecie au contenu. */
picture {
  display: block;
}

img {
  display: block;
  width: 100%;
  height: auto;
  transform-origin: 0 0;
  user-select: none;
  -webkit-user-drag: none;
}

.aide {
  margin: 0;
  color: var(--encre-pale);
  font-size: 0.85rem;
}

/* Hors de la page tant que le cadre n'a pas le focus clavier, lue quand meme. */
.consigne {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.cadre:focus-visible + .consigne {
  position: static;
  width: auto;
  height: auto;
  clip-path: none;
  white-space: normal;
}

.geste-doigts {
  display: none;
}

@media (pointer: coarse) {
  .geste-souris {
    display: none;
  }

  .geste-doigts {
    display: inline;
  }
}

/* Conteneur : la liste ci-dessous se regle sur la largeur de la fiche, pas de l'ecran. */
.fiche {
  container-type: inline-size;
}

.fiche h1 {
  font-size: clamp(1.6rem, 1.2rem + 1.5vw, 2.4rem);
}

dl {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.35rem 1.5rem;
  margin: 0 0 1.5rem;
  padding-top: 0.75rem;
  border-top: var(--filet-trait);
  font-size: 0.95rem;
}

/*
 * Fiche trop etroite pour l'intitule et sa valeur cote a cote (« 420 × 300 mm » est
 * insecable) : une colonne, la valeur sous son intitule. En rem, le seuil suit la taille
 * du texte : a 200 % la fiche d'un ecran de 390 px passe sur une colonne au lieu de
 * deborder ; au texte normal, deux colonnes jusqu'a 320 px.
 */
@container (max-width: 13rem) {
  dl {
    grid-template-columns: 1fr;
    row-gap: 0;
  }

  dd + dt {
    margin-top: 0.35rem;
  }
}

dt {
  color: var(--encre-pale);
}

dd {
  margin: 0;
}

.note {
  /* Evite le mot seul en derniere ligne (« doigts. ») sous la main manuscrite. */
  text-wrap: pretty;
  font-family: var(--manuscrite);
  color: var(--encre-pale);
  padding-left: 1.2rem;
  border-left: 2px solid var(--papier-ombre);
}

.pigments {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.5rem;
}

.pigments a {
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.pastille {
  width: 1rem;
  height: 1rem;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px var(--contour-pastille);
}

.part {
  grid-column: 2;
  color: var(--encre-pale);
  font-size: 0.8rem;
}

.pigments a:hover,
.pigments a:focus-visible {
  text-decoration: underline;
}

.etat {
  padding: var(--marge);
  color: var(--encre-pale);
}

/*
 * Pendant l'ouverture, la place de la fiche est tenue jusqu'au bas de l'ecran : sinon le
 * pied de page s'affiche juste sous ce message, puis l'arrivee de la fiche le chasse
 * (decalage de mise en page mesure a 0,48 sur la fiche). Les issues sans fiche (absente,
 * indisponible) gardent la meme place : la relacher ferait remonter le pied de page
 * (0,76 mesure sur une oeuvre absente).
 */
.etat.reserve {
  min-height: 100svh;
  /* Sans elle, la marge du message traverse `main` et le decale a l'arrivee du contenu. */
  margin-top: 0;
}

/* 44 px de cible tactile ; la marge negative egale laisse la ligne a sa hauteur. */
.etat button {
  --toucher: max(0px, (44px - 1lh) / 2);
  padding-block: var(--toucher);
  margin-block: calc(-1 * var(--toucher));
  margin-left: 0.5rem;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-decoration: underline;
  cursor: pointer;
}
</style>
