<script setup>
import { ref, nextTick } from 'vue';
import { sendChatMessage } from '../../api/dashboard';

const input = ref('');
const sending = ref(false);
const chatHistory = ref([
  {
    role: 'assistant',
    content: '系统已就绪，所有核心节点运行正常。需要执行什么指令？',
  },
]);

const scrollContainer = ref(null);

function scrollToBottom() {
  nextTick(() => {
    if (scrollContainer.value) {
      scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
  });
}

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
      .slice(-18)
      .map(m => ({ role: m.role, content: m.content }));

    const { reply } = await sendChatMessage(message, apiHistory.slice(0, -1));
    chatHistory.value.push({ role: 'assistant', content: reply });
  } catch {
    chatHistory.value.push({
      role: 'assistant',
      content: '连接中断，请稍后重试。',
    });
  } finally {
    sending.value = false;
    scrollToBottom();
  }
}

function handleKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    handleSend();
  }
}
</script>

<template>
  <section class="rounded-[var(--np-radius-xl)] bg-[var(--np-surface-container-low)] p-4">
    <!-- Header -->
    <div class="mb-3 flex items-center gap-2">
      <span class="np-dot-pulse h-2.5 w-2.5 rounded-full bg-[var(--np-success)] text-[var(--np-success)]" />
      <span class="np-font-mono text-xs font-semibold uppercase tracking-[0.2em] text-[var(--np-on-surface-variant)]">
        NEURAL LINK 激活
      </span>
    </div>

    <!-- Chat Messages -->
    <div
      ref="scrollContainer"
      class="max-h-[200px] space-y-3 overflow-y-auto pr-1"
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
          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[rgba(153,247,255,0.1)] text-sm text-[var(--np-primary)]">
            ⬡
          </div>
          <div class="rounded-[var(--np-radius-md)] rounded-tl-sm bg-[var(--np-surface-container)] px-3 py-2 text-sm leading-6 text-[var(--np-on-surface)]">
            {{ msg.content }}
          </div>
        </div>

        <!-- User message -->
        <div
          v-else
          class="flex max-w-[85%] gap-2"
        >
          <div class="rounded-[var(--np-radius-md)] rounded-tr-sm bg-[var(--np-surface-container-high)] px-3 py-2 text-sm leading-6 text-[var(--np-on-surface)]">
            {{ msg.content }}
          </div>
          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[rgba(172,137,255,0.12)] text-sm text-[var(--np-secondary)]">
            ⊕
          </div>
        </div>
      </div>

      <!-- Typing indicator -->
      <div v-if="sending" class="flex gap-2">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[rgba(153,247,255,0.1)] text-sm text-[var(--np-primary)]">
          ⬡
        </div>
        <div class="flex items-center gap-1 rounded-[var(--np-radius-md)] bg-[var(--np-surface-container)] px-3 py-2">
          <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[var(--np-primary-dim)]" style="animation-delay: 0ms" />
          <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[var(--np-primary-dim)]" style="animation-delay: 150ms" />
          <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[var(--np-primary-dim)]" style="animation-delay: 300ms" />
        </div>
      </div>
    </div>

    <!-- Input -->
    <div class="mt-3 flex items-center gap-2 rounded-[var(--np-radius-lg)] bg-[var(--np-surface-container)] px-3 py-2">
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
  </section>
</template>
