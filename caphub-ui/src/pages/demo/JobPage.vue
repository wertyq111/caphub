<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useJobPolling } from '../../composables/useJobPolling';
import JobTimeline from '../../components/demo/JobTimeline.vue';
import AppLoader from '../../components/shared/AppLoader.vue';
import AppErrorState from '../../components/shared/AppErrorState.vue';

const route = useRoute();
const router = useRouter();

const jobId = computed(() => String(route.params.jobId ?? ''));
const pollingEnabled = computed(() => jobId.value.length > 0);
const jobQuery = useJobPolling(jobId, pollingEnabled);

const statusMeta = {
  pending: {
    label: '等待受理',
    tone: 'text-amber-200',
    dot: 'bg-amber-300',
    summary: '任务已经创建，系统正在准备翻译链路。',
  },
  queued: {
    label: '排队中',
    tone: 'text-sky-200',
    dot: 'bg-sky-300',
    summary: '任务已进入队列，正在等待当前长文本接口处理。',
  },
  processing: {
    label: '翻译处理中',
    tone: 'text-cyan-200',
    dot: 'bg-cyan-300',
    summary: '系统正在执行翻译，并持续轮询任务状态。',
  },
  succeeded: {
    label: '已完成',
    tone: 'text-emerald-200',
    dot: 'bg-emerald-300',
    summary: '翻译任务已经完成，可以直接进入结果页查看内容。',
  },
  failed: {
    label: '处理失败',
    tone: 'text-rose-200',
    dot: 'bg-rose-300',
    summary: '任务没有成功完成，请查看失败原因后再决定是否重试。',
  },
  cancelled: {
    label: '已取消',
    tone: 'text-slate-300',
    dot: 'bg-slate-400',
    summary: '任务已经结束，不会继续处理。',
  },
};

const inputTypeLabelMap = {
  plain_text: '纯文本',
  article_payload: 'JSON 文本',
};

const status = computed(() => jobQuery.data.value?.status ?? 'pending');
const statusInfo = computed(() => statusMeta[status.value] ?? {
  label: '状态更新中',
  tone: 'text-slate-200',
  dot: 'bg-slate-300',
  summary: '任务状态正在刷新，请稍候。',
});
const isFinished = computed(() => ['succeeded', 'failed', 'cancelled'].includes(status.value));
const isSucceeded = computed(() => status.value === 'succeeded');
const isFailed = computed(() => status.value === 'failed');
const refreshHint = computed(() => (isFinished.value ? '自动刷新已停止' : '页面每 1.5 秒自动刷新一次'));
const inputTypeLabel = computed(() => inputTypeLabelMap[jobQuery.data.value?.input_type] ?? '未知类型');
const languageDirection = computed(() => {
  const source = jobQuery.data.value?.source_lang ?? '--';
  const target = jobQuery.data.value?.target_lang ?? '--';

  return `${source} → ${target}`;
});

function formatTimestamp(value) {
  if (!value) {
    return '—';
  }

  try {
    return new Intl.DateTimeFormat('zh-CN', {
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
    }).format(new Date(value));
  } catch {
    return value;
  }
}

const startedAtLabel = computed(() => formatTimestamp(jobQuery.data.value?.started_at));
const finishedAtLabel = computed(() => formatTimestamp(jobQuery.data.value?.finished_at));
const failureReason = computed(() => jobQuery.data.value?.error?.reason ?? '');

function goToResult() {
  router.push(`/demo/results/${jobId.value}`);
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between rounded-[var(--np-radius-xl)] np-glass px-4 py-2.5">
      <div class="flex items-center gap-3">
        <span class="np-dot-pulse h-2 w-2 rounded-full bg-[var(--np-primary)] text-[var(--np-primary)]" />
        <span class="np-font-mono text-xs font-medium uppercase tracking-[0.2em] text-[var(--np-primary)]" style="opacity: 0.7;">
          Job Tracking
        </span>
        <span class="text-xs text-[var(--np-on-surface-variant)]">任务轮询 · 状态追踪 · 结果入口</span>
      </div>
      <RouterLink
        to="/demo/translate"
        class="text-xs text-[var(--np-on-surface-variant)] no-underline transition hover:text-[var(--np-primary)]"
      >
        ← 返回翻译台
      </RouterLink>
    </div>

    <div class="text-center">
      <h1
        class="np-font-display text-3xl font-bold text-[var(--np-on-surface)] sm:text-4xl"
        style="text-shadow: 0 0 50px rgba(153,247,255,0.25);"
      >
        翻译任务追踪
      </h1>
      <p class="mt-2 text-sm text-[var(--np-on-surface-variant)]">
        {{ statusInfo.summary }}
      </p>
    </div>

    <AppLoader v-if="jobQuery.isLoading.value" />
    <AppErrorState
      v-else-if="jobQuery.isError.value"
      :message="jobQuery.error.value?.message ?? '任务状态加载失败，请稍后再试。'"
    />
    <template v-else>
      <div class="mx-auto grid max-w-6xl gap-4 xl:grid-cols-[minmax(0,1.4fr)_300px]">
        <section class="space-y-4">
          <section class="rounded-[var(--np-radius-2xl)] np-glass-feature p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.28em] text-[var(--np-primary)]" style="opacity: 0.7;">
                  Job Summary
                </p>
                <h2 class="mt-2 np-font-display text-xl font-semibold text-[var(--np-on-surface)]">
                  任务概览
                </h2>
                <p class="mt-2 break-all text-sm text-[var(--np-on-surface-variant)]">
                  Job UUID: {{ jobId }}
                </p>
              </div>

              <div
                class="inline-flex items-center gap-2 rounded-full np-glass px-3 py-1.5 text-sm"
                :class="statusInfo.tone"
              >
                <span class="inline-flex h-2.5 w-2.5 rounded-full" :class="statusInfo.dot" />
                <span class="font-medium">{{ statusInfo.label }}</span>
              </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <div class="rounded-[var(--np-radius-lg)] np-glass-strong p-4">
                <div class="text-xs uppercase tracking-[0.22em] text-[var(--np-on-surface-variant)]">输入类型</div>
                <div class="mt-2 np-font-display text-lg font-semibold text-[var(--np-on-surface)]">{{ inputTypeLabel }}</div>
              </div>
              <div class="rounded-[var(--np-radius-lg)] np-glass-strong p-4">
                <div class="text-xs uppercase tracking-[0.22em] text-[var(--np-on-surface-variant)]">语言方向</div>
                <div class="mt-2 np-font-display text-lg font-semibold text-[var(--np-on-surface)]">{{ languageDirection }}</div>
              </div>
              <div class="rounded-[var(--np-radius-lg)] np-glass-strong p-4">
                <div class="text-xs uppercase tracking-[0.22em] text-[var(--np-on-surface-variant)]">开始时间</div>
                <div class="mt-2 text-sm font-medium text-[var(--np-on-surface)]">{{ startedAtLabel }}</div>
              </div>
              <div class="rounded-[var(--np-radius-lg)] np-glass-strong p-4">
                <div class="text-xs uppercase tracking-[0.22em] text-[var(--np-on-surface-variant)]">结束时间</div>
                <div class="mt-2 text-sm font-medium text-[var(--np-on-surface)]">{{ finishedAtLabel }}</div>
              </div>
            </div>
          </section>

          <JobTimeline :status="status" />
        </section>

        <aside class="space-y-4">
          <section class="rounded-[var(--np-radius-xl)] np-glass-feature p-4">
            <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.28em] text-[var(--np-primary)]" style="opacity: 0.7;">
              Live Status
            </p>
            <h2 class="mt-2 np-font-display text-lg font-semibold text-[var(--np-on-surface)]">实时状态说明</h2>
            <p class="mt-3 text-sm leading-6 text-[var(--np-on-surface-variant)]">
              {{ statusInfo.summary }}
            </p>
            <div class="mt-4 rounded-[var(--np-radius-lg)] np-glass p-3 text-xs text-[var(--np-on-surface-variant)]">
              {{ refreshHint }}
            </div>
          </section>

          <section
            v-if="isFailed"
            class="rounded-[var(--np-radius-xl)] border border-rose-300/20 bg-[rgba(251,113,133,0.08)] p-4"
          >
            <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.28em] text-rose-200" style="opacity: 0.8;">
              Failure Reason
            </p>
            <h2 class="mt-2 np-font-display text-lg font-semibold text-rose-100">失败原因</h2>
            <p class="mt-3 text-sm leading-6 text-rose-100/85">
              {{ failureReason || '后端没有返回额外失败原因。' }}
            </p>
          </section>

          <section class="rounded-[var(--np-radius-xl)] np-glass-strong p-4">
            <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.28em] text-[var(--np-primary)]" style="opacity: 0.7;">
              Next Step
            </p>
            <h2 class="mt-2 np-font-display text-lg font-semibold text-[var(--np-on-surface)]">下一步操作</h2>
            <div class="mt-4 flex flex-col gap-3">
              <button
                v-if="isSucceeded"
                class="np-btn-cta w-full !py-3 !text-sm"
                @click="goToResult"
              >
                查看翻译结果
              </button>
              <RouterLink
                to="/demo/translate"
                class="rounded-full np-glass px-4 py-3 text-center text-sm font-medium text-[var(--np-on-surface)] no-underline transition hover:text-[var(--np-primary)]"
              >
                返回翻译工作台
              </RouterLink>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>
