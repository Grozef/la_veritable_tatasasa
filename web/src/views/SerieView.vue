<script setup lang="ts">
import { ref, watch } from 'vue'
import CarnetGrid from '@/components/CarnetGrid.vue'
import { api, ErreurApi } from '@/api/client'
import type { Series } from '@/api/types'
import { useTitre } from '@/composables/titre'

const props = defineProps<{ slug: string }>()

/*
 * Seule vue qui va chercher sa donnee hors du store : une serie porte ses propres
 * oeuvres, dans son propre ordre, et le carnet ne les connait pas groupees.
 */
const serie = ref<Series | null>(null)
/** `introuvable` : l'API a rendu 404. `indisponible` : toute autre panne, a reessayer. */
const echec = ref<'introuvable' | 'indisponible' | null>(null)

async function charger(slug: string): Promise<void> {
  // Remise a zero : passer de A a B ne doit laisser ni la grille ni le titre de A.
  serie.value = null
  echec.value = null

  try {
    const recue = await api.series(slug)

    // Une reponse arrivee apres un changement de serie ne s'affiche pas.
    if (slug === props.slug) {
      serie.value = recue
    }
  } catch (cause) {
    if (slug === props.slug) {
      echec.value = cause instanceof ErreurApi && cause.statut === 404 ? 'introuvable' : 'indisponible'
    }
  }
}

watch(() => props.slug, charger, { immediate: true })

useTitre(
  () =>
    serie.value?.title ??
    (echec.value === 'introuvable' ? 'Série introuvable' : echec.value ? 'Série indisponible' : null),
)
</script>

<template>
  <section v-if="serie">
    <header>
      <h1>{{ serie.title }}</h1>
      <p class="annee">{{ serie.year }}</p>
      <p v-if="serie.notes" class="note">{{ serie.notes }}</p>
    </header>

    <CarnetGrid :works="serie.works ?? []" />
  </section>

  <div v-else-if="echec === 'introuvable'" class="etat">
    <h1 class="lecteur-seul">Série introuvable</h1>
    <p>
      Cette série n'est pas au carnet. <RouterLink :to="{ name: 'carnet' }">Y revenir</RouterLink>
    </p>
  </div>

  <div v-else-if="echec" class="etat">
    <h1 class="lecteur-seul">Série indisponible</h1>
    <p role="alert">
      La série n'a pas pu s'ouvrir.
      <button type="button" @click="charger(slug)">Réessayer</button>
    </p>
  </div>

  <p v-else class="etat">Ouverture de la série…</p>
</template>

<style scoped>
header {
  padding: var(--marge) var(--marge) 0;
  max-width: 42rem;
}

h1 {
  font-size: clamp(1.6rem, 1.2rem + 1.5vw, 2.4rem);
}

.annee {
  margin: 0;
  color: var(--encre-pale);
  font-variant-numeric: oldstyle-nums;
}

.note {
  margin-top: 1rem;
  font-family: var(--manuscrite);
  color: var(--encre-pale);
}

.etat {
  padding: var(--marge);
  color: var(--encre-pale);
}

button {
  margin-left: 0.5rem;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-decoration: underline;
  cursor: pointer;
}
</style>
