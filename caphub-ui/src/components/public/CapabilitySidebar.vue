<script setup>
defineProps({
  sections: {
    type: Array,
    default: () => [],
  },
  jobUuid: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['open-job']);
</script>

<template>
  <aside class="space-y-3">
    <section class="rounded-[var(--np-radius-xl)] np-glass-feature p-4">
      <p class="np-font-mono text-[11px] font-medium uppercase tracking-[0.3em] text-[var(--np-primary)]" style="opacity: 0.7;">Workbench Status</p>
      <h2 class="mt-3 np-font-display text-lg font-semibold text-[var(--np-on-surface)]">公开演示：智能翻译工作台</h2>
      <p class="mt-2 text-sm leading-6 text-[var(--np-on-surface-variant)]">
        使用化工资讯翻译 Agent 直接返回同步结果，并在当前页承接术语命中、风险标记与输出复核。
      </p>

      <div
        v-if="jobUuid"
        class="mt-4 rounded-[var(--np-radius-lg)] np-glass p-3" style="border-color: rgba(74,222,128,0.15);"
      >
        <div class="np-font-mono text-[11px] uppercase tracking-[0.24em] text-[var(--np-success)]" style="opacity: 0.7;">Latest Job</div>
        <div class="mt-2 break-all text-xs font-medium text-[var(--np-on-surface)] sm:text-sm">{{ jobUuid }}</div>
        <button
          class="np-btn-secondary mt-3 text-xs"
          @click="emit('open-job')"
        >
          查看任务进度
        </button>
      </div>
    </section>

    <section
      v-for="section in sections"
      :key="section.title"
      class="rounded-[var(--np-radius-xl)] np-glass-strong p-4"
    >
      <div class="np-font-mono text-[11px] uppercase tracking-[0.24em] text-[var(--np-primary)]" style="opacity: 0.6;">{{ section.eyebrow }}</div>
      <h3 class="mt-2 np-font-display text-base font-semibold text-[var(--np-on-surface)]">{{ section.title }}</h3>
      <p class="mt-2 text-sm leading-6 text-[var(--np-on-surface-variant)]">{{ section.description }}</p>
      <ul class="mt-3 space-y-2">
        <li
          v-for="item in section.items"
          :key="item"
          class="flex items-start gap-2.5 text-sm leading-6 text-[var(--np-on-surface-variant)]"
        >
          <span class="mt-2 h-1.5 w-1.5 rounded-full bg-[var(--np-primary)] np-dot-pulse text-[var(--np-primary)]" />
          <span>{{ item }}</span>
        </li>
      </ul>
    </section>
  </aside>
</template>
