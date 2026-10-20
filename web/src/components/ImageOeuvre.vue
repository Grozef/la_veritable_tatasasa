<script setup lang="ts">
import { computed } from 'vue'
import type { Media } from '@/api/types'

const props = defineProps<{
  media: Media | null
  alt: string
  /** Largeur que l'image occupera reellement, pour que le navigateur choisisse la bonne source. */
  sizes: string
  /** Renseigne uniquement sur l'image que la View Transition doit suivre d'une vue a l'autre. */
  transitionName?: string
}>()

/**
 * `<img>` a besoin d'un `src` : un element sans src affiche l'icone de fichier casse
 * dans certains navigateurs, meme quand le `srcset` suffit. On prend la plus petite
 * derivee, c'est-a-dire la premiere entree du srcset (format « url 400w, url 800w »).
 */
const repli = computed(() => props.media?.sources?.webp.split(', ')[0]?.split(' ')[0] ?? '')

/** Le rapport reel evite que la grille se reorganise a l'arrivee de chaque image. */
const proportions = computed(() =>
  props.media ? `${props.media.width} / ${props.media.height}` : '4 / 3',
)

// Fond pendant le chargement. C'est la moyenne de l'image, delavee — elle ne vaut rien
// comme couleur, tout comme aplat d'attente.
const fond = computed(() => props.media?.lqipHex ?? 'var(--papier-ombre)')
</script>

<template>
  <picture
    class="image-oeuvre"
    :style="{ aspectRatio: proportions, backgroundColor: fond, viewTransitionName: transitionName }"
  >
    <template v-if="media?.sources">
      <source type="image/avif" :srcset="media.sources.avif" :sizes="sizes" />
      <img
        :src="repli"
        :srcset="media.sources.webp"
        :sizes="sizes"
        :alt="alt"
        :width="media.width"
        :height="media.height"
        loading="lazy"
        decoding="async"
      />
    </template>
  </picture>
</template>

<style scoped>
/*
 * Sans derivees, le bloc reste un aplat de la couleur d'attente : mieux vaut une
 * reserve vide qu'une image cassee le temps que le job passe.
 */
.image-oeuvre {
  display: block;
  width: 100%;
  overflow: hidden;
  box-shadow: 0 1px 0 var(--filet);
}

img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
