<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { submitAsyncTranslation } from '../../api/translation';
import { useJobPolling } from '../../composables/useJobPolling';
import JobTimeline from '../../components/demo/JobTimeline.vue';
import AgentGlyph from '../../components/public/AgentGlyph.vue';
import AppLoader from '../../components/shared/AppLoader.vue';
import AppErrorState from '../../components/shared/AppErrorState.vue';

const route = useRoute();
const router = useRouter();

const jobId = computed(() => String(route.params.jobId ?? ''));
const pollingEnabled = computed(() => jobId.value.length > 0);
const jobQuery = useJobPolling(jobId, pollingEnabled);
const retrying = ref(false);
const retryError = ref('');
const animatedTranslatedText = ref('');
let typingTimer = null;

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
const providerLabelMap = {
  openclaw: 'OpenClaw',
  hermes: 'Hermes',
  github_models: 'Copilot',
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
const showRunningIndicator = computed(() => !isFinished.value);
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

function normalizeDocumentValue(value) {
  return typeof value === 'string' ? value.trim() : '';
}

function flattenDocument(document, inputType) {
  if (!document || typeof document !== 'object') {
    return '';
  }

  if (inputType === 'plain_text') {
    return normalizeDocumentValue(document.text);
  }

  const sections = [
    ['标题', normalizeDocumentValue(document.title)],
    ['摘要', normalizeDocumentValue(document.summary)],
    ['正文', normalizeDocumentValue(document.body)],
  ].filter(([, value]) => value !== '');

  return sections.map(([label, value]) => `${label}\n${value}`).join('\n\n');
}

function clearTypingAnimation() {
  if (typingTimer) {
    window.clearInterval(typingTimer);
    typingTimer = null;
  }
}

function startTypingAnimation(text) {
  clearTypingAnimation();

  if (!text) {
    animatedTranslatedText.value = '';
    return;
  }

  animatedTranslatedText.value = '';

  const totalLength = text.length;
  const step = Math.max(1, Math.ceil(totalLength / 140));
  let cursor = 0;

  typingTimer = window.setInterval(() => {
    cursor = Math.min(totalLength, cursor + step);
    animatedTranslatedText.value = text.slice(0, cursor);

    if (cursor >= totalLength) {
      clearTypingAnimation();
    }
  }, 18);
}

const startedAtLabel = computed(() => formatTimestamp(jobQuery.data.value?.started_at));
const finishedAtLabel = computed(() => formatTimestamp(jobQuery.data.value?.finished_at));
const failureReason = computed(() => jobQuery.data.value?.error?.reason ?? '');
const translationProviderKey = computed(() => jobQuery.data.value?.translation_provider ?? '');
const translationProviderLabel = computed(() => providerLabelMap[translationProviderKey.value] ?? '当前接口');
const translationAgent = computed(() => jobQuery.data.value?.translation_agent ?? '等待派发');
const sourcePreview = computed(() => flattenDocument(
  jobQuery.data.value?.source_document ?? {},
  jobQuery.data.value?.input_type,
));
const translatedPreview = computed(() => flattenDocument(
  jobQuery.data.value?.translated_document ?? {},
  jobQuery.data.value?.input_type,
));
const translatedPreviewDisplay = computed(() => (
  isSucceeded.value ? animatedTranslatedText.value : translatedPreview.value
));
const robotState = computed(() => {
  if (isSucceeded.value) {
    return 'success';
  }

  if (isFailed.value) {
    return 'failed';
  }

  return 'working';
});

watch(
  () => [isSucceeded.value, translatedPreview.value],
  ([succeeded, translatedText]) => {
    if (!succeeded) {
      clearTypingAnimation();
      animatedTranslatedText.value = translatedText || '';
      return;
    }

    startTypingAnimation(translatedText);
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  clearTypingAnimation();
});

function buildRetryPayload() {
  const data = jobQuery.data.value ?? {};
  const sourceDocument = data.source_document ?? {};

  return {
    input_type: data.input_type ?? 'plain_text',
    document_type: data.document_type ?? 'chemical_news',
    source_lang: data.source_lang ?? 'zh',
    target_lang: data.target_lang ?? 'en',
    content: data.input_type === 'plain_text'
      ? {
        text: sourceDocument.text ?? '',
      }
      : {
        title: sourceDocument.title ?? '',
        summary: sourceDocument.summary ?? '',
        body: sourceDocument.body ?? '',
      },
  };
}

async function retryTranslation() {
  retrying.value = true;
  retryError.value = '';

  try {
    const data = await submitAsyncTranslation(buildRetryPayload());
    const nextJobUuid = data.job_uuid ?? '';

    if (!nextJobUuid) {
      throw new Error('未返回新的任务 UUID。');
    }

    await router.push(`/demo/jobs/${nextJobUuid}`);
  } catch (error) {
    retryError.value = error?.response?.data?.message ?? error?.message ?? '重新翻译失败，请稍后再试。';
  } finally {
    retrying.value = false;
  }
}

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
          任务追踪
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
                  任务概览
                </p>
                <h2 class="mt-2 np-font-display text-xl font-semibold text-[var(--np-on-surface)]">
                  任务概览
                </h2>
                <p class="mt-2 break-all text-sm text-[var(--np-on-surface-variant)]">
                  任务 UUID：{{ jobId }}
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

          <section class="rounded-[var(--np-radius-xl)] np-glass-feature p-4 sm:p-5">
            <div class="grid gap-4 lg:grid-cols-2">
              <div class="min-w-0">
                <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.28em] text-[var(--np-primary)]" style="opacity: 0.7;">
                  原文内容
                </p>
                <h2 class="mt-2 np-font-display text-lg font-semibold text-[var(--np-on-surface)]">翻译正文</h2>
                <div class="mt-4 max-h-[22rem] overflow-auto rounded-[var(--np-radius-lg)] border border-white/10 bg-[rgba(13,14,23,0.55)] p-4">
                  <p
                    v-if="sourcePreview"
                    class="whitespace-pre-wrap break-all text-sm leading-7 text-[var(--np-on-surface)]"
                  >
                    {{ sourcePreview }}
                  </p>
                  <p v-else class="text-sm text-[var(--np-on-surface-variant)]">当前任务没有可展示的原文内容。</p>
                </div>
              </div>

              <div class="min-w-0">
                <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.28em] text-[var(--np-primary)]" style="opacity: 0.7;">
                  译文输出
                </p>
                <h2 class="mt-2 np-font-display text-lg font-semibold text-[var(--np-on-surface)]">翻译后内容</h2>
                <div class="relative mt-4 max-h-[22rem] overflow-auto rounded-[var(--np-radius-lg)] border border-white/10 bg-[rgba(13,14,23,0.55)] p-4">
                  <span
                    v-if="showRunningIndicator"
                    aria-hidden="true"
                    class="job-slash-spinner absolute right-4 top-4 inline-flex text-lg font-semibold text-[var(--np-primary)]"
                  >/</span>
                  <p
                    v-if="translatedPreviewDisplay"
                    class="pr-6 whitespace-pre-wrap break-all text-sm leading-7 text-[var(--np-on-surface)]"
                  >
                    {{ translatedPreviewDisplay }}
                  </p>
                  <p v-else-if="showRunningIndicator" class="pr-6 text-sm text-[var(--np-on-surface-variant)]">
                    翻译引擎正在处理，请稍候…
                  </p>
                  <p v-else class="text-sm text-[var(--np-on-surface-variant)]">当前任务尚未产出可用译文。</p>
                </div>
              </div>
            </div>
          </section>

          <JobTimeline :status="status" />
        </section>

        <aside class="space-y-4">
          <section class="rounded-[var(--np-radius-xl)] np-glass-feature p-4">
            <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.28em] text-[var(--np-primary)]" style="opacity: 0.7;">
              当前 Agent
            </p>
            <h2 class="mt-2 np-font-display text-lg font-semibold text-[var(--np-on-surface)]">当前干活的 Agent</h2>
            <div class="mt-4 flex items-start gap-3">
              <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[var(--np-radius-md)] bg-[rgba(153,247,255,0.12)] text-[var(--np-primary)]">
                <AgentGlyph :provider-key="translationProviderKey" size-class="h-8 w-8" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-[var(--np-on-surface)]">{{ translationProviderLabel }}</div>
                <div class="mt-1 break-all text-xs text-[var(--np-on-surface-variant)]">{{ translationAgent }}</div>
              </div>
            </div>
            <div class="mt-4 flex items-center gap-4 rounded-[var(--np-radius-lg)] np-glass p-3">
              <div class="job-robot shrink-0" :class="`job-robot--${robotState}`">
                <div class="job-robot__head">
                  <span class="job-robot__eye job-robot__eye--left" />
                  <span class="job-robot__eye job-robot__eye--right" />
                  <span class="job-robot__mouth" />
                </div>
                <div class="job-robot__body" />
                <span class="job-robot__arm job-robot__arm--left" />
                <span class="job-robot__arm job-robot__arm--right" />
              </div>
              <div class="text-xs leading-6 text-[var(--np-on-surface-variant)]">
                <p v-if="robotState === 'working'">小机器人正在努力处理这条任务。</p>
                <p v-else-if="robotState === 'success'">小机器人已经顺利完成翻译。</p>
                <p v-else>小机器人这次没跑通，建议检查失败原因后重试。</p>
              </div>
            </div>
          </section>

          <section class="rounded-[var(--np-radius-xl)] np-glass-feature p-4">
            <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.28em] text-[var(--np-primary)]" style="opacity: 0.7;">
              实时状态
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
              失败原因
            </p>
            <h2 class="mt-2 np-font-display text-lg font-semibold text-rose-100">失败原因</h2>
            <p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-rose-100/85">
              {{ failureReason || '后端没有返回额外失败原因。' }}
            </p>
          </section>

          <section class="rounded-[var(--np-radius-xl)] np-glass-strong p-4">
            <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.28em] text-[var(--np-primary)]" style="opacity: 0.7;">
              下一步
            </p>
            <h2 class="mt-2 np-font-display text-lg font-semibold text-[var(--np-on-surface)]">下一步操作</h2>
            <div class="mt-4 flex flex-col gap-3">
              <button
                v-if="isFailed"
                class="np-btn-cta w-full !py-3 !text-sm"
                :disabled="retrying"
                @click="retryTranslation"
              >
                <span v-if="retrying" class="flex items-center justify-center gap-2">
                  <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                  正在重新创建任务...
                </span>
                <span v-else>重新翻译</span>
              </button>
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
              <p v-if="retryError" class="text-sm leading-6 text-rose-200">
                {{ retryError }}
              </p>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>

<style scoped>
.job-slash-spinner {
  animation: job-slash-spin 1.2s linear infinite;
  transform-origin: center;
}

@keyframes job-slash-spin {
  0% {
    transform: rotate(0deg);
    opacity: 0.45;
  }

  50% {
    opacity: 1;
  }

  100% {
    transform: rotate(360deg);
    opacity: 0.45;
  }
}

@media (prefers-reduced-motion: reduce) {
  .job-slash-spinner {
    animation: none;
  }
}

.job-robot {
  position: relative;
  width: 56px;
  height: 68px;
}

.job-robot__head {
  position: absolute;
  top: 0;
  left: 8px;
  width: 40px;
  height: 28px;
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(153, 247, 255, 0.28), rgba(91, 133, 255, 0.16));
  border: 1px solid rgba(153, 247, 255, 0.25);
}

.job-robot__eye {
  position: absolute;
  top: 10px;
  width: 5px;
  height: 5px;
  border-radius: 999px;
  background: #99f7ff;
}

.job-robot__eye--left {
  left: 11px;
}

.job-robot__eye--right {
  right: 11px;
}

.job-robot__mouth {
  position: absolute;
  left: 14px;
  bottom: 6px;
  width: 12px;
  height: 2px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.65);
}

.job-robot__body {
  position: absolute;
  top: 31px;
  left: 14px;
  width: 28px;
  height: 24px;
  border-radius: 12px;
  background: linear-gradient(180deg, rgba(95, 193, 255, 0.25), rgba(29, 78, 216, 0.24));
  border: 1px solid rgba(95, 193, 255, 0.28);
}

.job-robot__arm {
  position: absolute;
  top: 36px;
  width: 14px;
  height: 3px;
  border-radius: 999px;
  background: rgba(153, 247, 255, 0.75);
}

.job-robot__arm--left {
  left: 2px;
  transform-origin: right center;
}

.job-robot__arm--right {
  right: 2px;
  transform-origin: left center;
}

.job-robot--working .job-robot__head {
  animation: robot-bob 1.6s ease-in-out infinite;
}

.job-robot--working .job-robot__arm--left {
  animation: robot-arm-left 0.9s ease-in-out infinite;
}

.job-robot--working .job-robot__arm--right {
  animation: robot-arm-right 0.9s ease-in-out 0.12s infinite;
}

.job-robot--success .job-robot__head,
.job-robot--success .job-robot__body {
  box-shadow: 0 0 18px rgba(74, 222, 128, 0.32);
}

.job-robot--success .job-robot__mouth {
  width: 14px;
  height: 6px;
  bottom: 5px;
  border-radius: 0 0 14px 14px;
  background: transparent;
  border-bottom: 2px solid rgba(74, 222, 128, 0.9);
}

.job-robot--failed .job-robot__head {
  animation: robot-droop 2s ease-in-out infinite;
}

.job-robot--failed .job-robot__eye {
  height: 2px;
}

.job-robot--failed .job-robot__mouth {
  width: 14px;
  height: 6px;
  bottom: 4px;
  border-radius: 14px 14px 0 0;
  background: transparent;
  border-top: 2px solid rgba(251, 113, 133, 0.9);
}

@keyframes robot-bob {
  0%, 100% {
    transform: translateY(0);
  }

  50% {
    transform: translateY(-2px);
  }
}

@keyframes robot-arm-left {
  0%, 100% {
    transform: rotate(18deg);
  }

  50% {
    transform: rotate(-18deg);
  }
}

@keyframes robot-arm-right {
  0%, 100% {
    transform: rotate(-18deg);
  }

  50% {
    transform: rotate(18deg);
  }
}

@keyframes robot-droop {
  0%, 100% {
    transform: rotate(0deg);
  }

  50% {
    transform: rotate(6deg) translateY(2px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .job-robot--working .job-robot__head,
  .job-robot--working .job-robot__arm--left,
  .job-robot--working .job-robot__arm--right,
  .job-robot--failed .job-robot__head {
    animation: none;
  }
}
</style>
