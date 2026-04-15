<script setup>
import { computed } from 'vue';
import hermesAvatar from '../../assets/brands/hermes-avatar.png';

const props = defineProps({
  agents: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const onlineCount = computed(() => props.agents.filter(a => a.configured).length);
const totalCount = computed(() => props.agents.length);

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
        v-for="agent in agents"
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
            <svg
              v-if="agent.key === 'openclaw'"
              class="openclaw-mark h-7 w-7"
              viewBox="0 0 120 120"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true"
            >
              <path
                d="M60 10C30 10 15 35 15 55C15 75 30 95 45 100L45 110H55V100C55 100 60 102 65 100V110H75V100C90 95 105 75 105 55C105 35 90 10 60 10Z"
                fill="url(#openclaw-gradient)"
                class="claw-body"
              />
              <path
                d="M20 45C5 40 0 50 5 60C10 70 20 65 25 55C28 48 25 45 20 45Z"
                fill="url(#openclaw-gradient)"
                class="claw-left"
              />
              <path
                d="M100 45C115 40 120 50 115 60C110 70 100 65 95 55C92 48 95 45 100 45Z"
                fill="url(#openclaw-gradient)"
                class="claw-right"
              />
              <path d="M45 15Q35 5 30 8" stroke="#ff6b73" stroke-width="2.5" stroke-linecap="round" class="antenna" />
              <path d="M75 15Q85 5 90 8" stroke="#ff6b73" stroke-width="2.5" stroke-linecap="round" class="antenna" />
              <circle cx="45" cy="35" r="6" fill="#050810" />
              <circle cx="75" cy="35" r="6" fill="#050810" />
              <circle cx="46" cy="34" r="2.2" fill="#00e5cc" class="eye-glow" />
              <circle cx="76" cy="34" r="2.2" fill="#00e5cc" class="eye-glow" />
              <defs>
                <linearGradient id="openclaw-gradient" x1="10%" y1="5%" x2="100%" y2="95%">
                  <stop offset="0%" stop-color="#ffb4bf" />
                  <stop offset="100%" stop-color="#ff4d57" />
                </linearGradient>
              </defs>
            </svg>
            <img
              v-else-if="agent.key === 'hermes'"
              :src="hermesAvatar"
              alt="Hermes"
              class="hermes-mark h-7 w-7 object-contain"
            />
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

<style scoped>
.openclaw-mark {
  animation: openclaw-float 4s ease-in-out infinite;
  filter: drop-shadow(0 0 10px rgba(255, 96, 120, 0.28));
}

.openclaw-mark .eye-glow {
  animation: openclaw-blink 3s ease-in-out infinite;
}

.openclaw-mark .antenna {
  animation: openclaw-wiggle 2s ease-in-out infinite;
  transform-origin: center;
}

.openclaw-mark .claw-left {
  animation: openclaw-snap-left 4s ease-in-out infinite;
  transform-origin: 25% 45%;
}

.openclaw-mark .claw-right {
  animation: openclaw-snap-right 4s ease-in-out 0.2s infinite;
  transform-origin: 75% 45%;
}

.hermes-mark {
  filter: drop-shadow(0 0 8px rgba(95, 195, 255, 0.25));
  image-rendering: -webkit-optimize-contrast;
}

@keyframes openclaw-float {
  0%, 100% {
    transform: translateY(0);
  }

  50% {
    transform: translateY(-2px);
  }
}

@keyframes openclaw-blink {
  0%, 90%, 100% {
    opacity: 1;
  }

  95% {
    opacity: 0.35;
  }
}

@keyframes openclaw-wiggle {
  0%, 100% {
    transform: rotate(0deg);
  }

  25% {
    transform: rotate(-2deg);
  }

  75% {
    transform: rotate(2deg);
  }
}

@keyframes openclaw-snap-left {
  0%, 85%, 100% {
    transform: rotate(0deg);
  }

  90% {
    transform: rotate(-6deg);
  }

  95% {
    transform: rotate(0deg);
  }
}

@keyframes openclaw-snap-right {
  0%, 85%, 100% {
    transform: rotate(0deg);
  }

  90% {
    transform: rotate(6deg);
  }

  95% {
    transform: rotate(0deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .openclaw-mark,
  .openclaw-mark .eye-glow,
  .openclaw-mark .antenna,
  .openclaw-mark .claw-left,
  .openclaw-mark .claw-right {
    animation: none;
  }
}
</style>
