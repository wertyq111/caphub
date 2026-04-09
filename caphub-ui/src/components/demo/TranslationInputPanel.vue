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
  <section class="relative overflow-hidden rounded-[1.2rem] border border-cyan-300/25 bg-[linear-gradient(165deg,rgba(3,14,34,0.97),rgba(7,20,42,0.97)_52%,rgba(28,23,58,0.93))] p-4 shadow-[0_24px_70px_rgba(3,10,27,0.7)] sm:p-5">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_15%,rgba(96,165,250,0.22),transparent_36%),radial-gradient(circle_at_85%_72%,rgba(168,85,247,0.18),transparent_42%)]" />

    <div class="relative space-y-4">
      <div class="space-y-3">
        <div class="text-center text-xl font-semibold text-cyan-100 sm:text-2xl">公开演示：智能翻译工作台</div>
        <div class="flex flex-wrap justify-center gap-2">
      <button
        data-mode="plain_text"
        class="rounded-md border px-4 py-1.5 text-xs transition"
        :class="mode === 'plain_text'
          ? 'border-cyan-200/40 bg-cyan-300 text-slate-950 shadow-[0_10px_25px_rgba(56,189,248,0.35)]'
          : 'border-slate-300/20 bg-slate-800/65 text-slate-200 hover:bg-slate-700/60'"
        @click="switchMode('plain_text')"
      >
        纯文本
      </button>
      <button
        data-mode="article_payload"
        class="rounded-md border px-4 py-1.5 text-xs transition"
        :class="mode === 'article_payload'
          ? 'border-cyan-200/40 bg-cyan-300 text-slate-950 shadow-[0_10px_25px_rgba(56,189,248,0.35)]'
          : 'border-slate-300/20 bg-slate-800/65 text-slate-200 hover:bg-slate-700/60'"
        @click="switchMode('article_payload')"
      >
        JSON 文本
      </button>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <div class="text-xs text-cyan-100/85">
          源语言输入
          <div class="mt-1.5 rounded-md border border-cyan-300/20 bg-slate-900/80 px-3 py-2 text-sm text-slate-50">
            中文
          </div>
        </div>
        <div class="text-xs text-cyan-100/85">
          AI 智能输出
          <div class="mt-1.5 rounded-md border border-violet-300/30 bg-slate-900/80 px-3 py-2 text-right text-sm text-slate-50">
            英文
          </div>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <div class="rounded-xl border border-cyan-300/30 bg-slate-900/60 p-2.5">
      <label v-if="mode === 'plain_text'" class="text-xs text-cyan-100/90">
        翻译原文
        <textarea
          v-model="form.text"
          rows="11"
          class="mt-1.5 w-full rounded-md border border-cyan-200/20 bg-slate-950/80 px-3 py-2.5 text-sm text-slate-100 outline-none transition focus:border-cyan-300/55"
        />
        <span class="mt-2 block text-[11px] leading-5 text-slate-400">
          请填入需要翻译的内容，支持多种格式。提交后会在当前页面直接展示翻译结果。
        </span>
      </label>
      <div v-else class="space-y-2">
        <label class="text-xs text-cyan-100/90">
        标题
        <input
          v-model="form.title"
          class="mt-1.5 w-full rounded-md border border-cyan-200/20 bg-slate-950/80 px-3 py-2 text-sm text-slate-50 outline-none transition focus:border-cyan-300/55"
        />
      </label>
        <label class="text-xs text-cyan-100/90">
        摘要
        <textarea
          v-model="form.summary"
          rows="3"
          class="mt-1.5 w-full rounded-md border border-cyan-200/20 bg-slate-950/80 px-3 py-2 text-sm text-slate-50 outline-none transition focus:border-cyan-300/55"
        />
      </label>
        <label class="text-xs text-cyan-100/90">
        正文
        <textarea
          v-model="form.body"
          rows="5"
          class="mt-1.5 w-full rounded-md border border-cyan-200/20 bg-slate-950/80 px-3 py-2 text-sm text-slate-50 outline-none transition focus:border-cyan-300/55"
        />
      </label>
      </div>
        </div>

        <div class="rounded-xl border border-violet-300/35 bg-[linear-gradient(180deg,rgba(33,37,66,0.72),rgba(26,31,58,0.9))] p-2.5">
          <div class="text-xs text-violet-100/85">翻译结果</div>
          <div class="mt-1 min-h-[20.25rem] rounded-md border border-violet-300/30 bg-slate-950/75 p-3 text-xs leading-6 text-slate-200 sm:text-sm">
            <template v-if="hasValue(translatedDocument().text)">
              <p class="whitespace-pre-wrap text-slate-100">{{ translatedDocument().text }}</p>
            </template>
            <div v-else-if="hasValue(translatedDocument().title) || hasValue(translatedDocument().summary) || hasValue(translatedDocument().body)" class="space-y-2">
              <p v-if="hasValue(translatedDocument().title)" class="font-medium text-white">{{ translatedDocument().title }}</p>
              <p v-if="hasValue(translatedDocument().summary)" class="text-slate-200">{{ translatedDocument().summary }}</p>
              <p v-if="hasValue(translatedDocument().body)" class="whitespace-pre-wrap text-slate-100">{{ translatedDocument().body }}</p>
            </div>
            <p v-else class="text-slate-400">提交后将在这里显示翻译结果。</p>
          </div>
        </div>
      </div>

      <div class="grid gap-2 rounded-xl border border-cyan-300/25 bg-slate-900/65 p-2.5 sm:grid-cols-[1fr_1fr_auto] sm:items-center">
        <div class="flex items-center gap-2 rounded-md border border-cyan-300/20 bg-cyan-500/10 px-2.5 py-2 text-xs text-cyan-100">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-950/70">✓</span>
          <span>术语匹配已启用</span>
        </div>
        <div class="flex items-center gap-2 rounded-md border border-violet-300/25 bg-violet-500/10 px-2.5 py-2 text-xs text-violet-100">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-950/70">!</span>
          <span>AI 风险评估：安全</span>
        </div>
        <button
          class="rounded-md border border-cyan-200/35 bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="props.loading"
          @click="submit"
        >
          {{ props.loading ? '提交中...' : '开始翻译' }}
        </button>
      </div>
    </div>
  </section>
</template>
