import { nextTick } from 'vue'
import { createRouter, createWebHistory, START_LOCATION } from 'vue-router'
import CarnetView from '@/views/CarnetView.vue'
import { poserTitre } from '@/composables/titre'

declare module 'vue-router' {
  interface RouteMeta {
    /** Titre generique de l'onglet ; les vues a donnee le precisent une fois chargees. */
    titre?: string
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'carnet', component: CarnetView },
    {
      path: '/oeuvre/:slug',
      name: 'oeuvre',
      component: () => import('@/views/OeuvreView.vue'),
      props: true,
      meta: { titre: 'Œuvre' },
    },
    {
      path: '/pigment/:slug',
      name: 'pigment',
      component: () => import('@/views/PigmentView.vue'),
      props: true,
      meta: { titre: 'Pigment' },
    },
    {
      path: '/serie/:slug',
      name: 'serie',
      component: () => import('@/views/SerieView.vue'),
      props: true,
      meta: { titre: 'Série' },
    },
    {
      path: '/a-propos',
      name: 'a-propos',
      component: () => import('@/views/AProposView.vue'),
      meta: { titre: "L'atelier" },
    },
    {
      path: '/contact',
      name: 'contact',
      component: () => import('@/views/ContactView.vue'),
      meta: { titre: 'Contact' },
    },
  ],
  scrollBehavior: (_to, _from, position) => position ?? { top: 0 },
})

/*
 * Transition carnet -> oeuvre par la View Transitions API, et RIEN en repli : un
 * navigateur qui ne la connait pas navigue normalement, sans polyfill ni animation
 * JavaScript de remplacement.
 *
 * Le rendez-vous est en deux temps parce que la transition doit encadrer un rendu Vue :
 * `beforeResolve` gele l'ecran actuel, la navigation reprend, et la transition ne se
 * termine qu'apres `afterEach` + `nextTick`, c'est-a-dire une fois le nouveau DOM pose.
 */
const demarrerTransition = (
  document as Document & {
    startViewTransition?: (rendu: () => Promise<void> | void) => unknown
  }
).startViewTransition

let acheverTransition: (() => void) | null = null

router.beforeResolve(() => {
  if (typeof demarrerTransition !== 'function') {
    return
  }

  return new Promise<void>((poursuivreNavigation) => {
    demarrerTransition.call(document, () => {
      poursuivreNavigation()

      return new Promise<void>((fin) => {
        acheverTransition = fin
      })
    })
  })
})

// Appele aussi sur une navigation avortee : sans cela l'ecran resterait fige sur la
// capture de la transition.
router.afterEach(async (to, from, echec) => {
  // Avant le rendu : la vue d'arrivee, montee ensuite, peut encore preciser ce titre.
  if (!echec) {
    poserTitre(to.meta.titre)
  }

  await nextTick()
  acheverTransition?.()
  acheverTransition = null

  // Au premier chargement le focus reste en haut du document, devant le lien
  // d'evitement ; une navigation qui ne change que la requete ne change pas de page.
  if (echec || from === START_LOCATION || to.path === from.path) {
    return
  }

  /*
   * Sans cela le focus reste sur le lien clique, dans une page qui n'existe plus : le
   * clavier repart du debut et le lecteur d'ecran n'annonce rien. Le titre de la page
   * d'arrivee, a defaut le contenu entier (`#contenu`, pose par App.vue).
   */
  const contenu = document.getElementById('contenu')
  const cible = contenu?.querySelector<HTMLElement>('h1') ?? contenu

  if (cible) {
    cible.setAttribute('tabindex', '-1')
    cible.focus({ preventScroll: true })
  }
})

export default router
