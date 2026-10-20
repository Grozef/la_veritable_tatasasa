<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonBackButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonPage,
  IonProgressBar,
  IonTitle,
  IonToolbar,
} from '@ionic/vue'
import EtapeBalanceBlanc from '@/components/capture/EtapeBalanceBlanc.vue'
import EtapeCapture from '@/components/capture/EtapeCapture.vue'
import EtapeFiltres from '@/components/capture/EtapeFiltres.vue'
import EtapePublier from '@/components/capture/EtapePublier.vue'
import EtapeRedresser from '@/components/capture/EtapeRedresser.vue'
import { useCapture } from '@/stores/capture'

/**
 * Le flux de capture, en cinq ecrans et un seul sens de lecture.
 *
 * L'ordre n'est pas negociable : redresser avant de mesurer un blanc (la perspective
 * deplace la zone de reserve), corriger le blanc avant de filtrer (monter le contraste
 * sur une dominante jaune ne fait qu'accentuer le jaune).
 */
const router = useRouter()
const { oublier } = useCapture()

const etapes = [
  { titre: 'Photographier', composant: EtapeCapture },
  { titre: 'Redresser', composant: EtapeRedresser },
  { titre: 'Balance des blancs', composant: EtapeBalanceBlanc },
  { titre: 'Filtres', composant: EtapeFiltres },
  { titre: 'Publier', composant: EtapePublier },
]

const etape = ref(0)
const courante = computed(() => etapes[etape.value])

function suivant(): void {
  etape.value = Math.min(etapes.length - 1, etape.value + 1)
}

async function terminer(): Promise<void> {
  oublier()
  await router.replace('/atelier')
}
</script>

<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/atelier" />
        </ion-buttons>
        <ion-title>{{ etape + 1 }}/{{ etapes.length }} — {{ courante.titre }}</ion-title>
      </ion-toolbar>
      <ion-progress-bar :value="(etape + 1) / etapes.length" />
    </ion-header>

    <ion-content>
      <component :is="courante.composant" @suivant="suivant" @termine="terminer" />
    </ion-content>
  </ion-page>
</template>
