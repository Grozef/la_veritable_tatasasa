<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useWorksStore } from '@/stores/works'

const props = defineProps<{ slug: string }>()

const store = useWorksStore()

onMounted(() => store.charger())

const work = computed(() => store.parSlug(props.slug))
const media = computed(
  () => work.value?.media.find((m) => m.role === 'principal') ?? work.value?.media[0] ?? null,
)

const grains: Record<string, string> = {
  torchon: 'grain torchon',
  satine: 'grain satine',
  fin: 'grain fin',
}

/*
 * Zoom et deplacement a la main. Le grain du papier EST le sujet d'une aquarelle :
 * une vue qui ne permet pas d'y entrer ne montre pas l'oeuvre. Deux doigts pour
 * l'echelle, un doigt ou la souris pour se deplacer, molette au pointeur, double clic
 * pour revenir au cadre.
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

function molette(event: WheelEvent): void {
  const point = local(event)
  zoomer(event.deltaY < 0 ? 1.15 : 1 / 1.15, point.x, point.y)
}

function recadrer(): void {
  echelle.value = 1
  dx.value = 0
  dy.value = 0
}
</script>

<template>
  <article v-if="work" class="oeuvre">
    <div
      class="cadre"
      @pointerdown="saisir"
      @pointermove="deplacer"
      @pointerup="lacher"
      @pointercancel="lacher"
      @wheel.prevent="molette"
      @dblclick="recadrer"
    >
      <picture v-if="media?.sources">
        <source type="image/avif" :srcset="media.sources.avif" sizes="(min-width: 70rem) 65vw, 100vw" />
        <img
          :src="media.sources.webp.split(', ')[0]?.split(' ')[0]"
          :srcset="media.sources.webp"
          sizes="(min-width: 70rem) 65vw, 100vw"
          :alt="`${work.title}, ${work.year}`"
          :style="[transformation, { viewTransitionName: `oeuvre-${work.slug}` }]"
        />
      </picture>
      <p v-else class="etat">Les derivees de cette image ne sont pas encore pretes.</p>
    </div>

    <p v-if="echelle > 1" class="aide">
      Echelle {{ echelle.toFixed(1) }}x — double clic pour recadrer
    </p>

    <div class="fiche">
      <h1>{{ work.title }}</h1>

      <dl>
        <dt>Annee</dt>
        <dd>{{ work.year }}</dd>

        <dt>Technique</dt>
        <dd>{{ work.medium }}, {{ grains[work.paperGrain] ?? work.paperGrain }}</dd>

        <dt>Dimensions</dt>
        <dd>{{ work.widthMm }} x {{ work.heightMm }} mm</dd>

        <dt>Statut</dt>
        <dd>{{ work.status }}</dd>

        <template v-if="work.series">
          <dt>Serie</dt>
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
              <span class="part">{{ Math.round(pigment.weight * 100) }} % de la surface peinte</span>
            </RouterLink>
          </li>
        </ul>
      </template>
    </div>
  </article>

  <p v-else-if="store.chargement" class="etat">Ouverture de l'oeuvre…</p>

  <p v-else class="etat">
    Cette oeuvre n'est pas au carnet. <RouterLink :to="{ name: 'carnet' }">Y revenir</RouterLink>
  </p>
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
}

.cadre {
  overflow: hidden;
  background: var(--papier-ombre);
  touch-action: none;
  box-shadow: 0 1px 0 var(--filet);
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

dt {
  color: var(--encre-pale);
}

dd {
  margin: 0;
}

.note {
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
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.18);
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
</style>
