<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonPage,
  IonProgressBar,
  IonTitle,
  IonToolbar,
  useBackButton,
  useIonRouter,
} from '@ionic/vue'
import { arrowBackSharp } from 'ionicons/icons'
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
const ionRouter = useIonRouter()
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
/** Publication mise en file : le flux est clos, revenir en arriere ferait publier deux fois. */
const enFile = ref(false)
/** Publication en cours : Retour attend, sinon l'etape Publier serait demontee en plein envoi. */
const occupe = ref(false)

function suivant(): void {
  etape.value = Math.min(etapes.length - 1, etape.value + 1)
}

/**
 * Retour = etape precedente, le brouillon (photo, coins, gains, reglages) reste dans le
 * magasin. Seule la premiere etape rend la main au carnet.
 */
async function precedent(): Promise<void> {
  if (occupe.value) {
    return
  }

  if (enFile.value) {
    await terminer()
  } else if (etape.value > 0) {
    etape.value--
  } else if (ionRouter.canGoBack()) {
    ionRouter.back()
  } else {
    ionRouter.navigate('/atelier', 'back', 'replace')
  }
}

/*
 * Retour materiel Android : priorite 10, au-dessus du gestionnaire du routeur Ionic (0)
 * qui ferait `history.go(-1)` et quitterait tout le flux, au-dessous des surcouches
 * (menus, alertes : 100) qui doivent se fermer d'abord.
 */
const { unregister } = useBackButton(10, (suivantGestionnaire) => {
  if (router.currentRoute.value.name !== 'capture') {
    suivantGestionnaire()

    return
  }

  return precedent()
})

onUnmounted(unregister)

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
          <ion-button
            :aria-label="etape === 0 || enFile ? 'Retour au carnet' : 'Étape précédente'"
            :disabled="occupe"
            @click="precedent"
          >
            <ion-icon slot="icon-only" :icon="arrowBackSharp" />
          </ion-button>
        </ion-buttons>
        <ion-title>{{ etape + 1 }}/{{ etapes.length }} — {{ courante.titre }}</ion-title>
      </ion-toolbar>
      <ion-progress-bar :value="(etape + 1) / etapes.length" />
    </ion-header>

    <ion-content>
      <component
        :is="courante.composant"
        @suivant="suivant"
        @termine="terminer"
        @en-file="enFile = true"
        @occupe="occupe = $event"
      />
    </ion-content>
  </ion-page>
</template>
