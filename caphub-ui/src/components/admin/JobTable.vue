<script setup>
import { useAdminI18n } from '../../composables/useAdminI18n';
import {
  buildSourcePreview,
  formatDateTime,
  formatDuration,
  getStatusLabel,
  getStatusTagType,
  startCase,
} from '../../utils/adminPresentation';

defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['view']);
const { locale, t } = useAdminI18n();
</script>

<template>
  <div class="admin-table">
    <el-table :data="rows" stripe>
      <el-table-column :label="t('jobs.table.job')" min-width="240">
        <template #default="{ row }">
          <div class="space-y-1.5 py-1">
            <p class="text-xs font-mono text-slate-500 truncate" :title="row.job_uuid">{{ row.job_uuid }}</p>
            <div class="flex flex-wrap gap-1.5">
              <el-tag size="small" effect="plain" round>{{ startCase(row.mode) }}</el-tag>
              <el-tag size="small" effect="plain" round type="info">
                {{ startCase(row.input_type) }}
              </el-tag>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column :label="t('jobs.table.status')" width="110" align="center">
        <template #default="{ row }">
          <el-tag size="small" :type="getStatusTagType(row.status)" effect="light" round>
            {{ getStatusLabel(row.status, t) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('jobs.table.translationBody')" min-width="320">
        <template #default="{ row }">
          <p
            class="text-sm leading-relaxed text-slate-600"
            style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"
          >
            {{ buildSourcePreview(row) }}
          </p>
        </template>
      </el-table-column>
      <el-table-column :label="t('jobs.table.duration')" width="100" align="center" class-name="timing-chip-cell">
        <template #default="{ row }">
          <span
            class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="formatDuration(row.started_at, row.finished_at) === '--'
              ? 'bg-slate-100 text-slate-400'
              : 'bg-sky-50 text-sky-700'"
          >
            {{ formatDuration(row.started_at, row.finished_at) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column :label="t('jobs.table.startedAt')" min-width="170">
        <template #default="{ row }">
          <span class="text-xs text-slate-500">{{ formatDateTime(row.started_at, locale) }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('jobs.table.finishedAt')" min-width="170">
        <template #default="{ row }">
          <span class="text-xs text-slate-500">{{ formatDateTime(row.finished_at, locale) }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('common.actions')" width="100" fixed="right" align="center">
        <template #default="{ row }">
          <el-button size="small" type="primary" text @click="emit('view', row)">{{ t('common.detail') }}</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>
