<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonButton,
  IonContent,
  IonHeader,
  IonInput,
  IonItem,
  IonList,
  IonNote,
  IonPage,
  IonSpinner,
  IonTitle,
  IonToolbar,
} from '@ionic/vue'
import { ErreurApi } from '@/services/api'
import { useAtelier } from '@/stores/atelier'

const router = useRouter()
const { connexion } = useAtelier()

const email = ref('')
const motDePasse = ref('')
const erreur = ref<string | null>(null)
const envoi = ref(false)

/**
 * Le jeton porte le nom de l'appareil : c'est ce qui permet de revoquer un telephone
 * perdu sans deconnecter les autres. Le modele n'est pas lisible depuis une WebView,
 * donc on prend ce que la plateforme veut bien dire.
 */
const appareil = navigator.userAgent.slice(0, 60)

async function valider(): Promise<void> {
  envoi.value = true
  erreur.value = null

  try {
    await connexion(email.value.trim(), motDePasse.value, appareil)
    await router.replace('/atelier')
  } catch (cause) {
    erreur.value =
      cause instanceof ErreurApi && cause.horsLigne
        ? "L'atelier est injoignable. Vérifie le réseau."
        : (cause as Error).message
  } finally {
    envoi.value = false
  }
}
</script>

<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Atelier</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <form @submit.prevent="valider">
        <ion-list>
          <ion-item>
            <ion-input
              v-model="email"
              label="Adresse"
              label-placement="stacked"
              type="email"
              autocomplete="username"
              required
            />
          </ion-item>

          <ion-item>
            <ion-input
              v-model="motDePasse"
              label="Mot de passe"
              label-placement="stacked"
              type="password"
              autocomplete="current-password"
              required
            />
          </ion-item>
        </ion-list>

        <ion-note v-if="erreur" color="danger" class="erreur" role="alert">{{ erreur }}</ion-note>

        <!-- `aria-busy` pose apres coup sur ion-button reste sur l'hote : Ionic 8 ne le
             recopie sur le bouton natif qu'au premier rendu. La region annonce l'attente. -->
        <p class="visuellement-cache" role="status">{{ envoi ? 'Connexion en cours…' : '' }}</p>

        <ion-button
          type="submit"
          expand="block"
          :disabled="envoi"
        >
          <ion-spinner v-if="envoi" name="dots" aria-hidden="true" />
          <span :class="{ 'visuellement-cache': envoi }">Entrer dans l'atelier</span>
        </ion-button>
      </form>
    </ion-content>
  </ion-page>
</template>

<style scoped>
.erreur {
  display: block;
  margin: 1rem 0;
}
</style>
