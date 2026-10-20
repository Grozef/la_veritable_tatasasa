import { createRouter, createWebHistory } from '@ionic/vue-router';
import { RouteRecordRaw } from 'vue-router';
import AtelierPage from '../views/AtelierPage.vue'
import { jeton } from '@/services/session'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    redirect: '/atelier'
  },
  {
    path: '/connexion',
    name: 'connexion',
    component: () => import('@/views/ConnexionPage.vue')
  },
  {
    path: '/atelier',
    name: 'atelier',
    component: AtelierPage,
    meta: { authentifie: true }
  },
  {
    path: '/capture',
    name: 'capture',
    component: () => import('@/views/CaptureFlowPage.vue'),
    meta: { authentifie: true }
  },
  {
    path: '/partage/:ulid',
    name: 'partage',
    component: () => import('@/views/PartagePage.vue'),
    props: true,
    meta: { authentifie: true }
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

/*
 * Garde de presence, pas de validite : un jeton en stock peut avoir ete revoque depuis
 * un autre appareil. La verification reelle est faite par la premiere requete, qui
 * renvoie ici sur 401 — l'app doit rester ouvrable hors ligne.
 */
router.beforeEach(async (destination) => {
  if (!destination.meta.authentifie) {
    return true
  }

  return (await jeton()) ? true : { name: 'connexion' }
})

export default router
