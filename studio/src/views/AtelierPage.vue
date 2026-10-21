<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonBadge,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonItem,
  IonLabel,
  IonList,
  IonListHeader,
  IonNote,
  IonPage,
  IonRefresher,
  IonRefresherContent,
  IonThumbnail,
  IonTitle,
  IonToolbar,
  toastController,
} from '@ionic/vue'
import type { Work } from '@/api/types'
import { accorder, libelle, libellesStatut } from '@/composables/libelles'
import { vignette } from '@/composables/vignette'
import { annulerRetrait, confirmerRetrait, estDeCote, mettreDeCote, reprendre } from '@/services/queue'
import { useAtelier } from '@/stores/atelier'
import { useCapture } from '@/stores/capture'

const router = useRouter()
const {
  oeuvres,
  envois,
  chargement,
  erreur,
  rafraichir,
  relireFile,
  ecoulerFile,
  reprendreSession,
  deconnexion,
} = useAtelier()
const { oublier } = useCapture()

const ecoulement = ref(false)
const vignettes = computed(() => oeuvres.value.map(vignette))

/*
 * `onIonViewWillEnter` ne se declenche pas dans tous les cas en Ionic Vue 8 (piege note
 * dans les regles du projet). Un `onMounted` plus un rafraichissement explicite au
 * retour du flux de capture couvrent les cas qui comptent ici.
 */
// Le reseau qui revient est le seul evenement qui doit relancer la file toute seule.
// Pose au montage, retire au demontage : Sortir puis se reconnecter remonte la page, et un
// ecouteur pose a chaque montage relancerait la file autant de fois.
const surRetourReseau = () => void tenterEnvoi()

onUnmounted(() => window.removeEventListener('online', surRetourReseau))

onMounted(async () => {
  window.addEventListener('online', surRetourReseau)

  await relireFile()

  // Un jeton en stock ne prouve pas qu'il est encore valide : il a pu etre revoque
  // depuis un autre appareil. La seule preuve est une requete qui passe.
  if (!(await reprendreSession())) {
    await router.replace('/connexion')

    return
  }

  await tenterEnvoi()
})

async function tenterEnvoi(): Promise<void> {
  if (envois.value.length === 0) {
    return
  }

  ecoulement.value = true

  try {
    await ecoulerFile()
  } finally {
    ecoulement.value = false
  }
}

/** Le geste de tirer la liste vers le bas doit toujours rendre la main, meme hors ligne. */
async function rafraichirTire(evenement: CustomEvent): Promise<void> {
  try {
    await rafraichir()
    await tenterEnvoi()
  } catch {
    // L'erreur est deja affichee par le magasin.
  } finally {
    await (evenement.target as HTMLIonRefresherElement).complete()
  }
}

async function nouvelle(): Promise<void> {
  oublier()
  await router.push('/capture')
}

/**
 * Retirer un envoi detruit ses photos : le geste se rattrape pendant que le bandeau est
 * affiche. La suppression reelle n'a lieu qu'a sa disparition sans « Annuler ».
 */
async function retirerAnnulable(id: string): Promise<void> {
  // Deux touchers rapides : un seul bandeau, sinon le second confirmerait malgre l'Annuler du premier.
  if (estDeCote(id)) {
    return
  }

  if (!mettreDeCote(id)) {
    const parti = await toastController.create({
      message: 'Cet envoi est en train de partir : il ne peut plus être retiré.',
      duration: 4000,
    })
    await parti.present()

    return
  }

  await relireFile()

  const bandeau = await toastController.create({
    message: 'Envoi retiré',
    duration: 5000,
    buttons: [{ text: 'Annuler', role: 'cancel' }],
  })

  bandeau.onDidDismiss().then(async ({ role }) => {
    if (role === 'cancel') {
      annulerRetrait(id)
      await relireFile()
      // L'envoi rendu attendait peut-etre le reseau, qui est la : on le relance.
      await tenterEnvoi()

      return
    }

    await confirmerRetrait(id)
    await relireFile()
  })

  await bandeau.present()
}

async function sortir(): Promise<void> {
  await deconnexion()
  await router.replace('/connexion')
}

function pigments(index: number): string {
  return oeuvres.value[index].pigments.map((pigment) => pigment.name).join(', ')
}

/** « 1 image envoyée sur 2 » : l'accord suit le nombre d'images deja parties. */
function avancement(fichiers: { envoye: boolean }[]): string {
  const envoyes = fichiers.filter((fichier) => fichier.envoye).length

  return `${envoyes} ${accorder(envoyes, 'image envoyée', 'images envoyées')} sur ${fichiers.length}`
}

function partageable(oeuvre: Work): boolean {
  return oeuvre.media.some((media) => media.role === 'principal')
}
</script>

<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Carnet d'atelier</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="sortir">Sortir</ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content>
      <ion-refresher slot="fixed" @ion-refresh="rafraichirTire">
        <ion-refresher-content />
      </ion-refresher>

      <div class="ion-padding">
        <ion-button expand="block" @click="nouvelle">Photographier une aquarelle</ion-button>
        <ion-note v-if="erreur" color="warning" class="note">
          {{ erreur }} — les envois partiront au retour du réseau.
        </ion-note>
      </div>

      <ion-list v-if="envois.length">
        <ion-list-header>
          <ion-label>En attente d'envoi</ion-label>
          <ion-button :disabled="ecoulement" @click="tenterEnvoi">Envoyer</ion-button>
        </ion-list-header>

        <ion-item v-for="envoi in envois" :key="envoi.id">
          <ion-label>
            <h3>{{ envoi.oeuvre.title }}</h3>
            <p v-if="envoi.erreur" class="refus">{{ envoi.erreur }}</p>
            <p v-else>{{ avancement(envoi.fichiers) }}</p>
          </ion-label>

          <ion-buttons slot="end" class="actions">
            <ion-button v-if="envoi.erreur" @click="reprendre(envoi.id).then(relireFile)">
              Réessayer
            </ion-button>
            <ion-button color="danger" @click="retirerAnnulable(envoi.id)">
              Retirer
            </ion-button>
          </ion-buttons>
        </ion-item>
      </ion-list>

      <ion-list>
        <ion-list-header>
          <ion-label>Œuvres</ion-label>
          <ion-badge class="compteur">{{ oeuvres.length }}</ion-badge>
        </ion-list-header>

        <ion-item v-for="(oeuvre, index) in oeuvres" :key="oeuvre.ulid">
          <!-- Decorative : le titre suit, une alternative le ferait lire deux fois. -->
          <ion-thumbnail
            slot="start"
            class="vignette"
            :style="vignettes[index].fond ? { '--fond': vignettes[index].fond } : undefined"
          >
            <img
              v-if="vignettes[index].src"
              :src="vignettes[index].src!"
              :srcset="vignettes[index].srcset!"
              sizes="56px"
              width="56"
              height="56"
              alt=""
              loading="lazy"
              decoding="async"
            />
          </ion-thumbnail>
          <ion-label>
            <h3>{{ oeuvre.title }}</h3>
            <p>{{ oeuvre.year }} — {{ libelle(libellesStatut, oeuvre.status) }}</p>
            <p v-if="oeuvre.pigments.length" class="pigments">{{ pigments(index) }}</p>
            <p v-else class="pigments">Nuancier en attente du traitement</p>
            <p v-if="oeuvre.socialPosts?.length" class="pigments">
              Partagée {{ oeuvre.socialPosts.length }} fois
            </p>
          </ion-label>

          <!-- Sans image principale, il n'y a rien a composer : le bouton mentirait. -->
          <ion-buttons v-if="partageable(oeuvre)" slot="end">
            <ion-button :router-link="`/partage/${oeuvre.ulid}`">Partager</ion-button>
          </ion-buttons>
        </ion-item>

        <ion-item v-if="!oeuvres.length && !chargement">
          <ion-label>Aucune œuvre pour l'instant.</ion-label>
        </ion-item>
      </ion-list>
    </ion-content>
  </ion-page>
</template>

<style scoped>
.note {
  display: block;
  margin-top: 0.75rem;
}

.refus {
  color: var(--ion-color-danger);
}

/* Couleur de texte secondaire du theme (ion-label p), sans opacite : 4,6:1 en clair, 7,05:1 en sombre. */
.pigments {
  font-size: 0.8rem;
}

/* 8 dp au moins entre deux cibles voisines (Material) : Ionic n'en laisse que 4. */
.actions {
  gap: 8px;
}

/* Le compteur s'aligne sur la marge droite des lignes au lieu de toucher le bord. */
.compteur {
  margin-inline-end: 16px;
}

.vignette {
  --border-radius: 4px;
  background: var(--fond, var(--ion-background-color-step-100));
}
</style>
