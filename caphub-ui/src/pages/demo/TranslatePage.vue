<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import TranslationInputPanel from '../../components/demo/TranslationInputPanel.vue';
import CapabilitySidebar from '../../components/public/CapabilitySidebar.vue';
import AppErrorState from '../../components/shared/AppErrorState.vue';
import { submitAsyncTranslation, submitSyncTranslation } from '../../api/translation';
import { fetchDashboardStats } from '../../api/dashboard';

const PLAIN_TEXT_SYNC_THRESHOLD = 1800;

const router = useRouter();
const errorMessage = ref('');
const loading = ref(false);
const translationResult = ref(null);
const activeLongTextAgent = ref('');
const latestJobUuid = ref('');

onMounted(async () => {
  try {
    const data = await fetchDashboardStats();
    const active = data.agents?.find(a => a.active && a.key !== 'github_models');
    activeLongTextAgent.value = active?.name ?? '';
  } catch {
    activeLongTextAgent.value = '';
  }
});

const sidebarSections = [
  {
    eyebrow: 'Capability Highlights',
    title: '当前演示的不只是翻译框',
    description: '页面右侧会把系统能力直接展示出来，帮助用户理解这不是一次性的 prompt 页面，而是一套可扩展的 AI 工作流入口。',
    items: [
      '术语命中、风险标记与 notes 继续通过结果页承接',
      '短文本同步直出，长文本自动转任务追踪页',
      '后台侧已有术语表、任务历史与 AI 日志能力',
    ],
  },
  {
    eyebrow: 'Usage Notes',
    title: '输入建议',
    description: '纯文本 1800 字以内会直接走 Copilot；超过阈值或 JSON 文本会切到当前长文本接口并创建任务。',
    items: [
      '语言代码建议保持与后端既有规则一致',
      '短文本提交后会在当前页直接显示结果',
      '长文本提交后会自动进入任务跟踪页',
    ],
  },
];

const activeAgent = computed(() => {
  const longTextAgent = activeLongTextAgent.value || '当前长文本接口';
  return `短文本 Copilot(gpt-4o) · 长文本 ${longTextAgent}`;
});

function handleModeChange(mode) {
  return mode;
}

function shouldUseAsyncRoute(payload) {
  if (payload.preferred_route === 'async') {
    return true;
  }

  if (payload.input_type !== 'plain_text') {
    return true;
  }

  const text = payload.content?.text;

  return typeof text === 'string' && text.length > PLAIN_TEXT_SYNC_THRESHOLD;
}

async function handleSubmit(payload) {
  errorMessage.value = '';
  translationResult.value = null;
  latestJobUuid.value = '';
  loading.value = true;

  try {
    const { preferred_route, ...requestPayload } = payload;

    if (shouldUseAsyncRoute(payload)) {
      const data = await submitAsyncTranslation(requestPayload);
      latestJobUuid.value = data.job_uuid ?? '';

      if (latestJobUuid.value) {
        await router.push(`/demo/jobs/${latestJobUuid.value}`);
      }

      return;
    }

    const data = await submitSyncTranslation(requestPayload);
    translationResult.value = data;
  } catch (error) {
    errorMessage.value = error?.response?.data?.message ?? 'Failed to translate content.';
  } finally {
    loading.value = false;
  }
}

function openLatestJob() {
  if (!latestJobUuid.value) {
    return;
  }

  router.push(`/demo/jobs/${latestJobUuid.value}`);
}
</script>

<template>
  <div class="space-y-4">
    <!-- Status bar -->
    <div class="flex items-center justify-between rounded-[var(--np-radius-xl)] np-glass px-4 py-2.5">
      <div class="flex items-center gap-3">
        <span class="np-dot-pulse h-2 w-2 rounded-full bg-[var(--np-success)] text-[var(--np-success)]" />
        <span class="np-font-mono text-xs font-medium uppercase tracking-[0.2em] text-[var(--np-primary)]" style="opacity: 0.7;">Translation Workbench</span>
        <span class="text-xs text-[var(--np-on-surface-variant)]">短文本 Copilot · 长文本当前接口</span>
      </div>
      <RouterLink to="/" class="text-xs text-[var(--np-on-surface-variant)] no-underline transition hover:text-[var(--np-primary)]">← 返回首页</RouterLink>
    </div>

    <!-- Title -->
    <div class="text-center">
      <h1 class="np-font-display text-3xl font-bold text-[var(--np-on-surface)] sm:text-4xl" style="text-shadow: 0 0 50px rgba(153,247,255,0.25);">
        智能翻译工作台
      </h1>
      <p class="mt-2 text-sm text-[var(--np-on-surface-variant)]">专业化工资讯翻译 · 同步响应 · 即时结果预览</p>
    </div>

    <!-- Main content -->
    <div id="translation-workbench" class="mx-auto grid max-w-6xl gap-4 xl:grid-cols-[minmax(0,1.5fr)_260px]">
      <section class="space-y-3">
        <TranslationInputPanel
          :loading="loading"
          :translation-result="translationResult"
          :active-agent="activeAgent"
          @mode-change="handleModeChange"
          @submit="handleSubmit"
        />
        <AppErrorState v-if="errorMessage" :message="errorMessage" />
      </section>

      <CapabilitySidebar
        :sections="sidebarSections"
        :job-uuid="latestJobUuid"
        @open-job="openLatestJob"
      />
    </div>
  </div>
</template>
