<script setup>
import { reactive, ref } from 'vue';

const emit = defineEmits(['mode-change', 'submit']);
const props = defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  translationResult: {
    type: Object,
    default: null,
  },
  activeAgent: {
    type: String,
    default: '',
  },
});

const mode = ref('plain_text');
const form = reactive({
  source_lang: 'zh',
  target_lang: 'en',
  text: '乙烯价格上涨。',
  title: '',
  summary: '',
  body: '',
});

function switchMode(nextMode) {
  mode.value = nextMode;
  emit('mode-change', nextMode);
}

function submit() {
  const content = mode.value === 'plain_text'
    ? { text: form.text }
    : {
      title: form.title,
      summary: form.summary,
      body: form.body,
    };

  emit('submit', {
    input_type: mode.value,
    document_type: 'chemical_news',
    source_lang: form.source_lang,
    target_lang: form.target_lang,
    content,
  });
}

function hasValue(value) {
  return typeof value === 'string' && value.length > 0;
}

function translatedDocument() {
  return props.translationResult?.translated_document ?? {};
}
</script>

<template>
  <section class="relative overflow-hidden rounded-[var(--np-radius-2xl)] np-glass-feature p-4 sm:p-5">
    <div class="relative space-y-4">
      <!-- Title & Mode Selector -->
      <div class="space-y-3">
        <div class="np-font-display text-center text-xl font-semibold text-[var(--np-on-surface)] sm:text-2xl">公开演示：智能翻译工作台</div>
        <div class="flex flex-wrap justify-center gap-2">
          <button
            data-mode="plain_text"
            class="rounded-[var(--np-radius-md)] px-4 py-1.5 text-xs transition-all duration-200"
            :class="mode === 'plain_text'
              ? 'np-btn-cta !px-4 !py-1.5 !text-xs'
              : 'np-glass text-[var(--np-on-surface-variant)] hover:text-[var(--np-on-surface)]'"
            @click="switchMode('plain_text')"
          >
            纯文本
          </button>
          <button
            data-mode="article_payload"
            class="rounded-[var(--np-radius-md)] px-4 py-1.5 text-xs transition-all duration-200"
            :class="mode === 'article_payload'
              ? 'np-btn-cta !px-4 !py-1.5 !text-xs'
              : 'np-glass text-[var(--np-on-surface-variant)] hover:text-[var(--np-on-surface)]'"
            @click="switchMode('article_payload')"
          >
            JSON 文本
          </button>
        </div>
      </div>

      <!-- Language Labels -->
      <div class="grid gap-3 md:grid-cols-2">
        <div class="text-xs text-[var(--np-primary)]" style="opacity: 0.85;">
          源语言输入
          <div class="mt-1.5 rounded-[var(--np-radius-md)] np-glass px-3 py-2 text-sm text-[var(--np-on-surface)]">
            中文
          </div>
        </div>
        <div class="text-xs text-[var(--np-secondary)]" style="opacity: 0.85;">
          AI 智能输出
          <div class="mt-1.5 rounded-[var(--np-radius-md)] np-glass px-3 py-2 text-right text-sm text-[var(--np-on-surface)]">
            英文
          </div>
        </div>
      </div>

      <!-- Input / Output Panels -->
      <div class="grid gap-3 md:grid-cols-2">
        <!-- Input Panel -->
        <div class="rounded-[var(--np-radius-xl)] np-glass-strong p-2.5">
          <label v-if="mode === 'plain_text'" class="text-xs text-[var(--np-primary)]" style="opacity: 0.9;">
            翻译原文
            <textarea
              v-model="form.text"
              rows="11"
              class="mt-1.5 w-full rounded-[var(--np-radius-md)] border border-[var(--np-outline-variant)] bg-[var(--np-background)] px-3 py-2.5 text-sm text-[var(--np-on-surface)] outline-none transition focus:border-[var(--np-primary-dim)]"
              style="background: rgba(13,14,23,0.8);"
            />
            <span class="mt-2 block text-[11px] leading-5 text-[var(--np-on-surface-variant)]">
              请填入需要翻译的内容，支持多种格式。提交后会在当前页面直接展示翻译结果。
            </span>
          </label>
          <div v-else class="space-y-2">
            <label class="text-xs text-[var(--np-primary)]" style="opacity: 0.9;">
              标题
              <input
                v-model="form.title"
                class="mt-1.5 w-full rounded-[var(--np-radius-md)] border border-[var(--np-outline-variant)] bg-[var(--np-background)] px-3 py-2 text-sm text-[var(--np-on-surface)] outline-none transition focus:border-[var(--np-primary-dim)]"
                style="background: rgba(13,14,23,0.8);"
              />
            </label>
            <label class="text-xs text-[var(--np-primary)]" style="opacity: 0.9;">
              摘要
              <textarea
                v-model="form.summary"
                rows="3"
                class="mt-1.5 w-full rounded-[var(--np-radius-md)] border border-[var(--np-outline-variant)] bg-[var(--np-background)] px-3 py-2 text-sm text-[var(--np-on-surface)] outline-none transition focus:border-[var(--np-primary-dim)]"
                style="background: rgba(13,14,23,0.8);"
              />
            </label>
            <label class="text-xs text-[var(--np-primary)]" style="opacity: 0.9;">
              正文
              <textarea
                v-model="form.body"
                rows="5"
                class="mt-1.5 w-full rounded-[var(--np-radius-md)] border border-[var(--np-outline-variant)] bg-[var(--np-background)] px-3 py-2 text-sm text-[var(--np-on-surface)] outline-none transition focus:border-[var(--np-primary-dim)]"
                style="background: rgba(13,14,23,0.8);"
              />
            </label>
          </div>
        </div>

        <!-- Output Panel -->
        <div class="rounded-[var(--np-radius-xl)] np-glass-strong p-2.5" style="border-color: rgba(172,137,255,0.12);">
          <div class="text-xs text-[var(--np-secondary)]" style="opacity: 0.85;">翻译结果</div>
          <div class="mt-1 min-h-[20.25rem] rounded-[var(--np-radius-md)] border border-[var(--np-outline-variant)] p-3 text-xs leading-6 text-[var(--np-on-surface-variant)] sm:text-sm" style="background: rgba(13,14,23,0.6);">
            <template v-if="hasValue(translatedDocument().text)">
              <p class="whitespace-pre-wrap text-[var(--np-on-surface)]">{{ translatedDocument().text }}</p>
            </template>
            <div v-else-if="hasValue(translatedDocument().title) || hasValue(translatedDocument().summary) || hasValue(translatedDocument().body)" class="space-y-2">
              <p v-if="hasValue(translatedDocument().title)" class="font-medium text-white">{{ translatedDocument().title }}</p>
              <p v-if="hasValue(translatedDocument().summary)" class="text-[var(--np-on-surface-variant)]">{{ translatedDocument().summary }}</p>
              <p v-if="hasValue(translatedDocument().body)" class="whitespace-pre-wrap text-[var(--np-on-surface)]">{{ translatedDocument().body }}</p>
            </div>
            <p v-else class="text-[var(--np-on-surface-variant)]" style="opacity: 0.6;">提交后将在这里显示翻译结果。</p>
          </div>
        </div>
      </div>

      <!-- Status Indicators -->
      <div class="flex flex-wrap items-center gap-2">
        <div class="flex items-center gap-2 rounded-[var(--np-radius-md)] np-glass px-3 py-2 text-xs text-[var(--np-primary)]">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-[var(--np-primary-container)]">✓</span>
          <span>术语匹配已启用</span>
        </div>
        <div class="flex items-center gap-2 rounded-[var(--np-radius-md)] np-glass px-3 py-2 text-xs text-[var(--np-secondary)]">
          <span class="np-dot-pulse h-2 w-2 rounded-full bg-[var(--np-success)] text-[var(--np-success)]" />
          <span>当前智能体：{{ activeAgent || '检测中...' }}</span>
        </div>
      </div>

      <!-- Start Translation Button — standalone row -->
      <div class="flex justify-center pt-1">
        <button
          class="np-btn-cta w-full max-w-md !py-3.5 !text-base sm:w-auto sm:min-w-[280px]"
          :disabled="props.loading"
          @click="submit"
        >
          <span v-if="props.loading" class="flex items-center gap-2">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            翻译处理中...
          </span>
          <span v-else class="flex items-center gap-2">
            <span>⬡</span>
            开始翻译
          </span>
        </button>
      </div>
    </div>
  </section>
</template>
