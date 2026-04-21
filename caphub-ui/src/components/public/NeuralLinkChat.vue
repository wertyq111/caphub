<script setup>
import { ref, nextTick, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { sendChatMessage } from '../../api/dashboard';

const auth = useAuthStore();
const input = ref('');
const sending = ref(false);
const HISTORY_LIMIT = 6;
const HISTORY_CONTENT_LIMIT = 1200;
const DEFAULT_ERROR_MESSAGE = '对话助手响应超时或上游无返回，请稍后重试。';

const STORAGE_KEY = 'caphub_chat_history';

function loadHistory() {
  if (!auth.isAuthenticated) return [];
  try {
    const stored = localStorage.getItem(`${STORAGE_KEY}_${auth.user?.id || 'anon'}`);
    return stored ? JSON.parse(stored) : [];
  } catch {
    return [];
  }
}

function saveHistory() {
  if (!auth.isAuthenticated) return;
  try {
    const key = `${STORAGE_KEY}_${auth.user?.id || 'anon'}`;
    const toSave = chatHistory.value.slice(-50);
    localStorage.setItem(key, JSON.stringify(toSave));
  } catch { /* ignore quota errors */ }
}

const chatHistory = ref(loadHistory());

// Initialize with welcome message if empty
if (chatHistory.value.length === 0) {
  chatHistory.value.push({
    role: 'assistant',
    content: '系统已就绪，所有核心节点运行正常。需要执行什么指令？',
  });
}

const scrollContainer = ref(null);

function scrollToBottom() {
  nextTick(() => {
    if (scrollContainer.value) {
      scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
  });
}

onMounted(scrollToBottom);

watch(() => auth.isAuthenticated, (isAuth) => {
  if (isAuth) {
    const restored = loadHistory();
    if (restored.length > 0) {
      chatHistory.value = restored;
    }
    scrollToBottom();
  }
});

async function handleSend() {
  const message = input.value.trim();
  if (!message || sending.value) return;

  chatHistory.value.push({ role: 'user', content: message });
  input.value = '';
  sending.value = true;
  scrollToBottom();

  try {
    const apiHistory = chatHistory.value
      .filter(m => m.role !== 'system')
      .slice(-HISTORY_LIMIT)
      .map(m => ({ role: m.role, content: m.content.slice(0, HISTORY_CONTENT_LIMIT) }));

    const { reply } = await sendChatMessage(message, apiHistory.slice(0, -1));
    chatHistory.value.push({ role: 'assistant', content: reply });
  } catch (error) {
    chatHistory.value.push({
      role: 'assistant',
      content: error?.response?.data?.reply ?? DEFAULT_ERROR_MESSAGE,
    });
  } finally {
    sending.value = false;
    saveHistory();
    scrollToBottom();
  }
}

function handleKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    handleSend();
  }
}

function clearHistory() {
  chatHistory.value = [{
    role: 'assistant',
    content: '会话已清除。有什么可以帮你的？',
  }];
  saveHistory();
}
</script>

<template>
  <section class="rounded-[var(--np-radius-xl)] np-glass-feature p-4">
    <!-- Header -->
    <div class="mb-3 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="np-dot-pulse h-2.5 w-2.5 rounded-full bg-[var(--np-success)] text-[var(--np-success)]" />
        <span class="np-font-mono text-xs font-semibold uppercase tracking-[0.2em] text-[var(--np-on-surface-variant)]">
          NEURAL LINK 激活
        </span>
      </div>
      <button
        v-if="auth.isAuthenticated && chatHistory.length > 1"
        class="text-[11px] text-[var(--np-on-surface-variant)] transition hover:text-[var(--np-error)]"
        @click="clearHistory"
      >
        清除记录
      </button>
    </div>

    <!-- Login prompt if not authenticated -->
    <template v-if="!auth.isAuthenticated">
      <div class="flex flex-col items-center gap-4 py-8">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl np-glass-strong">
          <span class="text-2xl text-[var(--np-secondary)]">⬡</span>
        </div>
        <p class="text-center text-sm text-[var(--np-on-surface-variant)]">
          登录后即可与 Neural Link AI 助手对话，<br />会话记录将自动保存。
        </p>
        <RouterLink
          to="/admin/login"
          class="np-btn-cta !px-6 !py-2.5 !text-sm no-underline"
        >
          登录使用
        </RouterLink>
      </div>
    </template>

    <!-- Chat interface (authenticated) -->
    <template v-else>
      <!-- Chat Messages -->
      <div
        ref="scrollContainer"
        class="max-h-[420px] space-y-3 overflow-y-auto pr-1"
      >
        <div
          v-for="(msg, idx) in chatHistory"
          :key="idx"
          class="flex"
          :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
        >
          <!-- Assistant message -->
          <div
            v-if="msg.role === 'assistant'"
            class="flex max-w-[85%] gap-2"
          >
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full np-glass text-sm text-[var(--np-primary)]">
              ⬡
            </div>
            <div class="rounded-[var(--np-radius-md)] rounded-tl-sm np-glass-strong px-3 py-2 text-sm leading-6 text-[var(--np-on-surface)]">
              {{ msg.content }}
            </div>
          </div>

          <!-- User message -->
          <div
            v-else
            class="flex max-w-[85%] gap-2"
          >
            <div class="rounded-[var(--np-radius-md)] rounded-tr-sm px-3 py-2 text-sm leading-6 text-[var(--np-on-surface)]" style="background: rgba(172,137,255,0.1); border: 1px solid rgba(172,137,255,0.12);">
              {{ msg.content }}
            </div>
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm text-[var(--np-secondary)]" style="background: rgba(172,137,255,0.1);">
              ⊕
            </div>
          </div>
        </div>

        <!-- Typing indicator -->
        <div v-if="sending" class="flex gap-2">
          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full np-glass text-sm text-[var(--np-primary)]">
            ⬡
          </div>
          <div class="flex items-center gap-1 rounded-[var(--np-radius-md)] np-glass-strong px-3 py-2">
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[var(--np-primary-dim)]" style="animation-delay: 0ms" />
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[var(--np-primary-dim)]" style="animation-delay: 150ms" />
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[var(--np-primary-dim)]" style="animation-delay: 300ms" />
          </div>
        </div>
      </div>

      <!-- Input -->
      <div class="mt-3 flex items-center gap-2 rounded-[var(--np-radius-lg)] np-glass px-3 py-2">
        <span class="text-sm text-[var(--np-on-surface-variant)]">⊙</span>
        <input
          v-model="input"
          type="text"
          placeholder="输入控制指令或查询..."
          class="flex-1 bg-transparent text-sm text-[var(--np-on-surface)] placeholder-[var(--np-on-surface-variant)] outline-none"
          :disabled="sending"
          @keydown="handleKeydown"
        />
        <button
          class="flex h-8 w-8 items-center justify-center rounded-full text-sm transition"
          :class="input.trim()
            ? 'bg-gradient-to-r from-[var(--np-primary)] to-[var(--np-secondary)] text-[var(--np-background)] hover:opacity-90'
            : 'bg-[var(--np-surface-bright)] text-[var(--np-on-surface-variant)]'"
          :disabled="!input.trim() || sending"
          @click="handleSend"
        >
          ➤
        </button>
      </div>
    </template>
  </section>
</template>
