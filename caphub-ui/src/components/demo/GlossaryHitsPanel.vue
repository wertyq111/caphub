<script setup>
defineProps({
  glossaryHits: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <section class="space-y-3 rounded-[1.7rem] border border-sky-300/20 bg-sky-300/10 p-5">
    <h2 class="text-lg font-semibold text-sky-100">Glossary Hits</h2>
    <p v-if="glossaryHits.length === 0" class="text-sm text-slate-300">No glossary hits.</p>
    <ul v-else class="space-y-2 text-sm">
      <li v-for="(hit, index) in glossaryHits" :key="index" class="rounded-2xl border border-white/10 bg-slate-950/35 p-3 text-slate-100">
        <strong>{{ hit.source_term }}</strong>
        <span class="mx-1">→</span>
        <span>{{ hit.chosen_translation }}</span>
      </li>
    </ul>
  </section>
</template>
