<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { IonButton, IonItem, IonLabel, IonList, IonNote, IonRange } from '@ionic/vue'
import { reduire, versToile } from '@/composables/useToile'
import { creerRendu, REGLAGES_NEUTRES, type Rendu } from '@/services/filtres'
import { useCapture } from '@/stores/capture'

const emit = defineEmits<{ suivant: [] }>()

const { reglages, corrigee } = useCapture()

const toile = ref<HTMLCanvasElement | null>(null)
const erreur = ref<string | null>(null)

let rendu: Rendu | null = null
let sourceApercu: HTMLCanvasElement | null = null

/** Les quatre reglages du plan, avec leurs bornes. */
const curseurs = [
  { cle: 'exposition' as const, nom: 'Exposition', min: -2, max: 2, pas: 0.05 },
  { cle: 'contraste' as const, nom: 'Contraste', min: -0.5, max: 0.5, pas: 0.01 },
  { cle: 'saturation' as const, nom: 'Saturation', min: -1, max: 1, pas: 0.01 },
  { cle: 'temperature' as const, nom: 'Temperature', min: -1, max: 1, pas: 0.01 },
]

onMounted(() => {
  try {
    // L'apercu travaille sur une reduction : le GPU refait la passe entiere a chaque
    // mouvement de curseur, inutile de lui donner six megapixels pour un ecran de
    // telephone.
    sourceApercu = versToile(reduire(corrigee(), 1000))

    if (!toile.value) {
      return
    }

    toile.value.width = sourceApercu.width
    toile.value.height = sourceApercu.height
    rendu = creerRendu(toile.value)
    peindre()
  } catch (cause) {
    erreur.value = (cause as Error).message
  }
})

onBeforeUnmount(() => rendu?.detruire())

watch(reglages, peindre, { deep: true })

function peindre(): void {
  if (rendu && sourceApercu) {
    rendu.dessiner(sourceApercu, reglages)
  }
}

function neutraliser(): void {
  Object.assign(reglages, REGLAGES_NEUTRES)
}
</script>

<template>
  <div class="etape">
    <canvas v-show="!erreur" ref="toile" class="apercu" />

    <ion-note v-if="erreur" color="warning" class="note">
      Apercu impossible ({{ erreur }}) — l'image partira sans filtre.
    </ion-note>

    <ion-list>
      <ion-item v-for="curseur in curseurs" :key="curseur.cle">
        <ion-label position="stacked">
          {{ curseur.nom }} — {{ reglages[curseur.cle].toFixed(2) }}
        </ion-label>
        <ion-range
          :min="curseur.min"
          :max="curseur.max"
          :step="curseur.pas"
          :value="reglages[curseur.cle]"
          :disabled="Boolean(erreur)"
          @ion-input="reglages[curseur.cle] = Number(($event as CustomEvent).detail.value)"
        />
      </ion-item>
    </ion-list>

    <ion-note class="note">
      Les reglages sont ranges a cote de l'image : la photo brute part intacte et reste
      corrigeable.
    </ion-note>

    <ion-button expand="block" fill="outline" @click="neutraliser">Tout remettre a plat</ion-button>
    <ion-button expand="block" @click="emit('suivant')">Continuer</ion-button>
  </div>
</template>

<style scoped>
.etape {
  padding: 1rem;
}

.apercu {
  display: block;
  width: 100%;
  height: auto;
}

.note {
  display: block;
  margin: 1rem 0;
}
</style>
