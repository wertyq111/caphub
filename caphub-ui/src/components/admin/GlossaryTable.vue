<script setup>
defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['edit']);
</script>

<template>
  <div>
    <el-table :data="rows" border>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="term" label="Term" min-width="180" />
      <el-table-column prop="standard_translation" label="Standard Translation" min-width="180" />
      <el-table-column prop="source_lang" label="Source" width="100" />
      <el-table-column prop="target_lang" label="Target" width="100" />
      <el-table-column label="Actions" width="120">
        <template #default="{ row }">
          <el-button size="small" @click="emit('edit', row)">Edit</el-button>
        </template>
      </el-table-column>
    </el-table>
    <div class="hidden">
      <span v-for="row in rows" :key="row.id">{{ row.term }} {{ row.standard_translation }}</span>
    </div>
  </div>
</template>
