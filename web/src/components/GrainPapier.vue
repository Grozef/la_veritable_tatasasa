<script setup lang="ts">
/**
 * Grain du papier, en SVG et non en texture PNG : c'est une seule balise, ca ne pese
 * rien au reseau et le grain reste net a toutes les densites d'ecran.
 *
 * Fond fixe et en dessous du contenu : en multipliant sous le texte plutot que par
 * dessus, il salit le papier sans salir les lettres.
 */
</script>

<template>
  <svg class="grain" aria-hidden="true" focusable="false">
    <filter id="grain-papier">
      <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="3" stitchTiles="stitch" />
      <feColorMatrix type="saturate" values="0" />
    </filter>
    <rect width="100%" height="100%" filter="url(#grain-papier)" />
  </svg>
</template>

<style scoped>
.grain {
  position: fixed;
  inset: 0;
  width: 100%;
  height: 100%;
  z-index: 0;
  pointer-events: none;
  mix-blend-mode: multiply;
  opacity: 0.16;
}
</style>
