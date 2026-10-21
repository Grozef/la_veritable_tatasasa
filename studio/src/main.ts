import { createApp } from 'vue'
import App from './App.vue'
import router from './router';

import {
  createAnimation,
  getIonPageElement,
  iosTransitionAnimation,
  IonicVue,
  mdTransitionAnimation,
  type Animation,
  type TransitionOptions,
} from '@ionic/vue';

/* Core CSS required for Ionic components to work properly */
import '@ionic/vue/css/core.css';

/* Basic CSS for apps built with Ionic */
import '@ionic/vue/css/normalize.css';
import '@ionic/vue/css/structure.css';
import '@ionic/vue/css/typography.css';

/* Optional CSS utils that can be commented out */
import '@ionic/vue/css/padding.css';
import '@ionic/vue/css/float-elements.css';
import '@ionic/vue/css/text-alignment.css';
import '@ionic/vue/css/text-transformation.css';
import '@ionic/vue/css/flex-utils.css';
import '@ionic/vue/css/display.css';

/**
 * Ionic Dark Mode
 * -----------------------------------------------------
 * For more info, please see:
 * https://ionicframework.com/docs/theming/dark-mode
 */

/* @import '@ionic/vue/css/palettes/dark.always.css'; */
/* @import '@ionic/vue/css/palettes/dark.class.css'; */
import '@ionic/vue/css/palettes/dark.system.css';

/* Theme variables */
import './theme/variables.css';

/*
 * Mouvement reduit (Android : « Supprimer les animations ») : la page suivante arrive en fondu
 * de 150 ms au lieu de glisser sur 280. Le reglage est relu a chaque navigation, il peut
 * changer pendant que l'app tourne ; sans lui, la transition de la plateforme reste.
 */
const mouvementReduit = window.matchMedia('(prefers-reduced-motion: reduce)');

function fondu({ enteringEl, leavingEl }: TransitionOptions): Animation {
  const animation = createAnimation()
    .duration(150)
    .easing('linear')
    .addAnimation(
      createAnimation()
        .addElement(getIonPageElement(enteringEl))
        .fill('both')
        .beforeRemoveClass('ion-page-invisible')
        .fromTo('opacity', '0', '1'),
    );

  if (leavingEl) {
    animation.addAnimation(
      createAnimation().addElement(getIonPageElement(leavingEl)).fromTo('opacity', '1', '0'),
    );
  }

  return animation;
}

function navigation(base: HTMLElement, options: TransitionOptions): Animation {
  if (mouvementReduit.matches) {
    return fondu(options);
  }

  return options.mode === 'ios'
    ? iosTransitionAnimation(base, options)
    : mdTransitionAnimation(base, options);
}

const app = createApp(App)
  .use(IonicVue, { navAnimation: navigation })
  .use(router);

router.isReady().then(() => {
  app.mount('#app');
});
