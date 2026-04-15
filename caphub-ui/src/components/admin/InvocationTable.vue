<script setup>
import { useAdminI18n } from '../../composables/useAdminI18n';
import {
  formatDateTime,
  formatBytes,
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
    <el-table :data="rows" stripe table-layout="auto" class="invocation-table" style="width: 100%">
      <el-table-column :label="t('invocations.table.id')" width="96" align="center" header-align="center" class-name="id-cell">
        <template #default="{ row }">
          <span class="id-value text-sm font-semibold text-slate-700">{{ row.id }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="agent_name" :label="t('invocations.table.agent')" min-width="360" show-overflow-tooltip>
        <template #default="{ row }">
          <span class="font-medium text-slate-800">{{ row.agent_name }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('invocations.table.status')" width="116" align="center" class-name="status-chip-cell">
        <template #default="{ row }">
          <el-tag size="small" :type="getStatusTagType(row.status)" effect="light" round>
            {{ getStatusLabel(row.status, t) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('invocations.table.duration')" width="120" align="center" class-name="timing-chip-cell">
        <template #default="{ row }">
          <span
            class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="row.duration_ms ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-400'"
          >
            {{ formatDurationMs(row.duration_ms) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column :label="t('invocations.table.tokenUsage')" width="132" align="center" header-align="center" class-name="bytes-cell">
        <template #default="{ row }">
          <span class="byte-value text-xs font-medium text-slate-500">{{ formatBytes(row.text_bytes, locale) }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('invocations.table.createdAt')" min-width="220" class-name="created-at-cell">
        <template #default="{ row }">
          <span class="created-at-value text-xs text-slate-500">{{ formatDateTime(row.created_at, locale) }}</span>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<style scoped>
.invocation-table :deep(.id-cell .cell),
.invocation-table :deep(.bytes-cell .cell),
.invocation-table :deep(.created-at-cell .cell) {
  white-space: nowrap;
}

.id-value,
.byte-value,
.created-at-value {
  font-variant-numeric: tabular-nums;
}
</style>
