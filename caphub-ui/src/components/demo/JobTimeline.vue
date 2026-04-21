<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    default: 'pending',
  },
});

const stages = ['pending', 'queued', 'processing', 'succeeded'];
const stageLabels = {
  pending: '等待受理',
  queued: '排队中',
  processing: '翻译处理中',
  succeeded: '已完成',
  failed: '处理中断',
  cancelled: '流程取消',
};
const statusToStageIndex = {
  pending: 0,
  queued: 1,
  processing: 2,
  succeeded: 3,
  failed: 2,
  cancelled: 1,
};

function isDone(current, stage) {
  const currentIndex = statusToStageIndex[current] ?? 0;

  return stages.indexOf(stage) <= currentIndex;
}

const currentStageLabel = computed(() => stageLabels[props.status] ?? '状态更新中');
const progressPercent = computed(() => {
  const percentages = [18, 42, 74, 100];
  const index = statusToStageIndex[props.status] ?? 0;

  return percentages[index] ?? 18;
});
const progressToneClass = computed(() => {
  if (props.status === 'failed') {
    return 'from-rose-400 via-rose-300 to-rose-500';
  }

  if (props.status === 'succeeded') {
    return 'from-emerald-400 via-emerald-300 to-emerald-500';
  }

  return 'from-sky-400 via-cyan-300 to-sky-500';
});
const progressLabel = computed(() => {
  if (props.status === 'failed') {
    return '处理中断';
  }

  if (props.status === 'cancelled') {
    return '流程取消';
  }

  return '整体进度';
});
</script>

<template>
  <section class="rounded-[var(--np-radius-xl)] np-glass-feature p-4 sm:p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.28em] text-[var(--np-primary)]" style="opacity: 0.7;">
          任务时间线
        </p>
        <h2 class="mt-2 np-font-display text-lg font-semibold text-[var(--np-on-surface)]">
          任务处理时间线
        </h2>
      </div>
      <div class="rounded-full np-glass px-3 py-1.5 text-xs text-[var(--np-on-surface-variant)]">
        当前阶段：<span class="font-medium text-[var(--np-on-surface)]">{{ currentStageLabel }}</span>
      </div>
    </div>

    <div class="mt-4 rounded-[var(--np-radius-lg)] np-glass-strong p-4">
      <div class="flex items-center justify-between gap-3 text-xs">
        <span class="uppercase tracking-[0.22em] text-[var(--np-on-surface-variant)]">{{ progressLabel }}</span>
        <span class="np-font-mono text-[var(--np-on-surface)]">{{ progressPercent }}%</span>
      </div>
      <div class="mt-3 h-3 overflow-hidden rounded-full bg-[var(--np-surface-bright)]">
        <div
          class="job-progress-bar relative h-full rounded-full bg-gradient-to-r transition-all duration-700 ease-out"
          :class="[
            progressToneClass,
            status === 'processing' || status === 'queued' ? 'job-progress-bar--animated' : '',
          ]"
          :style="{ width: `${progressPercent}%` }"
        />
      </div>
    </div>

    <ol class="mt-4 grid gap-3 md:grid-cols-4">
      <li
        v-for="stage in stages"
        :key="stage"
        class="rounded-[var(--np-radius-md)] border px-3 py-3 text-sm transition"
        :class="isDone(status, stage)
          ? 'border-emerald-300/30 bg-emerald-300/12 text-emerald-50'
          : 'border-white/10 bg-slate-950/30 text-slate-400'"
      >
        <div class="flex items-center gap-2">
          <span
            class="inline-flex h-2.5 w-2.5 rounded-full"
            :class="isDone(status, stage) ? 'bg-emerald-300' : 'bg-slate-600'"
          />
          <span class="font-medium">{{ stageLabels[stage] }}</span>
        </div>
        <p class="mt-2 text-xs uppercase tracking-[0.16em] opacity-70">
          {{ stage }}
        </p>
      </li>
    </ol>
  </section>
</template>

<style scoped>
.job-progress-bar::after {
  content: '';
  position: absolute;
  inset: 0;
  opacity: 0;
  background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.26) 45%, transparent 100%);
}

.job-progress-bar--animated::after {
  opacity: 1;
  animation: job-progress-sweep 1.6s linear infinite;
}

@keyframes job-progress-sweep {
  0% {
    transform: translateX(-100%);
  }

  100% {
    transform: translateX(220%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .job-progress-bar {
    transition: none;
  }

  .job-progress-bar--animated::after {
    animation: none;
  }
}
</style>
