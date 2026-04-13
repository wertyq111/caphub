<script setup>
import { useAdminI18n } from '../../composables/useAdminI18n';
import {
  buildSourcePreview,
  formatDateTime,
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
      <el-table-column prop="id" :label="t('jobs.table.id')" width="90" />
      <el-table-column :label="t('jobs.table.job')" min-width="280">
        <template #default="{ row }">
          <div class="space-y-2 py-1">
            <p class="text-sm font-semibold text-slate-950">{{ row.job_uuid }}</p>
            <div class="flex flex-wrap gap-2">
              <el-tag size="small" effect="plain" round>{{ startCase(row.mode) }}</el-tag>
              <el-tag size="small" effect="plain" round type="info">
                {{ startCase(row.input_type) }}
              </el-tag>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column :label="t('jobs.table.status')" width="130">
        <template #default="{ row }">
          <el-tag :type="getStatusTagType(row.status)">
            {{ getStatusLabel(row.status, t) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('jobs.table.translationBody')" min-width="360">
        <template #default="{ row }">
          <p
            class="text-sm leading-6 text-slate-600"
            style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"
          >
            {{ buildSourcePreview(row) }}
          </p>
        </template>
      </el-table-column>
      <el-table-column :label="t('jobs.table.startedAt')" min-width="190">
        <template #default="{ row }">
          {{ formatDateTime(row.started_at, locale) }}
        </template>
      </el-table-column>
      <el-table-column :label="t('jobs.table.finishedAt')" min-width="190">
        <template #default="{ row }">
          {{ formatDateTime(row.finished_at, locale) }}
        </template>
      </el-table-column>
      <el-table-column :label="t('common.actions')" width="120" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="emit('view', row)">{{ t('common.detail') }}</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>
