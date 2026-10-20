import { nextTick } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import CarnetView from '@/views/CarnetView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'carnet', component: CarnetView },
    {
      path: '/oeuvre/:slug',
      name: 'oeuvre',
      component: () => import('@/views/OeuvreView.vue'),
      props: true,
    },
    {
      path: '/pigment/:slug',
      name: 'pigment',
      component: () => import('@/views/PigmentView.vue'),
      props: true,
    },
    {
      path: '/serie/:slug',
      name: 'serie',
      component: () => import('@/views/SerieView.vue'),
      props: true,
    },
    { path: '/a-propos', name: 'a-propos', component: () => import('@/views/AProposView.vue') },
    { path: '/contact', name: 'contact', component: () => import('@/views/ContactView.vue') },
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
router.afterEach(async () => {
  await nextTick()
  acheverTransition?.()
  acheverTransition = null
})

export default router
