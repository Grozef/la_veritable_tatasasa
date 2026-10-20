<script setup lang="ts">
import ImageOeuvre from '@/components/ImageOeuvre.vue'
import type { Work } from '@/api/types'

defineProps<{ works: Work[] }>()

/** Le media principal ; a defaut le premier disponible, pour ne jamais rendre un trou. */
const principal = (work: Work) =>
  work.media.find((media) => media.role === 'principal') ?? work.media[0] ?? null
</script>

<template>
  <ul class="carnet">
    <li v-for="work in works" :key="work.slug">
      <RouterLink :to="{ name: 'oeuvre', params: { slug: work.slug } }">
        <ImageOeuvre
          :media="principal(work)"
          :alt="`${work.title}, ${work.year}`"
          sizes="(min-width: 60rem) 30vw, (min-width: 40rem) 45vw, 90vw"
          :transition-name="`oeuvre-${work.slug}`"
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
  font-family: var(--manuscrite);
  font-size: 0.95rem;
  color: var(--encre-pale);
}

a:hover .titre,
a:focus-visible .titre {
  text-decoration: underline;
}
</style>
