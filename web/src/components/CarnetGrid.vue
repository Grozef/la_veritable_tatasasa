<script setup lang="ts">
import ImageOeuvre from '@/components/ImageOeuvre.vue'
import type { Work } from '@/api/types'

defineProps<{ works: Work[] }>()

/*
 * Largeur reelle d'une colonne, deduite de la grille ci-dessous. Marge m = clamp(16, 4vw, 64),
 * gouttiere g = clamp(24, 4vw, 56), colonnes de 288 px minimum : n colonnes des que
 * n x 288 + (n - 1) x g <= W - 2m, soit 2 a 654 px, 3 a 1029, 4 a 1435. Au-dela de 100rem
 * (marge et gouttiere plafonnees), n colonnes a partir de 344n + 72 px, chacune de
 * W/n - 56 - 72/n px : 5 a 1792, 6 a 2136, 7 a 2480, 8 a 2824, 9 a 3168, 10 a 3512 (11 a
 * 3856 : la valeur annoncee y depasse la colonne, jamais l'inverse). Seuils arrondis au-dessus.
 */
const TAILLES = [
  '(min-width: 219.5rem) calc(10vw - 63.2px)',
  '(min-width: 198rem) calc(11.111vw - 64px)',
  '(min-width: 176.5rem) calc(12.5vw - 65px)',
  '(min-width: 155rem) calc(14.286vw - 66.3px)',
  '(min-width: 133.5rem) calc(16.667vw - 68px)',
  '(min-width: 112rem) calc(20vw - 70.4px)',
  '(min-width: 100rem) calc(25vw - 74px)',
  '(min-width: 89.68rem) calc(23vw - 42px)',
  '(min-width: 87.5rem) calc(30.667vw - 37.3px)',
  '(min-width: 64.29rem) 28vw',
  '(min-width: 40.91rem) 44vw',
  '(min-width: 25rem) 92vw',
  'calc(100vw - 2rem)',
].join(', ')

/**
 * Une rangee a la largeur courante d'un ecran de bureau : visibles a l'arrivee. Sur ecran
 * etroit la grille n'a qu'une colonne : une seule image est visible, les autres ne doivent
 * pas lui disputer la bande passante.
 */
const PREMIERE_RANGEE = window.matchMedia('(min-width: 48rem)').matches ? 4 : 1

/** Le media principal ; a defaut le premier disponible, pour ne jamais rendre un trou. */
const principal = (work: Work) =>
  work.media.find((media) => media.role === 'principal') ?? work.media[0] ?? null
</script>

<template>
  <ul class="carnet">
    <li v-for="(work, rang) in works" :key="work.slug">
      <RouterLink :to="{ name: 'oeuvre', params: { slug: work.slug } }">
        <ImageOeuvre
          :media="principal(work)"
          :alt="`${work.title}, ${work.year}`"
          :sizes="TAILLES"
          :transition-name="`oeuvre-${work.slug}`"
          :prioritaire="rang < PREMIERE_RANGEE"
        />

        <span class="legende">
          <span class="titre">{{ work.title }}</span>
          <span class="annee">{{ work.year }}</span>
        </span>
      </RouterLink>

      <p v-if="work.notes" class="note">{{ work.notes }}</p>
    </li>
  </ul>
</template>

<style scoped>
/*
 * Grille volontairement desalignee : une page d'album parfaitement reglee a l'air d'un
 * catalogue, pas d'un carnet. Le decalage est porte par nth-child et non par une valeur
 * aleatoire, sinon la mise en page sauterait a chaque rendu.
 */
.carnet {
  list-style: none;
  margin: 0;
  padding: var(--marge);
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(18rem, 100%), 1fr));
  gap: clamp(2rem, 5vw, 4.5rem) clamp(1.5rem, 4vw, 3.5rem);
  align-items: start;
}

li:nth-child(3n + 2) {
  margin-top: clamp(1rem, 4vw, 3rem);
}

li:nth-child(4n + 3) {
  margin-top: clamp(0.5rem, 2vw, 1.5rem);
}

a {
  display: block;
  text-decoration: none;
}

.legende {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 1rem;
  margin-top: 0.6rem;
  padding-top: 0.4rem;
  border-top: var(--filet-trait);
}

.titre {
  font-size: 1.05rem;
}

.annee {
  color: var(--encre-pale);
  font-size: 0.85rem;
  font-variant-numeric: oldstyle-nums;
}

/* La note d'atelier, en marge, comme sur le carnet papier. */
.note {
  margin: 0.5rem 0 0;
  padding-left: 1.2rem;
  border-left: 2px solid var(--papier-ombre);
  /* Comme la note de la fiche : pas de mot seul en derniere ligne (« lave. »). */
  text-wrap: pretty;
  font-family: var(--manuscrite);
  font-size: 0.95rem;
  color: var(--encre-pale);
}

a:hover .titre,
a:focus-visible .titre {
  text-decoration: underline;
}
</style>
