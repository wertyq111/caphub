<script setup>
import { RouterLink } from 'vue-router';

defineProps({
  loading: { type: Boolean, default: false },
  activeProvider: { type: String, default: '' },
});

const cards = [
  {
    key: 'translator',
    icon: '文A',
    title: '神经翻译器',
    description: '高维数据文本转换矩阵，支持多样态解析。',
    status: '在线',
    statusColor: 'success',
    to: '/demo/translate',
    accent: 'primary',
  },
  {
    key: 'monitor',
    icon: '⊞',
    title: '任务监控器',
    description: '实时任务执行跟踪，节点健康度分析。',
    status: '在线',
    statusColor: 'success',
    to: null,
    accent: 'tertiary',
  },
  {
    key: 'glossary',
    icon: '☰',
    title: '术语库节点',
    description: '核心数据定义网络，语义统一规范。',
    status: '同步中',
    statusColor: 'warning',
    to: null,
    accent: 'secondary',
  },
];

function accentBg(accent) {
  if (accent === 'tertiary') return 'rgba(244, 114, 182, 0.12)';
  if (accent === 'secondary') return 'rgba(172, 137, 255, 0.12)';
  return 'rgba(153, 247, 255, 0.12)';
}

function accentColor(accent) {
  if (accent === 'tertiary') return 'var(--np-tertiary)';
  if (accent === 'secondary') return 'var(--np-secondary)';
  return 'var(--np-primary)';
}

function accentGlow(accent) {
  if (accent === 'tertiary') return '0 0 32px rgba(244, 114, 182, 0.2)';
  if (accent === 'secondary') return '0 0 32px rgba(172, 137, 255, 0.2)';
  return '0 0 32px rgba(153, 247, 255, 0.2)';
}

function statusDotColor(statusColor) {
  if (statusColor === 'warning') return 'var(--np-warning)';
  return 'var(--np-success)';
}
</script>

<template>
  <section>
    <h2 class="np-font-display mb-5 text-center text-2xl font-semibold text-[var(--np-on-surface)] lg:text-3xl">
      系统核心控制台
    </h2>

    <div v-if="loading" class="grid gap-4 md:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-44 animate-pulse rounded-[var(--np-radius-xl)] np-glass" />
    </div>

    <div v-else class="grid gap-4 md:grid-cols-3">
      <component
        v-for="card in cards"
        :key="card.key"
        :is="card.to ? RouterLink : 'div'"
        :to="card.to || undefined"
        class="np-ghost-border np-card-hover group rounded-[var(--np-radius-xl)] np-glass-feature p-5 no-underline"
      >
        <!-- Icon -->
        <div
          class="flex h-12 w-12 items-center justify-center rounded-[var(--np-radius-md)] text-lg font-bold"
          :style="{
            background: accentBg(card.accent),
            color: accentColor(card.accent),
            boxShadow: accentGlow(card.accent),
          }"
        >
          {{ card.icon }}
        </div>

        <!-- Title -->
        <h3 class="np-font-display mt-4 text-lg font-semibold text-[var(--np-on-surface)]">
          {{ card.title }}
        </h3>

        <!-- Description -->
        <p class="mt-2 text-sm leading-6 text-[var(--np-on-surface-variant)]">
          {{ card.description }}
        </p>

        <!-- Footer -->
        <div class="mt-4 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span
              class="np-dot-pulse h-2 w-2 rounded-full"
              :style="{ backgroundColor: statusDotColor(card.statusColor), color: statusDotColor(card.statusColor) }"
            />
            <span class="text-xs" :style="{ color: statusDotColor(card.statusColor) }">
              {{ card.status }}
            </span>
          </div>
          <span
            v-if="card.to"
            class="text-sm transition-transform group-hover:translate-x-1"
            :style="{ color: accentColor(card.accent) }"
          >
            →
          </span>
        </div>
      </component>
    </div>
  </section>
</template>
