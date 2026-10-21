<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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

/*
 * La bande est dans la coquille : elle se charge elle-meme, sinon une arrivee directe
 * sur une page sans oeuvres (atelier, contact, serie) la laisserait vide. `charger` est
 * idempotent : les vues qui l'appellent aussi reprennent la meme requete.
 */
onMounted(() => store.charger())

/*
 * Sur ecran etroit la bande tient sur une ligne qui defile a l'horizontale. Les fondus
 * aux bords disent qu'il reste des pigments de ce cote-la.
 */
const bande = ref<HTMLElement | null>(null)
const liste = ref<HTMLUListElement | null>(null)
const suiteAGauche = ref(false)
const suiteADroite = ref(false)

/*
 * Collante seulement si elle reste une bande : au-dela d'un cinquieme de la fenetre
 * (texte agrandi, telephone a l'horizontale), elle masquerait les oeuvres qu'elle filtre.
 */
const collante = ref(false)

function mesurer(): void {
  const ul = liste.value

  if (ul) {
    suiteAGauche.value = ul.scrollLeft > 1
    suiteADroite.value = ul.scrollLeft + ul.clientWidth < ul.scrollWidth - 1
  }

  if (bande.value) {
    collante.value = bande.value.offsetHeight <= window.innerHeight * 0.2
  }
}

const observateur = new ResizeObserver(mesurer)

onMounted(() => {
  if (bande.value) observateur.observe(bande.value)
  if (liste.value) observateur.observe(liste.value)
  window.addEventListener('resize', mesurer)
  // Une police web chargee apres coup elargit la ligne sans changer la taille de la liste.
  void document.fonts?.ready.then(mesurer)
})

onBeforeUnmount(() => {
  observateur.disconnect()
  window.removeEventListener('resize', mesurer)
})

/*
 * Le dernier pigment choisi reste en vue dans la ligne, y compris a l'arrivee sur
 * /pigment/:slug. On fait defiler la seule liste : un scrollIntoView ferait aussi
 * remonter la page quand la bande n'est plus collante.
 */
function amenerEnVue(bouton: HTMLElement): void {
  const ul = liste.value

  if (!ul) {
    return
  }

  const [b, u] = [bouton.getBoundingClientRect(), ul.getBoundingClientRect()]
  // Largeur du fondu CSS (2rem), pour que le bouton ne reste pas a demi efface dessous.
  const fondu = 2 * parseFloat(getComputedStyle(document.documentElement).fontSize)

  if (b.left < u.left + fondu) {
    ul.scrollLeft -= u.left + fondu - b.left
  } else if (b.right > u.right - fondu) {
    ul.scrollLeft += b.right - (u.right - fondu)
  }
}

watch(
  [selection, pigments],
  async () => {
    await nextTick()
    const dernier = selection.value.at(-1)
    // Le slug peut venir de l'URL (/pigment/:slug) : echappe avant d'entrer dans un selecteur.
    const bouton = dernier ? liste.value?.querySelector<HTMLElement>(`[data-slug="${CSS.escape(dernier)}"]`) : null

    if (bouton) {
      amenerEnVue(bouton)
    }

    mesurer()
  },
  { flush: 'post' },
)

/** Au clavier, le bouton qui recoit le focus sort du fondu, comme le pigment choisi. */
function focusDansLaListe(event: FocusEvent): void {
  if (event.target instanceof HTMLElement) {
    amenerEnVue(event.target)
  }
}

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
  <nav ref="bande" class="nuancier" :class="{ collante }" aria-label="Filtrer par pigment">
    <ul
      ref="liste"
      :class="{ 'suite-gauche': suiteAGauche, 'suite-droite': suiteADroite, attente: pigments.length === 0 }"
      @scroll.passive="mesurer"
      @focusin="focusDansLaListe"
    >
      <li v-for="pigment in pigments" :key="pigment.slug">
        <button
          type="button"
          :data-slug="pigment.slug"
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
  display: flex;
  align-items: center;
  gap: 0 1rem;
  padding: 0.75rem var(--marge);
  background: color-mix(in srgb, var(--papier) 88%, transparent);
  backdrop-filter: blur(6px);
  border-bottom: var(--filet-trait);
}

.nuancier.collante {
  position: sticky;
  top: 0;
  z-index: 2;
}

/*
 * Ecran etroit : une seule ligne, defilement tactile natif. Le rembourrage compense
 * par la marge negative laisse la place au contour de focus, que le defilement rognerait.
 */
ul {
  --fondu-g: 0px;
  --fondu-d: 0px;
  flex: 1;
  min-width: 0;
  display: flex;
  gap: 0 1rem;
  list-style: none;
  margin: -0.4rem;
  padding: 0.4rem;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  scrollbar-width: thin;
  scrollbar-color: var(--filet) transparent;
  mask-image: linear-gradient(
    to right,
    transparent,
    #000 var(--fondu-g),
    #000 calc(100% - var(--fondu-d)),
    transparent
  );
}

ul.suite-gauche {
  --fondu-g: 2rem;
}

ul.suite-droite {
  --fondu-d: 2rem;
}

/*
 * Les pigments arrivent avec les oeuvres : sans place reservee, la bande grandit a leur
 * arrivee et pousse toute la page (decalage de mise en page). Une rangee de 44 px ici.
 */
ul.attente {
  min-height: 44px;
}

li {
  flex-shrink: 0;
}

/*
 * Assez de place, en largeur ET en hauteur : la bande s'etale sur plusieurs lignes,
 * sans defilement. Un telephone a l'horizontale garde la ligne unique.
 */
@media (min-width: 48rem) and (min-height: 36rem) {
  .nuancier {
    align-items: baseline;
  }

  ul {
    flex-wrap: wrap;
    overflow-x: visible;
    mask-image: none;
  }

  /* Deux rangees : la hauteur mesuree de la bande a 1440 px (113 px, marges comprises). */
  ul.attente {
    min-height: 88px;
  }
}

button {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  /* 44 px : la cible tactile minimale, sans grossir le texte. */
  min-height: 44px;
  padding: 0 0.1rem;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  font-size: 0.9rem;
  white-space: nowrap;
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
  flex-shrink: 0;
  margin-left: auto;
  font-size: 0.85rem;
  text-decoration: underline;
}
</style>
