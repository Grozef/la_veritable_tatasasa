<script setup lang="ts">
import { onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import CarnetGrid from '@/components/CarnetGrid.vue'
import { useWorksStore } from '@/stores/works'

const store = useWorksStore()
const { filtrees, selection, chargement, erreur, pigments } = storeToRefs(store)

onMounted(() => store.charger())

/** Les pastilles actives, en toutes lettres : le filtre doit se lire, pas se deviner. */
const nomsSelectionnes = () =>
  selection.value
    .map((slug) => pigments.value.find((pigment) => pigment.slug === slug)?.name ?? slug)
    .join(' et ')
</script>

<template>
  <section>
    <p v-if="chargement" class="etat">Ouverture du carnet…</p>

    <p v-else-if="erreur" class="etat" role="alert">
      Le carnet n'a pas pu s'ouvrir : {{ erreur }}
      <button type="button" @click="store.charger()">Reessayer</button>
    </p>

    <template v-else>
      <p v-if="selection.length > 0" class="etat" aria-live="polite">
        {{ filtrees.length }} oeuvre{{ filtrees.length > 1 ? 's' : '' }} en {{ nomsSelectionnes() }}
      </p>

      <p v-if="filtrees.length === 0" class="etat">
        Aucune oeuvre ne croise ces pigments. Le nuancier filtre en intersection.
      </p>

      <CarnetGrid v-else :works="filtrees" />
    </template>
  </section>
</template>

<style scoped>
.etat {
  padding: 1.5rem var(--marge) 0;
  color: var(--encre-pale);
  font-size: 0.95rem;
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
