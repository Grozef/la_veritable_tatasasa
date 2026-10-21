<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { IonButton, IonNote } from '@ionic/vue'
import {
  appliquerGains,
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

  // Mesure sur l'apercu NON corrige, sinon chaque nouvelle pipette s'appliquerait sur
  // le resultat de la precedente et les gains s'empileraient.
  mesure.value = mesurerPastille(apercu, x, y)
  gains.value = gainsBalanceBlanc(mesure.value)
}

function annuler(): void {
  mesure.value = null
  gains.value = { r: 1, g: 1, b: 1 }
}
</script>

<template>
  <div class="etape">
    <p class="consigne">
      Touche une zone de réserve — du papier laissé nu. C'est ce point qui sera ramené au
      blanc du papier, et toute la photo avec lui.
    </p>

    <canvas ref="toile" class="apercu" @pointerdown="piper" />

    <div v-if="mesure && corrigee" class="pastilles">
      <span class="pastille" :style="{ background: teinte(mesure) }" />
      <span class="fleche">-&gt;</span>
      <span class="pastille" :style="{ background: teinte(corrigee) }" />
      <span class="pastille reference" :style="{ background: teinte(blancPapier) }" />
      <ion-note>mesuré, corrigé, blanc papier</ion-note>
    </div>

    <ion-note v-else class="note">Aucune mesure : l'image partira telle quelle.</ion-note>

    <ion-button expand="block" fill="outline" :disabled="!mesure" @click="annuler">
      Annuler la mesure
    </ion-button>

    <ion-button expand="block" @click="emit('suivant')">Continuer</ion-button>
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

.reference {
  margin-left: 0.75rem;
  border-style: dashed;
}

.note {
  display: block;
  margin: 1rem 0;
}
</style>
