<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

const route = useRoute();

const navigationItems = [
  { label: '首页', to: '/' },
  { label: '翻译演示', to: '/demo/translate' },
];

const isTranslatePage = computed(() => route.path.startsWith('/demo/translate'));
</script>

<template>
  <header class="sticky top-0 z-30 border-b border-white/10 bg-slate-950/80 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
      <RouterLink to="/" class="flex items-center gap-3 text-white">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-sky-400/30 bg-sky-400/15 text-lg font-semibold text-sky-200 shadow-[0_0_30px_rgba(14,165,233,0.18)]">
          C
        </div>
        <div>
          <div class="text-xs uppercase tracking-[0.35em] text-sky-200/70">Caphub</div>
          <div class="text-sm font-medium text-slate-200">AI 控制矩阵</div>
        </div>
      </RouterLink>

      <nav class="hidden items-center gap-2 md:flex">
        <RouterLink
          v-for="item in navigationItems"
          :key="item.to"
          :to="item.to"
          class="rounded-full px-4 py-2 text-sm transition"
          :class="route.path === item.to
            ? 'bg-white/12 text-white'
            : 'text-slate-300 hover:bg-white/6 hover:text-white'"
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <RouterLink
        to="/demo/translate"
        class="inline-flex items-center rounded-full border border-sky-300/25 bg-sky-400 px-4 py-2 text-sm font-medium text-slate-950 shadow-[0_10px_40px_rgba(56,189,248,0.35)] transition hover:-translate-y-0.5 hover:bg-sky-300"
      >
        {{ isTranslatePage ? '返回工作台' : '进入翻译工作台' }}
      </RouterLink>
    </div>
  </header>
</template>
