<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { IonButton, IonNote, IonSpinner } from '@ionic/vue'
import { redresser, type Point } from '@/composables/useHomographie'
import { echelleAjustee, versToile } from '@/composables/useToile'
import { useCapture } from '@/stores/capture'

const emit = defineEmits<{ suivant: [] }>()

const { source, coins, redressee } = useCapture()

const toile = ref<HTMLCanvasElement | null>(null)
const cadre = ref<HTMLDivElement | null>(null)
const zone = ref<HTMLDivElement | null>(null)
/** Place laissee a la photo, marges des poignees deduites : la photo y tient entiere, portrait comme paysage. */
const boite = ref({ largeur: 0, hauteur: 0 })
const travail = ref(false)
const erreur = ref<string | null>(null)

/** Rapport entre les pixels de la photo et ceux de l'ecran : tout passe par lui. */
const echelle = computed(() =>
  source.value ? echelleAjustee(source.value.width, source.value.height, boite.value) : 1,
)

/** Taille de l'apercu a l'ecran : le cadre l'epouse, donc ses coordonnees sont celles de la photo a l'echelle. */
const tailleApercu = computed(() =>
  source.value
    ? { width: `${source.value.width * echelle.value}px`, height: `${source.value.height * echelle.value}px` }
    : {},
)

const poignees = computed(() =>
  coins.value.map((coin) => ({
    x: coin.x * echelle.value,
    y: coin.y * echelle.value,
  })),
)

/** Le quadrilatere trace entre les poignees, pour voir ce qui sera redresse. */
const contour = computed(() =>
  poignees.value.map((poignee) => `${poignee.x},${poignee.y}`).join(' '),
)

onMounted(() => {
  if (source.value && toile.value) {
    versToile(source.value, toile.value)
  }

  // La zone change de taille a la rotation ET quand la page finit sa mise en page : un
  // observateur suit les deux, la ou `resize` ne voit que la fenetre.
  if (zone.value) {
    observateur.observe(zone.value)
  }
})

const observateur = new ResizeObserver(([entree]) => {
  boite.value = { largeur: entree.contentRect.width, hauteur: entree.contentRect.height }
})

// Retour par etape remonte ce composant a chaque aller-retour : un observateur par montage.
onUnmounted(() => observateur.disconnect())

let tire: number | null = null

function saisir(index: number, evenement: PointerEvent): void {
  tire = index

  /*
   * La capture du pointeur est un CONFORT — elle evite de perdre la poignee quand le
   * doigt sort du cadre. Elle leve si le pointeur n'est plus actif, et une exception ici
   * annulerait la prise en cours : le suivi doit survivre a son echec.
   */
  try {
    ;(evenement.target as HTMLElement).setPointerCapture(evenement.pointerId)
  } catch {
    // Sans capture, le suivi s'arrete au bord du cadre. C'est degrade, pas casse.
  }
}

function deplacer(evenement: PointerEvent): void {
  if (tire === null || !source.value || !cadre.value) {
    return
  }

  const boite = cadre.value.getBoundingClientRect()

  // Borne aux limites de la photo : une poignee tiree hors cadre ferait echantillonner
  // du vide et border la sortie de gris.
  const point: Point = {
    x: borner((evenement.clientX - boite.left) / echelle.value, source.value.width),
    y: borner((evenement.clientY - boite.top) / echelle.value, source.value.height),
  }

  coins.value = coins.value.map((coin, index) => (index === tire ? point : coin))
}

function lacher(): void {
  tire = null
}

function borner(valeur: number, maximum: number): number {
  return Math.min(maximum, Math.max(0, valeur))
}

async function valider(): Promise<void> {
  if (!source.value) {
    return
  }

  travail.value = true
  erreur.value = null

  // Laisse le temps au navigateur de peindre l'indicateur : le redressement est un
  // calcul par pixel, synchrone, qui gele l'interface le temps qu'il dure.
  await new Promise((suite) => setTimeout(suite, 30))

  try {
    redressee.value = redresser(source.value, coins.value)
    emit('suivant')
  } catch (cause) {
    erreur.value = (cause as Error).message
  } finally {
    travail.value = false
  }
}
</script>

<template>
  <div class="etape">
    <p class="consigne">
      Tire les quatre poignees sur les coins de la feuille. Une aquarelle photographiee au
      telephone n'est jamais de face.
    </p>

    <div ref="zone" class="zone">
      <div
        ref="cadre"
        class="cadre"
        @pointermove="deplacer"
        @pointerup="lacher"
        @pointercancel="lacher"
      >
        <canvas ref="toile" class="apercu" :style="tailleApercu" />

        <!-- Sans viewBox, le systeme de coordonnees du SVG est celui des pixels CSS :
             exactement celui dans lequel les poignees sont deja placees. -->
        <svg class="contour">
          <polygon :points="contour" />
        </svg>

        <button
          v-for="(poignee, index) in poignees"
          :key="index"
          class="poignee"
          type="button"
          :style="{ left: `${poignee.x}px`, top: `${poignee.y}px` }"
          :aria-label="`Coin ${index + 1}`"
          @pointerdown="saisir(index, $event)"
        />
      </div>
    </div>

    <ion-note v-if="erreur" color="danger" class="note">{{ erreur }}</ion-note>

    <ion-button class="action" expand="block" :disabled="travail" @click="valider">
      <ion-spinner v-if="travail" name="dots" />
      <span v-else>Redresser</span>
    </ion-button>
  </div>
</template>

<style scoped>
/*
 * La photo prend la place qui reste, entiere, en portrait comme en paysage : l'echelle
 * est calculee sur la plus petite dimension de la zone (voir `echelleAjustee`).
 */
.etape {
  display: grid;
  grid-template-rows: auto minmax(12rem, 1fr) auto auto;
  grid-template-areas: 'consigne' 'zone' 'note' 'action';
  box-sizing: border-box;
  height: 100%;
  padding: 1rem calc(1rem + var(--ion-safe-area-right, 0px)) calc(1rem + var(--ion-safe-area-bottom, 0px))
    calc(1rem + var(--ion-safe-area-left, 0px));
}

/* En paysage, la consigne et le bouton passent a cote : toute la hauteur va a la photo. */
@media (orientation: landscape) {
  .etape {
    grid-template-columns: minmax(0, 1fr) minmax(12rem, 18rem);
    grid-template-rows: minmax(0, 1fr) auto auto;
    grid-template-areas: 'zone consigne' 'zone note' 'zone action';
    column-gap: 1rem;
  }
}

.consigne {
  grid-area: consigne;
  margin: 0;
}

/*
 * 2rem autour de la photo : la moitie d'une poignee (24px) plus un jeu. Avec le 1rem de
 * l'etape, le bord de la photo est a 48px du bord de l'ecran : meme tiree sur ce bord, une
 * poignee reste hors de la bande du geste retour, et le bouton ne la recouvre jamais.
 */
.zone {
  grid-area: zone;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 0;
  min-height: 0;
  padding: 2rem;
}

.cadre {
  position: relative;
  touch-action: none;
  user-select: none;
}

.apercu {
  display: block;
}

.contour {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: visible;
}

.contour polygon {
  fill: rgba(255, 255, 255, 0.08);
  stroke: var(--ion-color-primary);
  stroke-width: 2;
  stroke-dasharray: 6 4;
}

/* Cible tactile large (48dp, plancher Android) : un coin de feuille se vise mal au pixel pres avec un doigt. */
.poignee {
  position: absolute;
  width: 48px;
  height: 48px;
  margin: -24px 0 0 -24px;
  border: 2px solid var(--ion-color-primary);
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.35);
  padding: 0;
}

.note {
  grid-area: note;
  display: block;
  margin: 0.75rem 0;
}

.action {
  grid-area: action;
}
</style>
