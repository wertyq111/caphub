<script setup>
import { computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { fetchTranslationJobDetail } from '../../api/admin';
import AdminPageHeader from '../../components/admin/AdminPageHeader.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import AdminStateBlock from '../../components/admin/AdminStateBlock.vue';
import { useAdminI18n } from '../../composables/useAdminI18n';
import {
  buildSourceDocument,
  buildTranslatedDocument,
  formatDateTime,
  getStatusLabel,
  getStatusTagType,
  resolveRequestError,
} from '../../utils/adminPresentation';

const router = useRouter();
const route = useRoute();
const { locale, t } = useAdminI18n();
const jobId = computed(() => String(route.params.jobId ?? ''));

const jobQuery = useQuery({
  queryKey: computed(() => ['admin', 'job-detail', jobId.value]),
  queryFn: () => fetchTranslationJobDetail(jobId.value),
  enabled: computed(() => jobId.value.length > 0),
});

const job = computed(() => jobQuery.data.value ?? null);
const errorMessage = computed(() =>
  jobQuery.error.value ? resolveRequestError(jobQuery.error.value, t('states.errorDescription')) : '',
);

const overviewItems = computed(() => [
  { label: 'ID', value: job.value?.id ?? '--' },
  { label: t('jobs.detail.jobUuid'), value: job.value?.job_uuid ?? '--' },
  {
    label: t('jobs.detail.status'),
    value: getStatusLabel(job.value?.status, t),
    type: getStatusTagType(job.value?.status),
    tag: true,
  },
]);

const configurationItems = computed(() => [
  { label: t('jobs.detail.sourceLanguage'), value: job.value?.source_lang ?? '--' },
  { label: t('jobs.detail.targetLanguage'), value: job.value?.target_lang ?? '--' },
  { label: t('jobs.detail.mode'), value: job.value?.mode ?? '--' },
  { label: t('jobs.detail.inputType'), value: job.value?.input_type ?? '--' },
]);

const timingItems = computed(() => [
  {
    label: t('jobs.detail.createdAt'),
    value: formatDateTime(job.value?.created_at, locale.value),
  },
  {
    label: t('jobs.detail.updatedAt'),
    value: formatDateTime(job.value?.updated_at, locale.value),
  },
  {
    label: t('jobs.detail.startedAt'),
    value: formatDateTime(job.value?.started_at, locale.value),
  },
  {
    label: t('jobs.detail.finishedAt'),
    value: formatDateTime(job.value?.finished_at, locale.value),
  },
]);

const sourceDocument = computed(() => buildSourceDocument(job.value));
const translatedDocument = computed(() => buildTranslatedDocument(job.value));
</script>

<template>
  <div class="space-y-6">
    <AdminPageHeader
      eyebrow="CapHub"
      :title="t('pages.jobDetail.title')"
      :subtitle="t('pages.jobDetail.description')"
    >
      <template #actions>
        <el-button size="large" @click="router.push('/admin/jobs')">
          {{ t('common.back') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <AdminStateBlock
      v-if="jobQuery.isPending.value"
      mode="loading"
      :title="t('states.loadingTitle')"
      :description="t('states.loadingDescription')"
    />

    <AdminStateBlock
      v-else-if="jobQuery.isError.value"
      mode="error"
      :title="t('states.errorTitle')"
      :description="errorMessage"
    >
      <el-button @click="jobQuery.refetch()">{{ t('common.retry') }}</el-button>
    </AdminStateBlock>

    <div v-else-if="job" class="space-y-4">
      <div class="grid gap-4 xl:grid-cols-3">
        <AdminPanel :title="t('jobs.detail.overview')">
          <el-descriptions :column="1" border>
            <el-descriptions-item
              v-for="item in overviewItems"
              :key="item.label"
              :label="item.label"
            >
              <el-tag v-if="item.tag" :type="item.type">{{ item.value }}</el-tag>
              <span v-else>{{ item.value }}</span>
            </el-descriptions-item>
          </el-descriptions>
        </AdminPanel>

        <AdminPanel :title="t('jobs.detail.configuration')">
          <el-descriptions :column="1" border>
            <el-descriptions-item
              v-for="item in configurationItems"
              :key="item.label"
              :label="item.label"
            >
              {{ item.value }}
            </el-descriptions-item>
          </el-descriptions>
        </AdminPanel>

        <AdminPanel :title="t('jobs.detail.timing')">
          <el-descriptions :column="1" border>
            <el-descriptions-item
              v-for="item in timingItems"
              :key="item.label"
              :label="item.label"
            >
              {{ item.value }}
            </el-descriptions-item>
          </el-descriptions>
        </AdminPanel>
      </div>

      <div class="grid gap-4 xl:grid-cols-2">
        <AdminPanel :title="t('jobs.detail.sourceBody')">
          <div class="max-h-[420px] overflow-auto rounded-[24px] bg-slate-50 p-5 text-sm leading-7 text-slate-700 whitespace-pre-wrap">
            {{ sourceDocument }}
          </div>
        </AdminPanel>

        <AdminPanel :title="t('jobs.detail.translatedBody')">
          <div class="max-h-[420px] overflow-auto rounded-[24px] bg-slate-50 p-5 text-sm leading-7 text-slate-700 whitespace-pre-wrap">
            {{ translatedDocument }}
          </div>
        </AdminPanel>
      </div>
    </div>

    <AdminStateBlock
      v-else
      mode="empty"
      :title="t('states.emptyTitle')"
      :description="t('states.emptyDescription')"
    />
  </div>
</template>
