<script setup>
defineProps({
  riskFlags: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <section class="space-y-3 rounded-[1.7rem] border border-amber-300/20 bg-amber-300/10 p-5">
    <h2 class="text-lg font-semibold text-amber-100">Risk Flags</h2>
    <p v-if="riskFlags.length === 0" class="text-sm text-slate-300">No risk flags.</p>
    <ul v-else class="list-disc space-y-1 pl-6 text-sm text-slate-100">
      <li v-for="(flag, index) in riskFlags" :key="index">{{ flag }}</li>
    </ul>
  </section>
</template>
