<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import JobTimeline from './JobTimeline.vue';
import TranslatedDocumentCard from './TranslatedDocumentCard.vue';
import GlossaryHitsPanel from './GlossaryHitsPanel.vue';
import RiskFlagsPanel from './RiskFlagsPanel.vue';
import AppErrorState from '../shared/AppErrorState.vue';

const props = defineProps({
  task: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  errorMessage: {
    type: String,
    default: '',
  },
});

const hasResult = computed(() => Boolean(props.task?.result));
const translatedDocument = computed(() => props.task?.result?.translated_document ?? {});
const glossaryHits = computed(() => props.task?.result?.glossary_hits ?? []);
const riskFlags = computed(() => props.task?.result?.risk_flags ?? []);
const resultMeta = computed(() => props.task?.result?.meta ?? props.task?.meta ?? {});

const statusText = computed(() => ({
  pending: '任务已创建，正在等待调度。',
  queued: '任务已经入队，正在等待模型执行。',
  processing: '任务正在处理中，结果会自动回流到当前工作台。',
  failed: props.task?.error?.reason ?? '任务执行失败，请稍后重试。',
  cancelled: '任务已取消。',
}[props.task?.status] ?? '选择一条任务后，这里会展示结果与复核信息。'));
</script>

<template>
  <section class="rounded-[2rem] border border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,0.04),rgba(255,255,255,0.02))] p-5 shadow-[0_28px_90px_rgba(6,13,30,0.45)] sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-[11px] uppercase tracking-[0.34em] text-cyan-100/55">Result Review</p>
        <h2 class="mt-3 text-2xl font-semibold text-white">结果与复核</h2>
        <p class="mt-2 text-sm leading-6 text-slate-300">
          同步快译会即时显示结果，异步任务则会在完成后自动回填术语命中、风险标记与结果摘要。
        </p>
      </div>

      <div
        v-if="task"
        class="rounded-full border border-white/10 bg-white/[0.04] px-4 py-2 text-xs text-slate-300"
      >
        {{ task.kind === 'async' ? '异步任务' : '同步快译' }}
      </div>
    </div>

    <AppErrorState v-if="errorMessage" class="mt-5" :message="errorMessage" />

    <div v-if="!task" class="mt-6 rounded-[1.5rem] border border-dashed border-white/12 bg-slate-950/55 p-6">
      <p class="text-lg font-medium text-white">准备开始一次翻译</p>
      <p class="mt-3 text-sm leading-6 text-slate-400">
        在左侧输入内容后，可以选择立即查看同步结果，或者创建异步任务并在右侧追踪状态。
      </p>
    </div>

    <div v-else class="mt-6 space-y-5">
      <div class="rounded-[1.5rem] border border-white/8 bg-slate-950/60 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm font-medium text-white">{{ task.sourceLang || 'zh' }} → {{ task.targetLang || 'en' }}</p>
            <p class="mt-1 break-all text-xs text-slate-500">{{ task.jobUuid || task.taskKey }}</p>
          </div>
          <div class="text-right">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Source Preview</p>
            <p class="mt-1 max-w-[20rem] text-sm text-slate-300">{{ task.sourcePreview || '暂无内容摘要。' }}</p>
          </div>
        </div>

        <div v-if="task.kind === 'async'" class="mt-5">
          <JobTimeline :status="task.status" />
        </div>
        <p v-else class="mt-5 text-sm leading-6 text-slate-300">同步快译会在当前工作台直接返回结果，可继续切换模式进行下一次提交。</p>
      </div>

      <div v-if="loading" class="rounded-[1.5rem] border border-sky-400/20 bg-sky-400/10 p-5 text-sm text-sky-100">
        正在刷新异步任务结果，请稍候……
      </div>

      <div v-else-if="!hasResult" class="rounded-[1.5rem] border border-white/8 bg-slate-950/60 p-5">
        <p class="text-lg font-medium text-white">结果尚未准备完成</p>
        <p class="mt-3 text-sm leading-6 text-slate-400">{{ statusText }}</p>
        <RouterLink
          v-if="task.jobUuid"
          :to="`/demo/jobs/${task.jobUuid}`"
          class="mt-4 inline-flex items-center rounded-full border border-cyan-300/25 bg-cyan-400/12 px-4 py-2 text-sm text-cyan-100 transition hover:border-cyan-200/40 hover:bg-cyan-400/18"
        >
          打开任务详情
        </RouterLink>
      </div>

      <template v-else>
        <TranslatedDocumentCard
          :translated-document="translatedDocument"
          :job-uuid="task.jobUuid"
          :status="task.status"
          :result-meta="resultMeta"
        />
        <div class="grid gap-5 xl:grid-cols-2">
          <GlossaryHitsPanel :glossary-hits="glossaryHits" />
          <RiskFlagsPanel :risk-flags="riskFlags" />
        </div>
      </template>
    </div>
  </section>
</template>
