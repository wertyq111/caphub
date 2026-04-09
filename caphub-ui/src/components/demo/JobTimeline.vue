<script setup>
defineProps({
  status: {
    type: String,
    default: 'pending',
  },
});

const stages = ['pending', 'queued', 'processing', 'succeeded'];

function isDone(current, stage) {
  return stages.indexOf(stage) <= stages.indexOf(current);
}
</script>

<template>
  <section class="rounded-[1.7rem] border border-white/10 bg-white/5 p-5">
    <h2 class="mb-4 text-lg font-semibold text-white">Job Timeline</h2>
    <ol class="grid gap-2 md:grid-cols-4">
      <li
        v-for="stage in stages"
        :key="stage"
        class="rounded border px-3 py-2 text-sm capitalize"
        :class="isDone(status, stage)
          ? 'border-emerald-300/30 bg-emerald-300/12 text-emerald-100'
          : 'border-white/10 bg-slate-950/30 text-slate-400'"
      >
        {{ stage }}
      </li>
    </ol>
  </section>
</template>
