<script setup lang="ts">
import { ref } from 'vue'
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera'
import { IonButton, IonNote, IonSpinner } from '@ionic/vue'
import { decoder } from '@/composables/useToile'
import { useCapture } from '@/stores/capture'

const emit = defineEmits<{ suivant: [] }>()

const { brute, poserPhoto } = useCapture()

const travail = ref(false)
const erreur = ref<string | null>(null)
const entree = ref<HTMLInputElement | null>(null)
/** Galerie du greffon en echec : le selecteur de fichiers est propose, jamais ouvert d'office. */
const galerieEchouee = ref<string | null>(null)

/**
 * Message de rejet de `@capacitor/camera` 8 quand l'artiste ferme l'appareil ou la
 * galerie sans rien choisir (LegacyCameraFlow.java `USER_CANCELLED`, et web.js sur
 * l'evenement `cancel` du selecteur). Ce n'est pas une panne : on ne dit rien.
 */
const ANNULATION = 'User cancelled photos app'

async function prendre(source: CameraSource): Promise<void> {
  erreur.value = null
  galerieEchouee.value = null

  try {
    const photo = await Camera.getPhoto({
      resultType: CameraResultType.Uri,
      source,
      quality: 92,
      // Pas de recadrage par l'appareil : le redressement se fait a l'ecran suivant, sur
      // les quatre coins de la feuille, et un rognage prealable jetterait ces coins.
      allowEditing: false,
    })

    if (!photo.webPath) {
      throw new Error("L'appareil n'a rien rendu")
    }

    await charger(await (await fetch(photo.webPath)).blob())
  } catch (cause) {
    const message = (cause as Error).message

    if (message === ANNULATION) {
      return
    }

    if (source === CameraSource.Photos) {
      // Ouvrir le selecteur ici, sans geste de l'artiste, serait une surprise (et le
      // navigateur peut le bloquer) : on dit ce qui s'est passe et on propose le fichier.
      galerieEchouee.value = message

      return
    }

    // Appareil sans camera, ou camera refusee : l'artiste passe par une photo existante,
    // que le message lui indique.
    erreur.value = message
  }
}

async function depuisFichier(evenement: Event): Promise<void> {
  const fichier = (evenement.target as HTMLInputElement).files?.[0]

  if (fichier) {
    erreur.value = null
    await charger(fichier)
  }
}

async function charger(blob: Blob): Promise<void> {
  travail.value = true

  try {
    poserPhoto(blob, await decoder(blob))
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
      Pose l'aquarelle a plat, lumiere du jour de preference, et cadre large : les quatre
      coins de la feuille doivent etre dans l'image.
    </p>

    <ion-button expand="block" :disabled="travail" @click="prendre(CameraSource.Camera)">
      <ion-spinner v-if="travail" name="dots" />
      <span v-else>Photographier</span>
    </ion-button>

    <ion-button
      expand="block"
      fill="outline"
      :disabled="travail"
      @click="prendre(CameraSource.Photos)"
    >
      Choisir une photo existante
    </ion-button>

    <input
      ref="entree"
      class="fichier"
      type="file"
      accept="image/*"
      @change="depuisFichier"
    />

    <template v-if="galerieEchouee">
      <ion-note color="warning" class="note">
        La galerie n'a pas pu s'ouvrir ({{ galerieEchouee }}).
      </ion-note>
      <ion-button expand="block" fill="clear" :disabled="travail" @click="entree?.click()">
        Choisir un fichier à la place
      </ion-button>
    </template>

    <ion-note v-if="erreur" color="warning" class="note">
      Appareil indisponible ({{ erreur }}) — passe par une photo existante.
    </ion-note>

    <!-- Revenir ici par Retour ne doit pas obliger a rephotographier : coins et reglages
         du brouillon restent en place tant qu'une nouvelle photo ne les remplace pas. -->
    <template v-if="brute">
      <ion-note class="note">Photo chargee.</ion-note>
      <ion-button expand="block" fill="clear" :disabled="travail" @click="emit('suivant')">
        Continuer avec cette photo
      </ion-button>
    </template>
  </div>
</template>

<style scoped>
.etape {
  padding: 1rem;
}

.consigne {
  margin-bottom: 1.5rem;
}

.fichier {
  display: none;
}

.note {
  display: block;
  margin-top: 1rem;
}
</style>
