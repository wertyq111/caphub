<script setup>
import { computed, ref } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { createGlossary, fetchGlossaries, updateGlossary } from '../../api/admin';
import GlossaryTable from '../../components/admin/GlossaryTable.vue';
import GlossaryFormDialog from '../../components/admin/GlossaryFormDialog.vue';

const queryClient = useQueryClient();
const dialogVisible = ref(false);
const editingRow = ref(null);

const glossaryQuery = useQuery({
  queryKey: ['admin', 'glossaries'],
  queryFn: () => fetchGlossaries({ per_page: 50 }),
});

const rows = computed(() => glossaryQuery.data.value?.data ?? []);

const createMutation = useMutation({
  mutationFn: createGlossary,
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'glossaries'] }),
});

const updateMutation = useMutation({
  mutationFn: ({ id, payload }) => updateGlossary(id, payload),
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'glossaries'] }),
});

/**
 * 打开创建术语弹窗，参数：无。
 * @since 2026-04-02
 * @author zhouxufeng
 */
function openCreateDialog() {
  editingRow.value = null;
  dialogVisible.value = true;
}

/**
 * 打开编辑术语弹窗，参数：row 当前选中行数据。
 * @since 2026-04-02
 * @author zhouxufeng
 */
function openEditDialog(row) {
  editingRow.value = row;
  dialogVisible.value = true;
}

/**
 * 提交术语弹窗数据，参数：payload 表单提交数据。
 * @since 2026-04-02
 * @author zhouxufeng
 */
async function submitDialog(payload) {
  if (editingRow.value?.id) {
    await updateMutation.mutateAsync({ id: editingRow.value.id, payload });
  } else {
    await createMutation.mutateAsync(payload);
  }

  dialogVisible.value = false;
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-3xl font-semibold">Glossaries</h1>
      <el-button type="primary" @click="openCreateDialog">New Glossary</el-button>
    </div>

    <GlossaryTable :rows="rows" @edit="openEditDialog" />

    <GlossaryFormDialog
      v-model="dialogVisible"
      :initial-value="editingRow ?? {}"
      @submit="submitDialog"
    />
  </div>
</template>
