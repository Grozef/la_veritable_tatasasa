<script setup lang="ts">
/**
 * Pas de formulaire : l'API n'expose aucune route de contact, et en ajouter une
 * demanderait une adresse, un anti-spam et une politique de conservation qui n'ont pas
 * ete decidees. Un lien courriel fait le travail le jour ou l'adresse est connue.
 */
</script>

<template>
  <section class="page">
    <h1>Ecrire a l'atelier</h1>

    <p class="a-fournir">[Adresse de contact a fournir par l'artiste.]</p>

    <p>
      Pour une oeuvre en particulier, indiquez son titre et son annee : ils figurent sous
      chaque image du carnet.
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

.a-fournir {
  padding: 0.75rem 1rem;
  border: 1px dashed var(--filet);
  color: var(--encre-pale);
  font-family: var(--manuscrite);
}
</style>
