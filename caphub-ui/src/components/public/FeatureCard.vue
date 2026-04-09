<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

const props = defineProps({
  feature: {
    type: Object,
    required: true,
  },
});

const cardClass = computed(() => props.feature.available
  ? 'group border-cyan-300/30 bg-[linear-gradient(180deg,rgba(5,15,36,0.95),rgba(4,13,29,0.95))] hover:border-cyan-200/45 hover:shadow-[0_0_24px_rgba(34,211,238,0.22)]'
  : 'cursor-not-allowed border-slate-500/30 bg-[linear-gradient(180deg,rgba(9,14,28,0.9),rgba(8,13,24,0.95))] opacity-80');
</script>

<template>
  <component
    :is="feature.available ? RouterLink : 'article'"
    :to="feature.available ? feature.to : undefined"
    class="block rounded-xl border p-4 transition duration-300 sm:p-5"
    :class="cardClass"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="flex h-11 w-11 items-center justify-center rounded-lg border border-cyan-300/30 bg-cyan-500/10 text-base font-semibold text-cyan-100">
        {{ feature.icon }}
      </div>
      <span
        class="rounded-md px-2.5 py-1 text-[11px] font-medium"
        :class="feature.available
          ? 'border border-emerald-300/35 bg-emerald-300/12 text-emerald-200'
          : 'border border-slate-300/20 bg-slate-400/10 text-slate-300'"
      >
        {{ feature.available ? '已开放' : '即将开放' }}
      </span>
    </div>

    <div class="mt-4 space-y-2">
      <h3 class="text-lg font-semibold text-cyan-50 sm:text-xl">{{ feature.title }}</h3>
      <p class="text-sm leading-6 text-slate-300">{{ feature.description }}</p>
    </div>

    <div class="mt-4 flex items-center justify-between border-t border-cyan-100/10 pt-3 text-xs sm:text-sm">
      <span class="text-slate-400">{{ feature.meta }}</span>
      <span class="font-medium text-cyan-100">
        {{ feature.available ? '进入模块' : '准备中' }}
      </span>
    </div>
  </component>
</template>
