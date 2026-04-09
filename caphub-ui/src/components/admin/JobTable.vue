<script setup>
import { formatDateTime } from '../../utils/adminPresentation';

defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['view']);
</script>

<template>
  <el-table :data="rows" border>
    <el-table-column prop="id" label="ID" width="80" />
    <el-table-column prop="job_uuid" label="Job UUID" min-width="220" />
    <el-table-column prop="status" label="Status" width="120" />
    <el-table-column prop="source_lang" label="Source" width="100" />
    <el-table-column prop="target_lang" label="Target" width="100" />
    <el-table-column label="Started At" min-width="180">
      <template #default="{ row }">
        {{ formatDateTime(row.started_at) }}
      </template>
    </el-table-column>
    <el-table-column label="Finished At" min-width="180">
      <template #default="{ row }">
        {{ formatDateTime(row.finished_at) }}
      </template>
    </el-table-column>
    <el-table-column label="Actions" width="120">
      <template #default="{ row }">
        <el-button size="small" @click="emit('view', row)">Detail</el-button>
      </template>
    </el-table-column>
  </el-table>
</template>
