<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import CarnetGrid from '@/components/CarnetGrid.vue'
import { useWorksStore } from '@/stores/works'

const store = useWorksStore()
const { filtrees, selection, chargement, erreur, pigments } = storeToRefs(store)

onMounted(() => store.charger())

/*
 * Arriver au carnet par un filtre (pastille cliquee depuis une fiche ou une serie) le
 * monte avec un compte deja calcule. Pose dans le meme rendu que la region, ce premier
 * texte ne serait pas annonce : la region doit d'abord exister vide. Un `nextTick` ne
 * suffit pas, il tombe avant que l'arbre d'accessibilite n'ait vu la region ; on attend
 * un court delai.
 */
const regionPrete = ref(false)
let attente: ReturnType<typeof setTimeout> | undefined

onMounted(() => {
  attente = setTimeout(() => (regionPrete.value = true), 150)
})

onBeforeUnmount(() => clearTimeout(attente))

/** Les pastilles actives, en toutes lettres : le filtre doit se lire, pas se deviner. */
const nomsSelectionnes = () =>
  selection.value
    .map((slug) => pigments.value.find((pigment) => pigment.slug === slug)?.name ?? slug)
    .join(' et ')
</script>

<template>
  <section>
    <h1 class="lecteur-seul">Carnet d'aquarelles</h1>

    <!-- Region live presente des le premier rendu : une region creee avec son texte
         n'est pas annoncee. Seul son contenu change au fil des filtres. -->
    <p class="etat annonce" aria-live="polite"><template v-if="regionPrete && !chargement && !erreur && selection.length > 0">{{ filtrees.length }} œuvre{{ filtrees.length > 1 ? 's' : '' }} en {{ nomsSelectionnes() }}</template></p>

    <p v-if="chargement" class="etat">Ouverture du carnet…</p>

    <p v-else-if="erreur" class="etat" role="alert">
      Le carnet n'a pas pu s'ouvrir.
      <button type="button" @click="store.charger()">Réessayer</button>
    </p>

    <template v-else>
      <!-- Sans pigment choisi, un carnet vide n'a rien a retirer : la consigne serait impossible. -->
      <p v-if="filtrees.length === 0 && selection.length === 0" class="etat">
        Le carnet est encore vide.
      </p>

      <p v-else-if="filtrees.length === 0" class="etat">
        Aucune œuvre ne porte tous ces pigments à la fois. Retirez-en un, ou revenez à tout
        le carnet.
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

/* Vide, la region reste dans le DOM sans occuper de place. */
.annonce:empty {
  padding: 0;
  margin: 0;
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
