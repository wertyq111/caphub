<script setup>
defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <el-table :data="rows" border>
    <el-table-column prop="id" label="ID" width="80" />
    <el-table-column prop="agent_name" label="Agent" min-width="180" />
    <el-table-column prop="status" label="Status" width="120" />
    <el-table-column prop="duration_ms" label="Duration (ms)" width="140" />
    <el-table-column prop="token_usage_estimate" label="Token Usage" width="130" />
    <el-table-column prop="created_at" label="Created At" min-width="180" />
  </el-table>
</template>
