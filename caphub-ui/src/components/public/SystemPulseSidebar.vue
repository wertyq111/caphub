<script setup>
import { computed } from 'vue';

const props = defineProps({
  throughput: { type: Array, default: () => [] },
  recentLogs: { type: Array, default: () => [] },
  jobs24h: { type: Object, default: () => ({ total: 0, succeeded: 0, processing: 0 }) },
  loading: { type: Boolean, default: false },
});

const maxRequests = computed(() => {
  if (!props.throughput.length) return 1;
  return Math.max(...props.throughput.map(t => t.requests), 1);
});

const totalThroughput = computed(() =>
  props.throughput.reduce((sum, t) => sum + t.requests, 0),
);

function formatTime(isoString) {
  if (!isoString) return '--:--:--';
  const d = new Date(isoString);
  return d.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function formatHour(hourStr) {
  if (!hourStr) return '';
  const parts = hourStr.split(' ');
  return parts[1]?.substring(0, 5) ?? '';
}

function statusIcon(status) {
  if (status === 'succeeded') return 'OK';
  if (status === 'failed') return 'ERR';
  return 'SYS';
}

function statusColor(status) {
  if (status === 'succeeded') return 'var(--np-success)';
  if (status === 'failed') return 'var(--np-error)';
  return 'var(--np-primary)';
}
</script>

<template>
  <aside class="flex flex-col gap-4">
    <!-- Header -->
    <div class="rounded-[var(--np-radius-xl)] np-glass-feature p-4">
      <div class="flex items-center gap-2">
        <span class="text-lg text-[var(--np-tertiary)]">📈</span>
        <h2 class="np-font-display text-xl font-semibold text-[var(--np-on-surface)]">系统脉搏</h2>
      </div>
    </div>

    <!-- Throughput -->
    <div class="rounded-[var(--np-radius-xl)] np-glass-strong p-4">
      <div class="flex items-baseline justify-between">
        <span class="text-xs text-[var(--np-on-surface-variant)]">总合吐量</span>
        <span class="np-font-mono text-2xl font-bold text-[var(--np-on-surface)]">
          {{ totalThroughput }}
          <span class="text-xs font-normal text-[var(--np-on-surface-variant)]">req/12h</span>
        </span>
      </div>

      <!-- Bar chart -->
      <div v-if="loading" class="mt-4 h-24 animate-pulse rounded bg-[var(--np-surface-bright)]" />
      <div v-else class="mt-4 flex h-24 items-end gap-1">
        <div
          v-for="(bar, idx) in throughput"
          :key="idx"
          class="relative flex-1 rounded-t transition-all duration-500"
          :style="{
            height: Math.max(4, (bar.requests / maxRequests) * 100) + '%',
            background: `linear-gradient(180deg, var(--np-primary), rgba(153, 247, 255, 0.15))`,
          }"
        >
          <div class="absolute inset-x-0 top-0 h-1 rounded-full bg-[var(--np-primary)] blur-[2px]" />
        </div>
      </div>
      <div v-if="throughput.length" class="mt-2 flex justify-between text-[10px] text-[var(--np-on-surface-variant)]">
        <span class="np-font-mono">{{ formatHour(throughput[0]?.hour) }}</span>
        <span class="np-font-mono">{{ formatHour(throughput[throughput.length - 1]?.hour) }}</span>
      </div>
    </div>

    <!-- Real-time Logs -->
    <div class="flex-1 rounded-[var(--np-radius-xl)] np-glass-strong p-4">
      <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-[var(--np-on-surface-variant)]">
        实时日志点距趋势
      </h3>

      <div v-if="loading" class="space-y-2">
        <div v-for="i in 6" :key="i" class="h-4 animate-pulse rounded bg-[var(--np-surface-bright)]" />
      </div>

      <div v-else class="max-h-[280px] space-y-1 overflow-y-auto pr-1">
        <div
          v-for="log in recentLogs"
          :key="log.id"
          class="flex gap-2 rounded px-2 py-1 text-[11px] leading-5 transition hover:bg-[var(--np-surface-bright)]"
        >
          <span class="np-font-mono shrink-0 text-[var(--np-on-surface-variant)]">
            [{{ formatTime(log.time) }}]
          </span>
          <span
            class="np-font-mono shrink-0 font-semibold"
            :style="{ color: statusColor(log.status) }"
          >
            {{ statusIcon(log.status) }}
          </span>
          <span class="min-w-0 truncate text-[var(--np-on-surface-variant)]">
            {{ log.agent ?? 'system' }}
            <span v-if="log.duration_ms" class="np-font-mono text-[var(--np-primary-dim)]">
              ({{ log.duration_ms }}ms)
            </span>
          </span>
        </div>

        <div v-if="!recentLogs.length" class="py-6 text-center text-xs text-[var(--np-on-surface-variant)]">
          暂无日志数据
        </div>
      </div>
    </div>
  </aside>
</template>
