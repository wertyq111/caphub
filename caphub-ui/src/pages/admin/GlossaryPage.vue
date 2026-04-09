<script setup>
import { computed, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import {
  createGlossary,
  deleteGlossary,
  fetchGlossaries,
  updateGlossary,
} from '../../api/admin';
import AdminPageHeader from '../../components/admin/AdminPageHeader.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import AdminStateBlock from '../../components/admin/AdminStateBlock.vue';
import GlossaryTable from '../../components/admin/GlossaryTable.vue';
import GlossaryFormDialog from '../../components/admin/GlossaryFormDialog.vue';
import { useAdminI18n } from '../../composables/useAdminI18n';
import { resolveRequestError } from '../../utils/adminPresentation';

const queryClient = useQueryClient();
const dialogVisible = ref(false);
const editingRow = ref(null);
const dialogErrorMessage = ref('');
const deletingId = ref(null);
const { t } = useAdminI18n();

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

const deleteMutation = useMutation({
  mutationFn: deleteGlossary,
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'glossaries'] }),
});

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
);
const totalCount = computed(() => glossaryQuery.data.value?.total ?? rows.value.length);

const queryErrorMessage = computed(() =>
  glossaryQuery.error.value
    ? resolveRequestError(glossaryQuery.error.value, t('states.errorDescription'))
    : '',
);

/**
 * 打开创建术语弹窗，参数：无。
 * @since 2026-04-02
 * @author zhouxufeng
 */
function openCreateDialog() {
  editingRow.value = null;
  dialogErrorMessage.value = '';
  dialogVisible.value = true;
}

/**
 * 打开编辑术语弹窗，参数：row 当前选中行数据。
 * @since 2026-04-02
 * @author zhouxufeng
 */
function openEditDialog(row) {
  editingRow.value = row;
  dialogErrorMessage.value = '';
  dialogVisible.value = true;
}

/**
 * 提交术语弹窗数据，参数：payload 表单提交数据。
 * @since 2026-04-02
 * @author zhouxufeng
 */
async function submitDialog(payload) {
  dialogErrorMessage.value = '';

  try {
    if (editingRow.value?.id) {
      await updateMutation.mutateAsync({ id: editingRow.value.id, payload });
    } else {
      await createMutation.mutateAsync(payload);
    }

    dialogVisible.value = false;
  } catch (error) {
    dialogErrorMessage.value = resolveRequestError(error, t('states.errorDescription'));
  }
}

async function removeGlossary(row) {
  deletingId.value = row.id;

  try {
    await deleteMutation.mutateAsync(row.id);
    ElMessage.success(t('glossary.deleteSuccess'));
  } catch (error) {
    ElMessage.error(resolveRequestError(error, t('states.errorDescription')));
  } finally {
    deletingId.value = null;
  }
}
</script>

<template>
  <div class="space-y-6">
    <AdminPageHeader
      eyebrow="CapHub"
      :title="t('pages.glossaries.title')"
      :subtitle="t('pages.glossaries.description')"
    >
      <template #actions>
        <el-button type="primary" size="large" @click="openCreateDialog">
          {{ t('glossary.newEntry') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <AdminPanel
      :title="t('glossary.listTitle')"
      :subtitle="t('glossary.listSubtitle')"
      :padded="false"
    >
      <template #header-actions>
        <el-tag round effect="plain">{{ t('common.totalRecords', { count: totalCount }) }}</el-tag>
        <el-button @click="glossaryQuery.refetch()">{{ t('common.refresh') }}</el-button>
      </template>

      <GlossaryTable
        v-if="rows.length"
        :rows="rows"
        :deleting-id="deletingId"
        @edit="openEditDialog"
        @delete="removeGlossary"
      />
      <AdminStateBlock
        v-else-if="glossaryQuery.isPending.value"
        mode="loading"
        :title="t('states.loadingTitle')"
        :description="t('states.loadingDescription')"
      />
      <AdminStateBlock
        v-else-if="glossaryQuery.isError.value"
        mode="error"
        :title="t('states.errorTitle')"
        :description="queryErrorMessage"
      >
        <el-button @click="glossaryQuery.refetch()">{{ t('common.retry') }}</el-button>
      </AdminStateBlock>
      <AdminStateBlock
        v-else
        mode="empty"
        :title="t('states.emptyTitle')"
        :description="t('states.emptyDescription')"
      />
    </AdminPanel>

    <GlossaryFormDialog
      v-model="dialogVisible"
      :error-message="dialogErrorMessage"
      :initial-value="editingRow ?? {}"
      :submitting="isSubmitting"
      @submit="submitDialog"
    />
  </div>
</template>
