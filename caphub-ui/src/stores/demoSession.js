import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

const TERMINAL_STATUSES = ['succeeded', 'failed', 'cancelled'];

function extractSourcePreview(payload) {
  const content = payload?.content ?? {};

  if (payload?.input_type === 'article_payload') {
    return [content.title, content.summary, content.body]
      .filter((value) => typeof value === 'string' && value.trim().length > 0)
      .join(' / ')
      .slice(0, 180);
  }

  return String(content.text ?? '')
    .trim()
    .slice(0, 180);
}

function extractTranslatedPreview(result) {
  const translatedDocument = result?.translated_document ?? {};
  const preferredValue = translatedDocument.text
    ?? translatedDocument.title
    ?? translatedDocument.summary
    ?? translatedDocument.body
    ?? Object.values(translatedDocument).find((value) => typeof value === 'string');

  return String(preferredValue ?? '')
    .trim()
    .slice(0, 180);
}

function buildTimestamp() {
  return new Date().toISOString();
}

export const useDemoSessionStore = defineStore('demoSession', () => {
  const tasks = ref([]);
  const selectedTaskKey = ref('');

  const recentTasks = computed(() => tasks.value.slice(0, 4));
  const featuredTask = computed(() => (
    tasks.value.find((task) => task.taskKey === selectedTaskKey.value)
    ?? tasks.value[0]
    ?? null
  ));
  const latestResultTask = computed(() => (
    tasks.value.find((task) => task.status === 'succeeded' && task.result)
    ?? null
  ));
  const activeAsyncTask = computed(() => (
    tasks.value.find((task) => task.kind === 'async' && !TERMINAL_STATUSES.includes(task.status))
    ?? null
  ));
  const summary = computed(() => ({
    total: tasks.value.length,
    running: tasks.value.filter((task) => task.kind === 'async' && !TERMINAL_STATUSES.includes(task.status)).length,
    completed: tasks.value.filter((task) => task.status === 'succeeded').length,
  }));

  function placeTaskFirst(nextTask) {
    tasks.value = [
      nextTask,
      ...tasks.value.filter((task) => task.taskKey !== nextTask.taskKey),
    ];

    if (!selectedTaskKey.value || selectedTaskKey.value === nextTask.taskKey) {
      selectedTaskKey.value = nextTask.taskKey;
    }
  }

  function recordSyncTranslation(payload, result) {
    const timestamp = buildTimestamp();
    const task = {
      taskKey: `sync-${Date.now()}`,
      kind: 'sync',
      status: 'succeeded',
      jobId: null,
      jobUuid: '',
      inputType: payload?.input_type ?? 'plain_text',
      sourceLang: payload?.source_lang ?? '',
      targetLang: payload?.target_lang ?? '',
      sourcePreview: extractSourcePreview(payload),
      translatedPreview: extractTranslatedPreview(result),
      glossaryCount: Array.isArray(result?.glossary_hits) ? result.glossary_hits.length : 0,
      riskCount: Array.isArray(result?.risk_flags) ? result.risk_flags.length : 0,
      result,
      meta: {
        mode: 'sync',
      },
      startedAt: timestamp,
      finishedAt: timestamp,
      createdAt: timestamp,
      updatedAt: timestamp,
    };

    placeTaskFirst(task);
    selectedTaskKey.value = task.taskKey;
  }

  function recordAsyncJob(payload, response) {
    const timestamp = buildTimestamp();
    const task = {
      taskKey: response?.job_uuid ?? `async-${Date.now()}`,
      kind: 'async',
      status: response?.status ?? 'pending',
      jobId: response?.job_id ?? null,
      jobUuid: response?.job_uuid ?? '',
      inputType: payload?.input_type ?? 'plain_text',
      sourceLang: payload?.source_lang ?? '',
      targetLang: payload?.target_lang ?? '',
      sourcePreview: extractSourcePreview(payload),
      translatedPreview: '',
      glossaryCount: 0,
      riskCount: 0,
      result: null,
      meta: {
        mode: 'async',
      },
      startedAt: '',
      finishedAt: '',
      createdAt: timestamp,
      updatedAt: timestamp,
      error: null,
    };

    placeTaskFirst(task);
    selectedTaskKey.value = task.taskKey;
  }

  function updateAsyncJobStatus(jobStatus) {
    const currentTask = tasks.value.find((task) => task.jobUuid === jobStatus?.job_uuid);
    if (!currentTask) {
      return;
    }

    placeTaskFirst({
      ...currentTask,
      status: jobStatus?.status ?? currentTask.status,
      startedAt: jobStatus?.started_at ?? currentTask.startedAt,
      finishedAt: jobStatus?.finished_at ?? currentTask.finishedAt,
      updatedAt: buildTimestamp(),
      error: jobStatus?.error ?? currentTask.error ?? null,
    });
  }

  function attachAsyncResult(jobUuid, result) {
    const currentTask = tasks.value.find((task) => task.jobUuid === jobUuid);
    if (!currentTask) {
      return;
    }

    placeTaskFirst({
      ...currentTask,
      status: result?.status ?? 'succeeded',
      translatedPreview: extractTranslatedPreview(result),
      glossaryCount: Array.isArray(result?.glossary_hits) ? result.glossary_hits.length : 0,
      riskCount: Array.isArray(result?.risk_flags) ? result.risk_flags.length : 0,
      result,
      meta: {
        ...(currentTask.meta ?? {}),
        ...(result?.meta ?? {}),
      },
      finishedAt: result?.finished_at ?? currentTask.finishedAt ?? buildTimestamp(),
      updatedAt: buildTimestamp(),
      error: result?.error ?? null,
    });
  }

  function selectTask(taskKey) {
    selectedTaskKey.value = taskKey;
  }

  return {
    recentTasks,
    featuredTask,
    latestResultTask,
    activeAsyncTask,
    selectedTaskKey,
    summary,
    recordSyncTranslation,
    recordAsyncJob,
    updateAsyncJobStatus,
    attachAsyncResult,
    selectTask,
  };
});
