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
    <section class="rounded-xl border border-cyan-300/25 bg-[linear-gradient(180deg,rgba(6,20,45,0.96),rgba(5,14,31,0.98))] p-4 shadow-[0_18px_50px_rgba(8,47,73,0.28)]">
      <p class="text-[11px] font-medium uppercase tracking-[0.3em] text-cyan-100/70">Workbench Status</p>
      <h2 class="mt-3 text-lg font-semibold text-cyan-50">公开演示：智能翻译工作台</h2>
      <p class="mt-2 text-sm leading-6 text-slate-300">
        使用化工资讯翻译 Agent 直接返回同步结果，并在当前页承接术语命中、风险标记与输出复核。
      </p>

      <div
        v-if="jobUuid"
        class="mt-4 rounded-lg border border-emerald-300/30 bg-emerald-300/10 p-3"
      >
        <div class="text-[11px] uppercase tracking-[0.24em] text-emerald-200/70">Latest Job</div>
        <div class="mt-2 break-all text-xs font-medium text-white sm:text-sm">{{ jobUuid }}</div>
        <button
          class="mt-3 inline-flex items-center rounded-md bg-cyan-300 px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-cyan-200"
          @click="emit('open-job')"
        >
          查看任务进度
        </button>
      </div>
    </section>

    <section
      v-for="section in sections"
      :key="section.title"
      class="rounded-xl border border-cyan-200/20 bg-slate-900/55 p-4"
    >
      <div class="text-[11px] uppercase tracking-[0.24em] text-cyan-100/60">{{ section.eyebrow }}</div>
      <h3 class="mt-2 text-base font-semibold text-cyan-50">{{ section.title }}</h3>
      <p class="mt-2 text-sm leading-6 text-slate-300">{{ section.description }}</p>
      <ul class="mt-3 space-y-2">
        <li
          v-for="item in section.items"
          :key="item"
          class="flex items-start gap-2.5 text-sm leading-6 text-slate-200"
        >
          <span class="mt-2 h-1.5 w-1.5 rounded-full bg-cyan-300 shadow-[0_0_10px_rgba(34,211,238,0.85)]" />
          <span>{{ item }}</span>
        </li>
      </ul>
    </section>
  </aside>
</template>
