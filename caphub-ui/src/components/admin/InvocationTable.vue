<script setup>
import { useAdminI18n } from '../../composables/useAdminI18n';
import {
  formatDateTime,
  formatDurationMs,
  getStatusLabel,
  getStatusTagType,
} from '../../utils/adminPresentation';

defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
});

const { locale, t } = useAdminI18n();
</script>

<template>
  <div class="admin-table">
    <el-table :data="rows" stripe>
      <el-table-column prop="id" :label="t('invocations.table.id')" width="70" />
      <el-table-column prop="agent_name" :label="t('invocations.table.agent')" min-width="180">
        <template #default="{ row }">
          <span class="font-medium text-slate-800">{{ row.agent_name }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('invocations.table.status')" width="110" align="center">
        <template #default="{ row }">
          <el-tag size="small" :type="getStatusTagType(row.status)" effect="light" round>
            {{ getStatusLabel(row.status, t) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('invocations.table.duration')" width="100" align="center">
        <template #default="{ row }">
          <span
            class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="row.duration_ms ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-400'"
          >
            {{ formatDurationMs(row.duration_ms) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column :label="t('invocations.table.tokenUsage')" width="120" align="center">
        <template #default="{ row }">
          <span class="text-xs text-slate-500">{{ row.token_usage_estimate ?? '--' }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('invocations.table.createdAt')" min-width="170">
        <template #default="{ row }">
          <span class="text-xs text-slate-500">{{ formatDateTime(row.created_at, locale) }}</span>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>
