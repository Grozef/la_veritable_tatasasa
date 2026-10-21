<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { IonButton, IonNote, IonSpinner } from '@ionic/vue'
import { redresser, type Point } from '@/composables/useHomographie'
import { versToile } from '@/composables/useToile'
import { useCapture } from '@/stores/capture'

const emit = defineEmits<{ suivant: [] }>()

const { source, coins, redressee } = useCapture()

const toile = ref<HTMLCanvasElement | null>(null)
const cadre = ref<HTMLDivElement | null>(null)
const largeurAffichee = ref(1)
const travail = ref(false)
const erreur = ref<string | null>(null)

/** Rapport entre les pixels de la photo et ceux de l'ecran : tout passe par lui. */
const echelle = computed(() => (source.value ? largeurAffichee.value / source.value.width : 1))

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

  mesurer()
  window.addEventListener('resize', mesurer)
})

// Retour par etape remonte ce composant a chaque aller-retour : un ecouteur par montage.
onUnmounted(() => window.removeEventListener('resize', mesurer))

function mesurer(): void {
  largeurAffichee.value = cadre.value?.clientWidth ?? 1
}

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

    <div
      ref="cadre"
      class="cadre"
      @pointermove="deplacer"
      @pointerup="lacher"
      @pointercancel="lacher"
    >
      <canvas ref="toile" class="apercu" />

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

    <ion-note v-if="erreur" color="danger" class="note">{{ erreur }}</ion-note>

    <ion-button expand="block" :disabled="travail" @click="valider">
      <ion-spinner v-if="travail" name="dots" />
      <span v-else>Redresser</span>
    </ion-button>
  </div>
</template>

<style scoped>
.etape {
  padding: 1rem;
}

.consigne {
  margin-bottom: 1rem;
}

.cadre {
  position: relative;
  touch-action: none;
  user-select: none;
}

.apercu {
  display: block;
  width: 100%;
  height: auto;
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

/* Cible tactile large : un coin de feuille se vise mal au pixel pres avec un doigt. */
.poignee {
  position: absolute;
  width: 44px;
  height: 44px;
  margin: -22px 0 0 -22px;
  border: 2px solid var(--ion-color-primary);
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.35);
  padding: 0;
}

.note {
  display: block;
  margin: 0.75rem 0;
}
</style>
