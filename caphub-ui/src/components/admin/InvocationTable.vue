<script setup>
import { useAdminI18n } from '../../composables/useAdminI18n';
import { formatDateTime, getStatusLabel, getStatusTagType } from '../../utils/adminPresentation';

defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
});

const { locale, t } = useAdminI18n();
</script>

<template>
  <el-table :data="rows" stripe>
    <el-table-column prop="id" :label="t('invocations.table.id')" width="90" />
    <el-table-column prop="agent_name" :label="t('invocations.table.agent')" min-width="180" />
    <el-table-column :label="t('invocations.table.status')" width="130">
      <template #default="{ row }">
        <el-tag :type="getStatusTagType(row.status)">
          {{ getStatusLabel(row.status, t) }}
        </el-tag>
      </template>
    </el-table-column>
    <el-table-column prop="duration_ms" :label="t('invocations.table.duration')" width="150" />
    <el-table-column prop="token_usage_estimate" :label="t('invocations.table.tokenUsage')" width="140" />
    <el-table-column :label="t('invocations.table.createdAt')" min-width="190">
      <template #default="{ row }">
        {{ formatDateTime(row.created_at, locale) }}
      </template>
    </el-table-column>
  </el-table>
</template>
