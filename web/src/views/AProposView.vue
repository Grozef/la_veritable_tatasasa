<script setup lang="ts">
/**
 * Le texte de cette page appartient a l'artiste. Rien n'est invente ici : une biographie
 * fabriquee finirait en ligne sous son nom. Les blocs restent marques tant qu'elle n'a
 * pas fourni ses mots.
 */
</script>

<template>
  <section class="page">
    <h1>L'atelier</h1>

    <p class="a-fournir">[Texte de presentation a fournir par l'artiste.]</p>

    <h2>La technique</h2>
    <p class="a-fournir">
      [Papiers, pigments et facon de travailler — a fournir par l'artiste. Le nuancier du
      site sera seede avec ses vrais tubes, marque et nom exact.]
    </p>
  </section>
</template>

<style scoped>
.page {
  padding: var(--marge);
  max-width: 42rem;
}

h1 {
  font-size: clamp(1.6rem, 1.2rem + 1.5vw, 2.4rem);
}

h2 {
  margin-top: 2rem;
  font-size: 1.2rem;
}

/* Visuellement distinct : personne ne doit prendre ce texte pour du contenu livre. */
.a-fournir {
  padding: 0.75rem 1rem;
  border: 1px dashed var(--filet);
  color: var(--encre-pale);
  font-family: var(--manuscrite);
}
</style>
