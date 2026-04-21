<script setup>
import { computed } from 'vue';
import AgentGlyph from './AgentGlyph.vue';

const props = defineProps({
  agents: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const visibleAgents = computed(() => props.agents.filter(agent => agent.key !== 'github_models'));
const onlineCount = computed(() => visibleAgents.value.filter(a => a.configured).length);
const totalCount = computed(() => visibleAgents.value.length);

function getAccent(agent) {
  if (agent.key === 'hermes') return 'secondary';
  return 'primary';
}

function getLoadPercent(agent) {
  const total = agent.stats_24h?.total_calls ?? 0;
  return Math.min(100, Math.max(5, total * 2));
}

function getTypeLabel(agent) {
  if (agent.key === 'hermes') return 'Chat · 翻译';
  return 'API · 翻译';
}
</script>

<template>
  <aside class="flex flex-col gap-4">
    <!-- Header -->
    <div class="rounded-[var(--np-radius-xl)] np-glass-feature p-4">
      <div class="flex items-center gap-2">
        <span class="text-lg">✦</span>
        <h2 class="np-font-display text-xl font-semibold text-[var(--np-on-surface)]">代理网络 Nexus</h2>
      </div>
      <p class="mt-2 text-sm text-[var(--np-on-surface-variant)]">
        在线节点: <span class="np-font-mono text-[var(--np-primary)]">{{ onlineCount }}/{{ totalCount }}</span>
      </p>
    </div>

    <!-- Agent Cards -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 2" :key="i" class="h-28 animate-pulse rounded-[var(--np-radius-lg)] np-glass" />
    </div>

    <div v-else class="space-y-3">
      <article
        v-for="agent in visibleAgents"
        :key="agent.key"
        class="np-ghost-border np-card-hover rounded-[var(--np-radius-xl)] np-glass-strong p-4"
      >
        <div class="flex items-start gap-3">
          <!-- Avatar -->
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-[var(--np-radius-md)] text-xl"
            :class="getAccent(agent) === 'secondary'
              ? 'bg-[rgba(97,193,255,0.14)] text-[#61c1ff]'
              : 'bg-[rgba(153,247,255,0.12)] text-[var(--np-primary)]'"
          >
            <AgentGlyph :provider-key="agent.key" size-class="h-7 w-7" />
          </div>

          <!-- Info -->
          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between">
              <h3 class="np-font-display text-base font-semibold text-[var(--np-on-surface)]">
                {{ agent.name }}
              </h3>
              <span
                v-if="agent.configured"
                class="np-sweep rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider"
                :class="agent.active
                  ? 'bg-[rgba(74,222,128,0.15)] text-[var(--np-success)]'
                  : 'bg-[rgba(153,247,255,0.1)] text-[var(--np-primary)]'"
              >
                {{ agent.active ? '活跃' : '待机' }}
              </span>
              <span v-else class="rounded-full bg-[rgba(248,113,113,0.12)] px-2 py-0.5 text-[10px] font-medium text-[var(--np-error)]">
                未配置
              </span>
            </div>

            <p class="mt-1 text-xs text-[var(--np-on-surface-variant)]">{{ getTypeLabel(agent) }}</p>

            <!-- Stats row -->
            <div class="mt-3 flex items-center gap-4 text-xs">
              <div>
                <span class="text-[var(--np-on-surface-variant)]">调用 </span>
                <span class="np-font-mono font-medium text-[var(--np-primary)]">{{ agent.stats_24h?.total_calls ?? 0 }}</span>
              </div>
              <div>
                <span class="text-[var(--np-on-surface-variant)]">响应耗时 </span>
                <span class="np-font-mono font-medium text-[var(--np-on-surface)]">{{ agent.stats_24h?.avg_latency_ms ?? 0 }}ms</span>
              </div>
            </div>

            <!-- Load bar -->
            <div class="mt-2 h-1.5 rounded-full bg-[var(--np-surface-bright)]">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="getAccent(agent) === 'secondary'
                  ? 'bg-gradient-to-r from-[var(--np-secondary-dim)] to-[var(--np-secondary)]'
                  : 'bg-gradient-to-r from-[var(--np-primary-dim)] to-[var(--np-primary)]'"
                :style="{ width: getLoadPercent(agent) + '%' }"
              />
            </div>
          </div>
        </div>
      </article>
    </div>
  </aside>
</template>
