<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { IonButton, IonNote } from '@ionic/vue'
import {
  appliquerGains,
  chercherPapier,
  gainsBalanceBlanc,
  mesurerPastille,
  type Rvb,
} from '@/composables/useBalanceBlanc'
import type { Pixels } from '@/composables/useHomographie'
import { reduire, versToile } from '@/composables/useToile'
import { useCapture } from '@/stores/capture'

const emit = defineEmits<{ suivant: [] }>()

const { redressee, gains, blancPapier } = useCapture()

const toile = ref<HTMLCanvasElement | null>(null)
const mesure = ref<Rvb | null>(null)
/** « Trouver le papier nu » n'a rien trouve : on le dit, et les gains restent tels quels. */
const introuvable = ref(false)

/**
 * Apercu reduit : la pipette se repose plusieurs fois de suite, et recalculer 6
 * megapixels a chaque essai ferait attendre l'artiste pour rien. Les gains, eux, sont
 * les memes quelle que soit la taille.
 */
let apercu: Pixels | null = null

const teinte = (couleur: Rvb) =>
  `rgb(${Math.round(couleur.r)}, ${Math.round(couleur.g)}, ${Math.round(couleur.b)})`

const corrigee = computed(() =>
  mesure.value
    ? {
        r: Math.min(255, mesure.value.r * gains.value.r),
        g: Math.min(255, mesure.value.g * gains.value.g),
        b: Math.min(255, mesure.value.b * gains.value.b),
      }
    : null,
)

onMounted(() => {
  if (redressee.value) {
    apercu = reduire(redressee.value, 900)
    peindre()
  }
})

watch(gains, peindre, { deep: true })

function peindre(): void {
  if (!apercu || !toile.value) {
    return
  }

  const copie: Pixels = { ...apercu, data: new Uint8ClampedArray(apercu.data) }

  versToile(appliquerGains(copie, gains.value), toile.value)
}

/** La pipette se pose sur une RESERVE : un endroit ou le papier est reste nu. */
function piper(evenement: PointerEvent): void {
  if (!apercu || !toile.value) {
    return
  }

  const boite = toile.value.getBoundingClientRect()
  const x = Math.round(((evenement.clientX - boite.left) / boite.width) * apercu.width)
  const y = Math.round(((evenement.clientY - boite.top) / boite.height) * apercu.height)

  mesurer(apercu, x, y)
}

/** Sans viser : la reserve la plus claire de l'image. Meme mesure que la pipette ensuite. */
function chercher(): void {
  if (!apercu) {
    return
  }

  const point = chercherPapier(apercu)

  introuvable.value = point === null

  if (point) {
    mesurer(apercu, point.x, point.y)
  }
}

function mesurer(image: Pixels, x: number, y: number): void {
  // Mesure sur l'apercu NON corrige, sinon chaque nouvelle pipette s'appliquerait sur
  // le resultat de la precedente et les gains s'empileraient.
  introuvable.value = false
  mesure.value = mesurerPastille(image, x, y)
  gains.value = gainsBalanceBlanc(mesure.value)
}

function annuler(): void {
  introuvable.value = false
  mesure.value = null
  gains.value = { r: 1, g: 1, b: 1 }
}
</script>

<template>
  <div class="etape">
    <p id="consigne-pipette" class="consigne">
      Touche une zone de réserve — du papier laissé nu. C'est ce point qui sera ramené au
      blanc du papier, et toute la photo avec lui.
    </p>

    <!-- La pipette se pose au doigt : la toile porte un nom et sa consigne ; le bouton
         « Trouver le papier nu » fait la meme mesure sans viser. -->
    <canvas
      ref="toile"
      class="apercu"
      role="img"
      aria-label="Photo redressée, pipette du blanc"
      aria-describedby="consigne-pipette"
      @pointerdown="piper"
    />

    <div v-if="mesure && corrigee" class="pastilles mesure">
      <span class="pastille" :style="{ background: teinte(mesure) }" />
      <span class="fleche">-&gt;</span>
      <span class="pastille" :style="{ background: teinte(corrigee) }" />
      <span class="pastille reference" :style="{ background: teinte(blancPapier) }" />
      <ion-note>mesuré, corrigé, blanc papier</ion-note>
    </div>

    <ion-note v-else class="note mesure">Aucune mesure : l'image partira telle quelle.</ion-note>

    <!-- Region live presente des le montage, sinon le premier message n'est pas annonce. -->
    <p class="alerte" role="status">
      <template v-if="introuvable">Pas de papier nu repéré : touche une zone blanche de la feuille.</template>
    </p>

    <ion-button class="chercher" expand="block" fill="outline" @click="chercher">
      Trouver le papier nu
    </ion-button>

    <ion-button class="annuler" expand="block" fill="outline" :disabled="!mesure" @click="annuler">
      Annuler la mesure
    </ion-button>

    <ion-button class="continuer" expand="block" @click="emit('suivant')">Continuer</ion-button>
  </div>
</template>

<style scoped>
.etape {
  padding: 1rem;
}

.consigne {
  margin-bottom: 1rem;
}

.apercu {
  display: block;
  width: 100%;
  height: auto;
  touch-action: none;
  cursor: crosshair;
}

.pastilles {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 1rem 0;
}

.pastille {
  width: 2rem;
  height: 2rem;
  border: 1px solid var(--ion-color-medium);
  border-radius: 4px;
}

.fleche {
  white-space: nowrap;
}

.reference {
  margin-left: 0.75rem;
  border-style: dashed;
}

.note {
  display: block;
  margin: 1rem 0;
}

.alerte {
  margin: 0 0 1rem;
  color: var(--ion-text-color);
  font-weight: 600;
}

.alerte:empty {
  margin: 0;
}

/*
 * En paysage, meme grille que le redressement : la photo a gauche, bornee a la hauteur de
 * l'ecran, consigne et actions a droite. Toile et Continuer restent visibles sans defiler.
 */
@media (orientation: landscape) {
  .etape {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(12rem, 18rem);
    grid-template-rows: auto auto auto minmax(0, 1fr) auto auto auto;
    grid-template-areas:
      'apercu consigne'
      'apercu mesure'
      'apercu alerte'
      'apercu .'
      'apercu chercher'
      'apercu annuler'
      'apercu continuer';
    column-gap: 1rem;
    box-sizing: border-box;
    height: 100%;
    padding: 1rem calc(1rem + var(--ion-safe-area-right, 0px)) calc(1rem + var(--ion-safe-area-bottom, 0px))
      calc(1rem + var(--ion-safe-area-left, 0px));
  }

  .consigne {
    grid-area: consigne;
    margin: 0;
  }

  .apercu {
    grid-area: apercu;
    place-self: center;
    width: auto;
    max-width: 100%;
    max-height: 100%;
  }

  .mesure {
    grid-area: mesure;
  }

  .alerte {
    grid-area: alerte;
  }

  .chercher {
    grid-area: chercher;
  }

  .annuler {
    grid-area: annuler;
  }

  .continuer {
    grid-area: continuer;
  }
}
</style>
