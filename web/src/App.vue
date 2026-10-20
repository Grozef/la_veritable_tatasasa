<script setup lang="ts">
import GrainPapier from '@/components/GrainPapier.vue'
import Nuancier from '@/components/Nuancier.vue'
</script>

<template>
  <GrainPapier />

  <a class="saut-contenu" href="#contenu">Aller au carnet</a>

  <div class="feuille">
    <header class="entete">
      <RouterLink :to="{ name: 'carnet' }" class="enseigne">La veritable Tatasasa</RouterLink>

      <nav aria-label="Pages">
        <RouterLink :to="{ name: 'a-propos' }">L'atelier</RouterLink>
        <RouterLink :to="{ name: 'contact' }">Contact</RouterLink>
      </nav>
    </header>

    <Nuancier />

    <main id="contenu">
      <RouterView />
    </main>

    <footer>
      <p>Aquarelles — toutes les images restent la propriete de l'artiste.</p>
    </footer>
  </div>
</template>

<style scoped>
/* Au-dessus du grain, qui est en position fixe sous le contenu. */
.feuille {
  position: relative;
  z-index: 1;
}

.entete {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem 2rem;
  padding: clamp(1rem, 3vw, 2rem) var(--marge) 0.75rem;
}

.enseigne {
  font-size: clamp(1.2rem, 1rem + 1vw, 1.8rem);
  letter-spacing: 0.02em;
  text-decoration: none;
}

nav {
  display: flex;
  gap: 1.5rem;
  font-size: 0.9rem;
}

nav a {
  text-decoration: none;
  color: var(--encre-pale);
}

nav a:hover,
nav a:focus-visible,
nav a.router-link-active {
  color: var(--encre);
  text-decoration: underline;
}

footer {
  margin-top: clamp(3rem, 10vh, 6rem);
  padding: 1.5rem var(--marge) 2.5rem;
  border-top: var(--filet-trait);
  color: var(--encre-pale);
  font-size: 0.8rem;
}

footer p {
  margin: 0;
}
</style>
