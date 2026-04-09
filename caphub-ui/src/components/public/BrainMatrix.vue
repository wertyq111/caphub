<script setup>
import { RouterLink } from 'vue-router';

defineProps({
  features: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <section id="feature-matrix" class="relative flex flex-col items-center py-8">
    <div class="relative z-10 flex h-36 w-36 flex-col items-center justify-center rounded-full border-2 border-cyan-300/50 bg-[radial-gradient(circle,rgba(6,182,212,0.18),rgba(2,8,23,0.95))] shadow-[0_0_60px_rgba(34,211,238,0.35),0_0_120px_rgba(34,211,238,0.15)]">
      <div class="absolute inset-0 animate-ping rounded-full border border-cyan-300/20" style="animation-duration:3s" />
      <div class="text-[11px] font-medium uppercase tracking-[0.3em] text-cyan-100/60">AI Core</div>
      <div class="mt-1 text-xl font-bold text-cyan-100">CapHub</div>
      <div class="mt-0.5 text-[10px] text-cyan-300/70">Neural Matrix</div>
    </div>

    <div class="mt-10 grid w-full max-w-4xl gap-4 sm:grid-cols-3">
      <component
        v-for="feature in features"
        :key="feature.title"
        :is="feature.available ? RouterLink : 'article'"
        :to="feature.available ? feature.to : undefined"
        class="group relative rounded-xl border p-5 transition duration-300"
        :class="feature.available
          ? 'border-cyan-300/35 bg-[linear-gradient(180deg,rgba(5,15,36,0.95),rgba(4,13,29,0.95))] hover:border-cyan-200/55 hover:shadow-[0_0_28px_rgba(34,211,238,0.25)]'
          : 'cursor-not-allowed border-slate-500/25 bg-[linear-gradient(180deg,rgba(9,14,28,0.9),rgba(8,13,24,0.95))] opacity-70'"
      >
        <div
          class="absolute -top-4 left-1/2 h-4 w-px -translate-x-1/2"
          :class="feature.available ? 'bg-gradient-to-b from-transparent to-cyan-300/50' : 'bg-gradient-to-b from-transparent to-slate-500/30'"
        />

        <div class="flex items-start justify-between gap-3">
          <div
            class="flex h-11 w-11 items-center justify-center rounded-lg border text-base font-semibold"
            :class="feature.available ? 'border-cyan-300/30 bg-cyan-500/10 text-cyan-100' : 'border-slate-500/20 bg-slate-700/20 text-slate-400'"
          >
            {{ feature.icon }}
          </div>
          <span
            class="rounded-md px-2.5 py-1 text-[11px] font-medium"
            :class="feature.available
              ? 'border border-emerald-300/35 bg-emerald-300/12 text-emerald-200'
              : 'border border-slate-300/20 bg-slate-400/10 text-slate-400'"
          >
            {{ feature.available ? '已开放' : '即将开放' }}
          </span>
        </div>

        <div class="mt-4 space-y-1.5">
          <h3 class="text-base font-semibold" :class="feature.available ? 'text-cyan-50' : 'text-slate-400'">{{ feature.title }}</h3>
          <p class="text-xs leading-5 text-slate-400">{{ feature.description }}</p>
        </div>

        <div
          class="mt-4 flex items-center justify-between border-t pt-3 text-xs"
          :class="feature.available ? 'border-cyan-100/10 text-slate-400' : 'border-slate-600/20 text-slate-500'"
        >
          <span>{{ feature.meta }}</span>
          <span :class="feature.available ? 'text-cyan-300' : 'text-slate-500'">
            {{ feature.available ? '进入模块 →' : '准备中' }}
          </span>
        </div>
      </component>
    </div>
  </section>
</template>
