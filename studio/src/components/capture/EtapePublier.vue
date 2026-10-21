<script setup lang="ts">
import { ref } from 'vue'
import {
  IonButton,
  IonCheckbox,
  IonInput,
  IonItem,
  IonList,
  IonNote,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonTextarea,
} from '@ionic/vue'
import type { Work } from '@/api/types'
import { libelle, libellesGrain, libellesStatut } from '@/composables/libelles'
import { enfiler } from '@/services/queue'
import { useAtelier } from '@/stores/atelier'
import { useCapture } from '@/stores/capture'

const emit = defineEmits<{ termine: []; enFile: []; occupe: [boolean] }>()

const { brute, metadonnees, exporter } = useCapture()
const { oeuvres, ecoulerFile, rafraichir } = useAtelier()

const travail = ref(false)
const erreur = ref<string | null>(null)
const publiee = ref<Work | null>(null)
const enFile = ref(false)

const grains = ['torchon', 'satine', 'fin']
const statuts = ['atelier', 'exposee', 'vendue', 'privee']

/**
 * Publier = mettre en FILE, jamais appeler l'API directement. Une photo prise dans un
 * atelier sans reseau doit partir quand le reseau revient, sans que l'artiste ait a y
 * repenser.
 */
async function publier(): Promise<void> {
  // Verrou avant tout `await` : un second toucher arrive avant que le bouton desactive
  // soit repeint, et chaque passage ici ENFILE une oeuvre.
  if (travail.value || enFile.value) {
    return
  }

  if (!brute.value) {
    erreur.value = 'Aucune photo'

    return
  }

  travail.value = true
  erreur.value = null
  // Le flux bloque Retour pendant ce temps : demonter cette etape en plein envoi
  // emporterait le verrou, et un second passage enfilerait l'oeuvre deux fois.
  emit('occupe', true)

  try {
    const corrigee = await exporter()

    await enfiler(
      {
        title: metadonnees.title.trim(),
        year: metadonnees.year,
        width_mm: metadonnees.width_mm,
        height_mm: metadonnees.height_mm,
        medium: metadonnees.medium,
        paper_grain: metadonnees.paper_grain,
        status: metadonnees.status,
        notes: metadonnees.notes.trim() || null,
        published_at: metadonnees.publier ? new Date().toISOString() : null,
      },
      [
        // L'image validee par l'artiste est la numerisation de reference.
        { blob: corrigee, role: 'principal', position: 0 },
        // La photo d'appareil part telle quelle : elle permettra de refaire la
        // correction sans rephotographier l'aquarelle. Elle ne sort jamais des routes
        // publiques.
        { blob: brute.value, role: 'brute', position: 1 },
      ],
    )

    enFile.value = true
    emit('enFile')
    await ecoulerFile()
    retrouver()
  } catch (cause) {
    erreur.value = (cause as Error).message
  } finally {
    travail.value = false
    emit('occupe', false)
  }
}

/**
 * Le nuancier n'est pas dans la reponse du televersement : il est calcule par le job de
 * derivation, apres coup. On relit donc l'oeuvre.
 */
async function relire(): Promise<void> {
  travail.value = true

  try {
    await rafraichir()
    retrouver()
  } finally {
    travail.value = false
  }
}

function retrouver(): void {
  publiee.value =
    oeuvres.value.find((oeuvre) => oeuvre.title === metadonnees.title.trim()) ?? null
}
</script>

<template>
  <div class="etape">
    <ion-list v-if="!publiee && !enFile">
      <ion-item>
        <!-- La raison du Publier grisé, sur le champ qui la lève : `helper-text` est relié
             au champ par aria-describedby (ion-input). -->
        <ion-input
          v-model="metadonnees.title"
          label="Titre"
          label-placement="stacked"
          required
          :helper-text="metadonnees.title.trim() ? undefined : 'Obligatoire pour publier.'"
        />
      </ion-item>

      <ion-item>
        <ion-input
          v-model.number="metadonnees.year"
          label="Année"
          label-placement="stacked"
          type="number"
        />
      </ion-item>

      <ion-item>
        <ion-input
          v-model.number="metadonnees.width_mm"
          label="Largeur du papier (mm)"
          label-placement="stacked"
          type="number"
        />
      </ion-item>

      <ion-item>
        <ion-input
          v-model.number="metadonnees.height_mm"
          label="Hauteur du papier (mm)"
          label-placement="stacked"
          type="number"
        />
      </ion-item>

      <ion-item>
        <ion-select v-model="metadonnees.paper_grain" label="Grain" label-placement="stacked">
          <ion-select-option v-for="grain in grains" :key="grain" :value="grain">
            {{ libelle(libellesGrain, grain) }}
          </ion-select-option>
        </ion-select>
      </ion-item>

      <ion-item>
        <ion-select v-model="metadonnees.status" label="Statut" label-placement="stacked">
          <ion-select-option v-for="statut in statuts" :key="statut" :value="statut">
            {{ libelle(libellesStatut, statut) }}
          </ion-select-option>
        </ion-select>
      </ion-item>

      <ion-item>
        <ion-textarea
          v-model="metadonnees.notes"
          label="Note d'atelier"
          label-placement="stacked"
          :rows="3"
        />
      </ion-item>

      <ion-item>
        <ion-checkbox v-model="metadonnees.publier">Visible sur le portfolio</ion-checkbox>
      </ion-item>
    </ion-list>

    <ion-note v-if="erreur" color="danger" class="note">{{ erreur }}</ion-note>

    <template v-if="!publiee">
      <!-- Une fois en file, plus de bouton Publier : l'envoi partira de lui-meme, un
           second toucher enfilerait un doublon. -->
      <template v-if="enFile && !travail">
        <ion-note class="note">
          En file d'attente : l'envoi repartira tout seul au retour du réseau.
        </ion-note>
        <ion-button expand="block" @click="emit('termine')">Retour au carnet</ion-button>
      </template>

      <ion-button
        v-else
        expand="block"
        :disabled="travail || enFile || !metadonnees.title.trim()"
        @click="publier"
      >
        <ion-spinner v-if="travail" name="dots" />
        <span v-else>Publier</span>
      </ion-button>
    </template>

    <div v-else class="nuancier">
      <h2>{{ publiee.title }}</h2>

      <template v-if="publiee.pigments.length">
        <p>Nuancier extrait de l'image :</p>
        <ul>
          <li v-for="pigment in publiee.pigments" :key="pigment.slug">
            <span class="pastille" :style="{ background: pigment.hex }" />
            {{ pigment.name }} — {{ Math.round(pigment.weight * 100) }} %
            <strong v-if="pigment.isDominant">dominant</strong>
          </li>
        </ul>
        <ion-note>
          Un pigment mal reconnu se corrige depuis le carnet : c'est l'œil qui tranche,
          pas le calcul.
        </ion-note>
      </template>

      <ion-note v-else class="note">
        Nuancier pas encore calculé — le traitement de l'image tourne en file côté serveur.
      </ion-note>

      <ion-button expand="block" fill="outline" :disabled="travail" @click="relire">
        Relire le nuancier
      </ion-button>

      <ion-button expand="block" @click="emit('termine')">Retour au carnet</ion-button>
    </div>
  </div>
</template>

<style scoped>
.etape {
  padding: 1rem;
}

.note {
  display: block;
  margin: 1rem 0;
}

.nuancier ul {
  list-style: none;
  padding: 0;
}

.nuancier li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.pastille {
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 50%;
  border: 1px solid var(--ion-color-medium);
}
</style>
