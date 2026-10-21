<script setup lang="ts">
import { ref, watch } from 'vue'
import CarnetGrid from '@/components/CarnetGrid.vue'
import { api } from '@/api/client'
import type { Series } from '@/api/types'
import { useTitre } from '@/composables/titre'

const props = defineProps<{ slug: string }>()

/*
 * Seule vue qui va chercher sa donnee hors du store : une serie porte ses propres
 * oeuvres, dans son propre ordre, et le carnet ne les connait pas groupees.
 */
const serie = ref<Series | null>(null)
const erreur = ref<string | null>(null)

watch(
  () => props.slug,
  async (slug) => {
    erreur.value = null

    try {
      serie.value = await api.series(slug)
    } catch (cause) {
      erreur.value = cause instanceof Error ? cause.message : 'Serie introuvable'
    }
  },
  { immediate: true },
)

useTitre(() => serie.value?.title ?? (erreur.value ? 'Série introuvable' : null))
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

  <p v-else-if="erreur" class="etat" role="alert">Cette serie n'est pas au carnet.</p>

  <p v-else class="etat">Ouverture de la serie…</p>
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
</style>
