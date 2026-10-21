<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { IonButton, IonItem, IonLabel, IonList, IonNote, IonRange } from '@ionic/vue'
import { valeurReglage } from '@/composables/libelles'
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
  { cle: 'temperature' as const, nom: 'Température', min: -1, max: 1, pas: 0.01 },
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

    <ion-note v-if="erreur" color="warning" class="note erreur">
      Aperçu impossible ({{ erreur }}) — l'image partira sans filtre.
    </ion-note>

    <ion-list class="reglages">
      <ion-item v-for="curseur in curseurs" :key="curseur.cle">
        <ion-label position="stacked">
          {{ curseur.nom }} — {{ valeurReglage(reglages[curseur.cle]) }}
        </ion-label>
        <!-- L'ion-label voisin ne nomme pas le curseur : sans aria-label, le lecteur
             d'ecran annonce un curseur anonyme. -->
        <ion-range
          :aria-label="curseur.nom"
          :min="curseur.min"
          :max="curseur.max"
          :step="curseur.pas"
          :value="reglages[curseur.cle]"
          :disabled="Boolean(erreur)"
          @ion-input="reglages[curseur.cle] = Number(($event as CustomEvent).detail.value)"
        />
      </ion-item>
    </ion-list>

    <ion-note class="note info">
      Les réglages sont rangés à côté de l'image : la photo brute part intacte et reste
      corrigeable.
    </ion-note>

    <div class="actions">
      <ion-button expand="block" fill="outline" @click="neutraliser">Tout remettre à plat</ion-button>
      <ion-button expand="block" @click="emit('suivant')">Continuer</ion-button>
    </div>
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

/*
 * En paysage, l'image reste sous les yeux pendant le reglage : apercu a gauche, borne a la
 * hauteur de l'ecran, curseurs a droite, actions cote a cote sous eux.
 */
@media (orientation: landscape) {
  .etape {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(18rem, 26rem);
    grid-template-rows: minmax(0, 1fr) auto;
    grid-template-areas:
      'apercu reglages'
      'info actions';
    column-gap: 1rem;
    box-sizing: border-box;
    height: 100%;
    padding: 0.5rem calc(1rem + var(--ion-safe-area-right, 0px)) calc(0.5rem + var(--ion-safe-area-bottom, 0px))
      calc(1rem + var(--ion-safe-area-left, 0px));
  }

  .apercu {
    grid-area: apercu;
    place-self: center;
    width: auto;
    max-width: 100%;
    max-height: 100%;
  }

  .erreur {
    grid-area: apercu;
    align-self: start;
  }

  .reglages {
    grid-area: reglages;
    align-self: start;
    min-height: 0;
    max-height: 100%;
    overflow-y: auto;
    padding-block: 0;
  }

  .info {
    grid-area: info;
    align-self: center;
    margin: 0;
  }

  .actions {
    grid-area: actions;
    display: flex;
    gap: 8px;
  }

  /* Largeur au libelle : « Tout remettre a plat » tient sur une ligne, Continuer prend le reste. */
  .actions ion-button {
    flex: auto;
    margin: 0;
  }
}
</style>
