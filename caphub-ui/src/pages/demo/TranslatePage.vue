<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import TranslationInputPanel from '../../components/demo/TranslationInputPanel.vue';
import CapabilitySidebar from '../../components/public/CapabilitySidebar.vue';
import AppErrorState from '../../components/shared/AppErrorState.vue';
import { submitSyncTranslation } from '../../api/translation';
import { fetchDashboardStats } from '../../api/dashboard';

const errorMessage = ref('');
const loading = ref(false);
const translationResult = ref(null);
const activeAgent = ref('');

onMounted(async () => {
  try {
    const data = await fetchDashboardStats();
    const active = data.agents?.find(a => a.active);
    activeAgent.value = active?.name ?? '';
  } catch {
    activeAgent.value = '';
  }
});

const sidebarSections = [
  {
    eyebrow: 'Capability Highlights',
    title: '当前演示的不只是翻译框',
    description: '页面右侧会把系统能力直接展示出来，帮助用户理解这不是一次性的 prompt 页面，而是一套可扩展的 AI 工作流入口。',
    items: [
      '术语命中、风险标记与 notes 继续通过结果页承接',
      '同步翻译结果会在当前页面即时呈现',
      '后台侧已有术语表、任务历史与 AI 日志能力',
    ],
  },
  {
    eyebrow: 'Usage Notes',
    title: '输入建议',
    description: '短消息、单段快讯更适合 Plain Text；结构化资讯则可切换到 Article Payload 模式，分别输入标题、摘要与正文。',
    items: [
      '语言代码建议保持与后端既有规则一致',
      '提交后会在当前页直接显示完整结果',
      '长文本内容建议按语义分段输入，便于人工复核',
    ],
  },
];

function handleModeChange(mode) {
  return mode;
}

async function handleSubmit(payload) {
  errorMessage.value = '';
  translationResult.value = null;
  loading.value = true;
  try {
    const data = await submitSyncTranslation(payload);
    translationResult.value = data;
  } catch (error) {
    errorMessage.value = error?.response?.data?.message ?? 'Failed to translate content.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Status bar -->
    <div class="flex items-center justify-between rounded-[var(--np-radius-xl)] np-glass px-4 py-2.5">
      <div class="flex items-center gap-3">
        <span class="np-dot-pulse h-2 w-2 rounded-full bg-[var(--np-success)] text-[var(--np-success)]" />
        <span class="np-font-mono text-xs font-medium uppercase tracking-[0.2em] text-[var(--np-primary)]" style="opacity: 0.7;">Translation Workbench</span>
        <span class="text-xs text-[var(--np-on-surface-variant)]">chemical-news-translator · Sync-first</span>
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

      <CapabilitySidebar :sections="sidebarSections" />
    </div>
  </div>
</template>
