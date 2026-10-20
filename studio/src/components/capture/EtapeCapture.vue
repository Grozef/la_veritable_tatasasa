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

async function photographier(): Promise<void> {
  erreur.value = null

  try {
    const photo = await Camera.getPhoto({
      resultType: CameraResultType.Uri,
      source: CameraSource.Camera,
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
    /*
     * Le greffon camera n'a pas d'implementation web sans `@ionic/pwa-elements`, absent
     * du projet : dans un navigateur cet appel echoue toujours. Le selecteur de fichier
     * n'est donc pas un confort, c'est le seul chemin praticable hors appareil.
     */
    erreur.value = (cause as Error).message
    entree.value?.click()
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

    <ion-button expand="block" :disabled="travail" @click="photographier">
      <ion-spinner v-if="travail" name="dots" />
      <span v-else>Photographier</span>
    </ion-button>

    <ion-button expand="block" fill="outline" :disabled="travail" @click="entree?.click()">
      Choisir une photo existante
    </ion-button>

    <input
      ref="entree"
      class="fichier"
      type="file"
      accept="image/*"
      capture="environment"
      @change="depuisFichier"
    />

    <ion-note v-if="erreur" color="warning" class="note">
      Appareil indisponible ({{ erreur }}) — passe par une photo existante.
    </ion-note>

    <ion-note v-if="brute" class="note">Photo chargee.</ion-note>
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
