<script setup>
const emit = defineEmits(['select']);

const props = defineProps({
  tasks: {
    type: Array,
    default: () => [],
  },
  selectedTaskKey: {
    type: String,
    default: '',
  },
  activeTask: {
    type: Object,
    default: null,
  },
});

const statusMap = {
  pending: {
    label: '等待处理',
    badge: 'border-amber-400/30 bg-amber-400/12 text-amber-100',
  },
  queued: {
    label: '队列中',
    badge: 'border-amber-400/30 bg-amber-400/12 text-amber-100',
  },
  processing: {
    label: '处理中',
    badge: 'border-sky-400/30 bg-sky-400/12 text-sky-100',
  },
  succeeded: {
    label: '已完成',
    badge: 'border-emerald-400/30 bg-emerald-400/12 text-emerald-100',
  },
  failed: {
    label: '已失败',
    badge: 'border-rose-400/30 bg-rose-400/12 text-rose-100',
  },
  cancelled: {
    label: '已取消',
    badge: 'border-slate-400/30 bg-slate-400/12 text-slate-200',
  },
};

function formatTaskKind(task) {
  return task?.kind === 'async' ? '异步任务' : '同步快译';
}

function statusMeta(status) {
  return statusMap[status] ?? {
    label: status || '未知状态',
    badge: 'border-white/12 bg-white/6 text-slate-100',
  };
}

function formatTime(value) {
  if (!value) {
    return '刚刚';
  }

  return new Intl.DateTimeFormat('zh-CN', {
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}
</script>

<template>
  <aside class="space-y-4">
    <section class="rounded-[1.8rem] border border-white/10 bg-white/[0.03] p-5 shadow-[0_20px_80px_rgba(4,10,24,0.45)]">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-[11px] uppercase tracking-[0.34em] text-cyan-100/55">Session Pulse</p>
          <h2 class="mt-3 text-2xl font-semibold text-white">当前会话任务</h2>
          <p class="mt-2 text-sm leading-6 text-slate-300">
            翻译页里的同步结果与异步任务都会保留在当前会话里，方便继续追踪和复核。
          </p>
        </div>
        <div
          class="hidden h-12 w-12 items-center justify-center rounded-2xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-100 shadow-[0_0_30px_rgba(56,189,248,0.18)] sm:flex"
        >
          ↗
        </div>
      </div>

      <div class="mt-5 rounded-[1.4rem] border border-white/8 bg-slate-950/65 p-4">
        <p class="text-[11px] uppercase tracking-[0.3em] text-slate-500">Active Focus</p>
        <template v-if="activeTask">
          <div class="mt-3 flex items-center justify-between gap-3">
            <div>
              <p class="text-sm font-medium text-white">{{ formatTaskKind(activeTask) }}</p>
              <p class="mt-1 break-all text-xs text-slate-400">{{ activeTask.jobUuid || activeTask.taskKey }}</p>
            </div>
            <span
              class="inline-flex rounded-full border px-2.5 py-1 text-xs"
              :class="statusMeta(activeTask.status).badge"
            >
              {{ statusMeta(activeTask.status).label }}
            </span>
          </div>
          <p class="mt-3 text-sm leading-6 text-slate-300">{{ activeTask.sourcePreview || '等待输入内容。' }}</p>
        </template>
        <template v-else>
          <p class="mt-3 text-sm leading-6 text-slate-400">当前没有进行中的异步任务，创建任务后这里会显示最新进度。</p>
        </template>
      </div>
    </section>

    <section class="rounded-[1.8rem] border border-white/10 bg-white/[0.03] p-5">
      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] uppercase tracking-[0.34em] text-cyan-100/55">Recent Runs</p>
          <h3 class="mt-2 text-xl font-semibold text-white">最近任务</h3>
        </div>
        <span class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs text-slate-300">
          {{ tasks.length }} 条
        </span>
      </div>

      <div v-if="tasks.length === 0" class="mt-5 rounded-[1.4rem] border border-dashed border-white/12 bg-slate-950/45 p-5 text-sm leading-6 text-slate-400">
        还没有任务记录。你可以先做一次同步快译，或者创建一个异步任务开始追踪状态。
      </div>

      <div v-else class="mt-5 space-y-3">
        <button
          v-for="task in tasks"
          :key="task.taskKey"
          type="button"
          class="w-full rounded-[1.35rem] border p-4 text-left transition"
          :class="task.taskKey === selectedTaskKey
            ? 'border-cyan-300/35 bg-cyan-400/10 shadow-[0_18px_40px_rgba(8,145,178,0.18)]'
            : 'border-white/8 bg-slate-950/50 hover:border-white/16 hover:bg-white/[0.05]'"
          @click="emit('select', task.taskKey)"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-1 text-[11px] uppercase tracking-[0.24em] text-slate-300">
                {{ formatTaskKind(task) }}
              </span>
              <span
                class="inline-flex rounded-full border px-2.5 py-1 text-xs"
                :class="statusMeta(task.status).badge"
              >
                {{ statusMeta(task.status).label }}
              </span>
            </div>
            <span class="text-xs text-slate-500">{{ formatTime(task.updatedAt || task.createdAt) }}</span>
          </div>

          <p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-200">{{ task.sourcePreview || '暂无源内容摘要。' }}</p>
          <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-400">
            <span class="truncate">{{ task.jobUuid || task.taskKey }}</span>
            <span>{{ task.sourceLang || 'zh' }} → {{ task.targetLang || 'en' }}</span>
          </div>
        </button>
      </div>
    </section>
  </aside>
</template>
