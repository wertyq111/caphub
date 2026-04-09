<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

const props = defineProps({
  eyebrow: {
    type: String,
    default: 'AI 实用功能接口与演示中心',
  },
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  primaryLabel: {
    type: String,
    default: '进入翻译工作台',
  },
  primaryTo: {
    type: String,
    default: '/demo/translate',
  },
  secondaryLabel: {
    type: String,
    default: '查看功能矩阵',
  },
  secondaryTo: {
    type: String,
    default: '#feature-matrix',
  },
  metrics: {
    type: Array,
    default: () => [],
  },
  variant: {
    type: String,
    default: 'dashboard',
  },
});

const isTranslateVariant = computed(() => props.variant === 'translate');
const useRouterForSecondary = computed(() => props.secondaryTo.startsWith('/'));
</script>

<template>
  <section
    class="relative overflow-hidden rounded-[1.5rem] border border-cyan-300/20 bg-[linear-gradient(160deg,rgba(3,15,33,0.98),rgba(2,8,23,0.98))] shadow-[0_24px_70px_rgba(2,8,23,0.6)]"
    :class="isTranslateVariant ? 'px-5 py-6 sm:px-8 sm:py-8' : 'px-4 py-5 sm:px-6 sm:py-7'"
  >
    <div class="pointer-events-none absolute inset-0">
      <div class="absolute -left-20 top-4 h-52 w-52 rounded-full bg-sky-500/18 blur-3xl" />
      <div class="absolute right-2 top-1/3 h-56 w-56 rounded-full bg-indigo-500/20 blur-3xl" />
      <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(56,189,248,0.06),transparent_28%,transparent_72%,rgba(96,165,250,0.08))]" />
    </div>

    <div class="relative space-y-6">
      <div class="flex items-start justify-between gap-4 border-b border-cyan-200/15 pb-4">
        <div class="min-w-0 space-y-2">
          <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-cyan-100/70">{{ eyebrow }}</p>
          <h1
            class="text-cyan-50"
            :class="isTranslateVariant ? 'text-2xl font-semibold sm:text-4xl' : 'text-xl font-semibold sm:text-3xl'"
          >
            {{ title }}
          </h1>
          <p class="max-w-3xl text-sm leading-6 text-slate-300 sm:text-base">{{ description }}</p>
        </div>
        <div class="hidden shrink-0 items-center gap-2 rounded-xl border border-cyan-300/15 bg-slate-950/65 px-3 py-2 text-[11px] text-cyan-100/80 lg:flex">
          <span class="h-2 w-2 rounded-full bg-emerald-300 shadow-[0_0_14px_rgba(52,211,153,0.7)]" />
          System Online
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <RouterLink
          :to="primaryTo"
          class="inline-flex items-center rounded-lg border border-cyan-300/30 bg-cyan-400/85 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
        >
          {{ primaryLabel }}
        </RouterLink>

        <RouterLink
          v-if="useRouterForSecondary"
          :to="secondaryTo"
          class="inline-flex items-center rounded-lg border border-white/15 bg-white/5 px-4 py-2 text-sm text-slate-100 transition hover:border-cyan-300/45 hover:bg-cyan-500/10"
        >
          {{ secondaryLabel }}
        </RouterLink>
        <a
          v-else
          :href="secondaryTo"
          class="inline-flex items-center rounded-lg border border-white/15 bg-white/5 px-4 py-2 text-sm text-slate-100 transition hover:border-cyan-300/45 hover:bg-cyan-500/10"
        >
          {{ secondaryLabel }}
        </a>
      </div>

      <div
        v-if="metrics.length"
        class="grid gap-3"
        :class="isTranslateVariant ? 'md:grid-cols-3' : 'sm:grid-cols-2 lg:grid-cols-3'"
      >
        <article
          v-for="metric in metrics"
          :key="metric.label"
          class="rounded-xl border border-cyan-200/20 bg-slate-950/55 p-4"
        >
          <div class="text-[11px] uppercase tracking-[0.22em] text-cyan-100/60">{{ metric.label }}</div>
          <div class="mt-2 text-base font-semibold text-cyan-100 sm:text-lg">{{ metric.value }}</div>
          <p class="mt-2 text-xs leading-5 text-slate-300">{{ metric.description }}</p>
        </article>
      </div>
    </div>
  </section>
</template>
