<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonFooter,
  IonHeader,
  IonItem,
  IonLabel,
  IonList,
  IonNote,
  IonPage,
  IonSegment,
  IonSegmentButton,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonTextarea,
  IonTitle,
  IonToolbar,
  toastController,
} from '@ionic/vue'
import type { SocialNetwork, Work } from '@/api/types'
import { libelle, libellesReseau } from '@/composables/libelles'
import { peindreVisuel, type FormatPartage } from '@/composables/useVisuelPartage'
import { api } from '@/services/api'
import { enfilerTrace } from '@/services/filePartages'
import { partagerVisuel } from '@/services/partage'
import { useAtelier } from '@/stores/atelier'

const props = defineProps<{ ulid: string }>()

const { oeuvres, rafraichir, ecoulerFile } = useAtelier()

const toile = ref<HTMLCanvasElement | null>(null)
const format = ref<FormatPartage>('portrait')
const reseau = ref<SocialNetwork>('instagram')
const legende = ref('')
const travail = ref(false)
const erreur = ref<string | null>(null)

let image: ImageBitmap | null = null

const oeuvre = computed<Work | null>(
  () => oeuvres.value.find((candidate) => candidate.ulid === props.ulid) ?? null,
)

const reseaux: SocialNetwork[] = ['instagram', 'facebook', 'whatsapp', 'autre']

const partages = computed(() => oeuvre.value?.socialPosts ?? [])

onMounted(async () => {
  if (!oeuvre.value) {
    await rafraichir().catch(() => undefined)
  }

  if (!oeuvre.value) {
    erreur.value = 'Œuvre introuvable'

    return
  }

  legende.value = `${oeuvre.value.title}, ${oeuvre.value.year}. Aquarelle.`

  try {
    image = await createImageBitmap(await api.apercu(props.ulid))
    peindre()
  } catch (cause) {
    erreur.value = (cause as Error).message
  }
})

watch(format, peindre)

function peindre(): void {
  if (!image || !toile.value || !oeuvre.value) {
    return
  }

  peindreVisuel(
    {
      image,
      imageLargeur: image.width,
      imageHauteur: image.height,
      titre: oeuvre.value.title,
      annee: oeuvre.value.year,
      format: format.value,
    },
    toile.value,
  )
}

/**
 * Retour d'etat en toast, pose au-dessus du pied ou sont les boutons : en note sous ceux-ci,
 * il tombait hors de l'ecran, sous la liste des partages.
 */
async function annoncer(texte: string, duree = 3000): Promise<void> {
  // Un seul message a la fois : deux toasts au meme ancrage se recouvriraient.
  await toastController.dismiss().catch(() => undefined)
  const toast = await toastController.create({
    message: texte,
    duration: duree,
    positionAnchor: 'pied-partage',
  })
  await toast.present()
}

function versBlob(): Promise<Blob> {
  return new Promise((resoudre, rejeter) => {
    toile.value?.toBlob(
      (blob) => (blob ? resoudre(blob) : rejeter(new Error('Export JPEG impossible'))),
      'image/jpeg',
      0.92,
    )
  })
}

/**
 * Partager PUIS tracer, jamais l'inverse : une trace posee avant l'ouverture de la
 * feuille mentirait des que l'artiste la referme sans rien publier.
 *
 * La trace passe par la file persistee, comme toute ecriture : un partage se fait
 * souvent hors ligne.
 */
async function partager(): Promise<void> {
  if (!oeuvre.value) {
    return
  }

  travail.value = true
  erreur.value = null

  try {
    const visuel = await versBlob()
    const resultat = await partagerVisuel(visuel, oeuvre.value.title, legende.value)

    // Feuille refermée sans rien choisir : rien n'a été partagé, rien à dire ni à tracer.
    if (resultat === 'annule') {
      return
    }

    if (resultat === 'indisponible') {
      // Une consigne en deux phrases : le temps de la lire.
      await annoncer(
        "La feuille de partage n'est pas disponible ici. Le visuel reste affiché : enregistre-le, puis note le partage.",
        8000,
      )

      return
    }

    await enfilerTrace(props.ulid, reseau.value, legende.value.trim() || null)
    await ecoulerFile()
    await rafraichir().catch(() => undefined)

    await annoncer('Partage tracé.')
  } catch (cause) {
    erreur.value = (cause as Error).message
  } finally {
    travail.value = false
  }
}

/** Trace un partage fait a la main, quand la feuille native n'est pas disponible. */
async function tracerSeulement(): Promise<void> {
  travail.value = true

  try {
    await enfilerTrace(props.ulid, reseau.value, legende.value.trim() || null)
    await ecoulerFile()
    await rafraichir().catch(() => undefined)
    await annoncer('Partage tracé.')
  } finally {
    travail.value = false
  }
}

const dateCourte = (iso: string) => new Date(iso).toLocaleDateString('fr-FR')
</script>

<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/atelier" />
        </ion-buttons>
        <ion-title>Partager</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <ion-note v-if="erreur" color="danger" class="note">{{ erreur }}</ion-note>

      <template v-if="oeuvre">
        <ion-segment :value="format" @ion-change="format = ($event as CustomEvent).detail.value">
          <ion-segment-button value="portrait">4:5</ion-segment-button>
          <ion-segment-button value="carre">Carré</ion-segment-button>
        </ion-segment>

        <canvas ref="toile" class="visuel" />

        <ion-list>
          <ion-item>
            <ion-textarea
              v-model="legende"
              label="Légende"
              label-placement="stacked"
              :rows="3"
              :counter="true"
              :maxlength="2200"
            />
          </ion-item>

          <ion-item>
            <ion-select v-model="reseau" label="Déposé sur" label-placement="stacked">
              <ion-select-option v-for="candidat in reseaux" :key="candidat" :value="candidat">
                {{ libelle(libellesReseau, candidat) }}
              </ion-select-option>
            </ion-select>
          </ion-item>
        </ion-list>

        <ion-note class="note">
          La feuille de partage ne dit pas où tu as déposé l'image : c'est cette liste qui
          fait foi dans l'historique.
        </ion-note>

        <ion-list v-if="partages.length">
          <ion-item v-for="(partage, index) in partages" :key="index">
            <ion-label>
              <h3>{{ libelle(libellesReseau, partage.network) }}</h3>
              <p>{{ dateCourte(partage.postedAt) }}</p>
            </ion-label>
          </ion-item>
        </ion-list>

        <ion-note v-else class="note">Jamais partagée.</ion-note>
      </template>
    </ion-content>

    <!-- Les deux actions restent a portee de pouce, quelle que soit la hauteur du visuel. -->
    <ion-footer v-if="oeuvre" id="pied-partage">
      <ion-toolbar class="actions">
        <ion-button expand="block" :disabled="travail" @click="partager">
          <ion-spinner v-if="travail" name="dots" />
          <span v-else>Ouvrir la feuille de partage</span>
        </ion-button>

        <ion-button expand="block" fill="outline" :disabled="travail" @click="tracerSeulement">
          Noter un partage déjà fait
        </ion-button>
      </ion-toolbar>
    </ion-footer>
  </ion-page>
</template>

<style scoped>
.visuel {
  display: block;
  width: 100%;
  height: auto;
  margin: 1rem 0;
  box-shadow: 0 1px 6px rgb(0 0 0 / 0.15);
}

.note {
  display: block;
  margin: 1rem 0;
}

.actions {
  --padding-start: 16px;
  --padding-end: 16px;
  --padding-top: 8px;
  --padding-bottom: 8px;
}

/* 8 dp au moins entre les deux cibles (Material) : Ionic n'en laisse que 4. */
.actions ion-button + ion-button {
  margin-top: 8px;
}
</style>
