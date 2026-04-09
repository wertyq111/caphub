<script setup>
defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  hint: {
    type: String,
    default: '',
  },
});
</script>

<template>
  <article class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_18px_50px_rgba(15,23,42,0.05)]">
    <p class="text-sm font-medium text-slate-500">{{ label }}</p>
    <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ value }}</p>
    <p v-if="hint" class="mt-3 text-sm leading-6 text-slate-500">{{ hint }}</p>
  </article>
</template>
