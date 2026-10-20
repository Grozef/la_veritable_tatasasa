<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { useWorksStore } from '@/stores/works'

/**
 * La navigation du site. Pas un menu : une bande de pigments reels.
 *
 * Chaque pastille porte son NOM en texte et son `aria-pressed`. Une pastille purement
 * chromatique serait inutilisable au clavier, au lecteur d'ecran et pour un daltonien —
 * or c'est ici que se fait toute la navigation.
 */
const store = useWorksStore()
const { pigments, selection } = storeToRefs(store)

const router = useRouter()
const route = useRoute()

/**
 * La bande est presente partout. Filtrer depuis une fiche oeuvre ou une serie doit
 * ramener au carnet, sinon la selection s'appliquerait a une vue qui ne la montre pas.
 */
function filtrer(slug: string): void {
  store.basculer(slug)

  if (route.name !== 'carnet') {
    router.push({ name: 'carnet' })
  }
}
</script>

<template>
  <nav class="nuancier" aria-label="Filtrer par pigment">
    <ul>
      <li v-for="pigment in pigments" :key="pigment.slug">
        <button
          type="button"
          :aria-pressed="store.estSelectionne(pigment.slug)"
          @click="filtrer(pigment.slug)"
        >
          <span class="pastille" :style="{ backgroundColor: pigment.hex }" aria-hidden="true" />
          <span class="nom">{{ pigment.name }}</span>
        </button>
      </li>
    </ul>

    <button v-if="selection.length > 0" type="button" class="effacer" @click="store.selectionner([])">
      Tout le carnet
    </button>
  </nav>
</template>

<style scoped>
.nuancier {
  position: sticky;
  top: 0;
  z-index: 2;
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.5rem 1rem;
  padding: 0.75rem var(--marge);
  background: color-mix(in srgb, var(--papier) 88%, transparent);
  backdrop-filter: blur(6px);
  border-bottom: var(--filet-trait);
}

ul {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 1rem;
  list-style: none;
  margin: 0;
  padding: 0;
}

button {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.25rem 0.1rem;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  font-size: 0.9rem;
  cursor: pointer;
}

.pastille {
  width: 0.85rem;
  height: 0.85rem;
  border-radius: 50%;
  /* Le filet evite qu'un pigment tres pale disparaisse dans le papier. */
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.18);
}

/* L'etat selectionne ne repose PAS sur la seule couleur. */
button[aria-pressed='true'] .nom {
  text-decoration: underline;
  text-decoration-thickness: 2px;
}

button[aria-pressed='false'] .nom {
  color: var(--encre-pale);
}

.effacer {
  margin-left: auto;
  font-size: 0.85rem;
  text-decoration: underline;
}
</style>
