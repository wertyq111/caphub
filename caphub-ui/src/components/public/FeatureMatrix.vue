<script setup>
import FeatureCard from './FeatureCard.vue';

defineProps({
  features: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <section id="feature-matrix" class="space-y-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
      <div class="space-y-2">
        <p class="text-[11px] font-medium uppercase tracking-[0.3em] text-cyan-100/70">Feature Matrix</p>
        <h2 class="text-2xl font-semibold text-cyan-50 md:text-3xl">AI 能力入口矩阵</h2>
      </div>
      <p class="max-w-2xl text-sm leading-6 text-slate-300 md:text-right">
        以控制台视角展示当前能力、后台支撑与下一步计划，保持首页信息密度与系统感。
      </p>
    </div>

    <div class="rounded-[1.4rem] border border-cyan-300/20 bg-[linear-gradient(180deg,rgba(8,17,37,0.92),rgba(4,12,28,0.96))] p-3 sm:p-4">
      <div class="grid gap-3 lg:grid-cols-3">
      <FeatureCard
        v-for="feature in features"
        :key="feature.title"
        :feature="feature"
      />
      </div>
    </div>
  </section>
</template>
