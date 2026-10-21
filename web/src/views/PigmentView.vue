<script setup lang="ts">
import { computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import CarnetGrid from '@/components/CarnetGrid.vue'
import { useWorksStore } from '@/stores/works'
import { useTitre } from '@/composables/titre'

const props = defineProps<{ slug: string }>()

const store = useWorksStore()
const { filtrees, pigments, chargement, erreur } = storeToRefs(store)

/*
 * La vue pigment n'est pas un filtre a part : elle POSE la selection du nuancier.
 * Le visiteur qui arrive par un lien profond retrouve la bande dans le meme etat que
 * s'il avait clique la pastille.
 *
 * Selection posee AVANT le chargement : posee apres, le carnet entier serait rendu une
 * fois (toutes les vignettes montees, leurs images demandees) avant d'etre filtre.
 */
watch(
  () => props.slug,
  (slug) => {
    store.selectionner([slug])
    void store.charger()
  },
  { immediate: true },
)

const pigment = computed(() => pigments.value.find((p) => p.slug === props.slug) ?? null)

useTitre(
  () =>
    pigment.value?.name ??
    (chargement.value ? null : erreur.value ? 'Pigment indisponible' : 'Pigment introuvable'),
)
</script>

<template>
  <section>
    <!--
      Tant que le nuancier n'est pas charge, l'absence de pigment n'est pas une
      information : l'annoncer afficherait un dementi faux a tout visiteur lent.
    -->
    <p v-if="chargement" class="etat reserve">Ouverture du nuancier…</p>

    <header v-else-if="pigment">
      <span class="pastille" :style="{ backgroundColor: pigment.hex }" aria-hidden="true" />
      <div class="intitule">
        <h1>{{ pigment.name }}</h1>
        <p v-if="pigment.brand">{{ pigment.brand }}</p>
        <p>{{ filtrees.length }} œuvre{{ filtrees.length > 1 ? 's' : '' }} au carnet</p>
      </div>
    </header>

    <div v-else-if="erreur" class="etat reserve">
      <h1 class="lecteur-seul">Pigment indisponible</h1>
      <p role="alert">
        Le nuancier n'a pas pu s'ouvrir.
        <button type="button" @click="store.charger()">Réessayer</button>
      </p>
    </div>

    <div v-else class="etat reserve">
      <h1 class="lecteur-seul">Pigment introuvable</h1>
      <p>Ce pigment n'est porté par aucune œuvre publiée.</p>
    </div>

    <CarnetGrid :works="filtrees" />
  </section>
</template>

<style scoped>
/*
 * Pastille a gauche du titre tant que la place le permet. Texte agrandi sur ecran etroit
 * (un nom de pigment ne se coupe pas) : le titre passe sous la pastille au lieu de deborder.
 */
header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
  /* Le meme ecart que sous l'en-tete d'une serie (marge de sa note) : sans lui, a 390 px
     le titre touchait la grille de plus pres que les cartes ne se touchent entre elles. */
  padding: var(--marge) var(--marge) 1rem;
}

h1 {
  margin: 0;
  font-size: clamp(1.6rem, 1.2rem + 1.5vw, 2.4rem);
}

.intitule {
  display: grid;
  gap: 0.25rem;
}

.pastille {
  flex: none;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px var(--contour-pastille);
}

p {
  margin: 0;
  color: var(--encre-pale);
  font-size: 0.9rem;
}

.etat {
  padding: var(--marge);
}

/*
 * Ouverture et issues sans grille : la place est tenue jusqu'au bas de l'ecran, sinon le
 * pied de page s'affiche sous le message court puis l'arrivee des oeuvres le chasse.
 */
.etat.reserve {
  min-height: 100svh;
  /* Sans elle, la marge du message traverse `main` et le decale a l'arrivee du contenu. */
  margin-top: 0;
}

/* 44 px de cible tactile ; la marge negative egale laisse la ligne a sa hauteur. */
button {
  --toucher: max(0px, (44px - 1lh) / 2);
  padding-block: var(--toucher);
  margin-block: calc(-1 * var(--toucher));
  margin-left: 0.5rem;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-decoration: underline;
  cursor: pointer;
}
</style>
