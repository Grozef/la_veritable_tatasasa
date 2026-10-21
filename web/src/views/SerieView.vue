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

/**
 * « Reessayer » disparait avec l'etat indisponible et le focus tombe sur `body` : sans ce
 * drapeau, la garde ci-dessous le prendrait pour une arrivee directe et le laisserait la.
 */
let relance = false

function reessayer(): void {
  relance = true
  void charger(props.slug)
}

/*
 * Le routeur pose le focus sur le h1 de la page d'arrivee, mais celui de la serie n'existe
 * qu'a l'arrivee de la reponse : il s'est rabattu sur `main`. Le h1 le reprend des qu'il
 * est rendu, et seulement si le focus est encore la (arrivee directe : rien a deplacer), ou
 * sur `body` apres une relance.
 */
watch(
  [serie, echec],
  () => {
    const contenu = document.getElementById('contenu')
    const titre = contenu?.querySelector<HTMLElement>('h1')

    if (!titre || !contenu) {
      return
    }

    const apresRelance = relance && (document.activeElement === document.body || !document.activeElement)

    relance = false

    if (document.activeElement !== contenu && !apresRelance) {
      return
    }

    titre.setAttribute('tabindex', '-1')
    titre.addEventListener('blur', () => titre.removeAttribute('tabindex'), { once: true })
    titre.focus({ preventScroll: true })
  },
  { flush: 'post' },
)

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

  <div v-else-if="echec === 'introuvable'" class="etat reserve">
    <h1 class="lecteur-seul">Série introuvable</h1>
    <p>
      Cette série n'est pas au carnet. <RouterLink :to="{ name: 'carnet' }">Y revenir</RouterLink>
    </p>
  </div>

  <div v-else-if="echec" class="etat reserve">
    <h1 class="lecteur-seul">Série indisponible</h1>
    <p role="alert">
      La série n'a pas pu s'ouvrir.
      <button type="button" @click="reessayer">Réessayer</button>
    </p>
  </div>

  <p v-else class="etat reserve">Ouverture de la série…</p>
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

/*
 * Ouverture et issues sans grille : la place est tenue jusqu'au bas de l'ecran, sinon le
 * pied de page s'affiche sous le message court puis l'arrivee de la serie le chasse.
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
