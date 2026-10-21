<script setup lang="ts">
import { computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import CarnetGrid from '@/components/CarnetGrid.vue'
import { useWorksStore } from '@/stores/works'
import { useTitre } from '@/composables/titre'

const props = defineProps<{ slug: string }>()

const store = useWorksStore()
const { filtrees, pigments, chargement } = storeToRefs(store)

/*
 * La vue pigment n'est pas un filtre a part : elle POSE la selection du nuancier.
 * Le visiteur qui arrive par un lien profond retrouve la bande dans le meme etat que
 * s'il avait clique la pastille.
 */
watch(
  () => props.slug,
  async (slug) => {
    await store.charger()
    store.selectionner([slug])
  },
  { immediate: true },
)

const pigment = computed(() => pigments.value.find((p) => p.slug === props.slug) ?? null)

useTitre(() => pigment.value?.name)
</script>

<template>
  <section>
    <!--
      Tant que le nuancier n'est pas charge, l'absence de pigment n'est pas une
      information : l'annoncer afficherait un dementi faux a tout visiteur lent.
    -->
    <p v-if="chargement" class="etat">Ouverture du nuancier…</p>

    <header v-else-if="pigment">
      <span class="pastille" :style="{ backgroundColor: pigment.hex }" aria-hidden="true" />
      <h1>{{ pigment.name }}</h1>
      <p v-if="pigment.brand">{{ pigment.brand }}</p>
      <p class="compte">
        {{ filtrees.length }} oeuvre{{ filtrees.length > 1 ? 's' : '' }} au carnet
      </p>
    </header>

    <p v-else class="etat">Ce pigment n'est porte par aucune oeuvre publiee.</p>

    <CarnetGrid :works="filtrees" />
  </section>
</template>

<style scoped>
header {
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 0.25rem 1rem;
  padding: var(--marge) var(--marge) 0;
}

h1 {
  margin: 0;
  font-size: clamp(1.6rem, 1.2rem + 1.5vw, 2.4rem);
}

.pastille {
  grid-row: span 2;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.18);
}

p {
  margin: 0;
  color: var(--encre-pale);
  font-size: 0.9rem;
}

.compte {
  grid-column: 2;
}

.etat {
  padding: var(--marge);
}
</style>
