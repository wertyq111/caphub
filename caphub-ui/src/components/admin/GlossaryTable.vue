<script setup>
import { startCase } from '../../utils/adminPresentation';
import { useAdminI18n } from '../../composables/useAdminI18n';

defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
  deletingId: {
    type: [Number, String, null],
    default: null,
  },
});

const emit = defineEmits(['edit', 'delete']);
const { t } = useAdminI18n();
</script>

<template>
  <div class="admin-table">
    <el-table :data="rows" stripe>
      <el-table-column prop="id" :label="t('glossary.table.id')" width="70" />
      <el-table-column
        :label="t('glossary.table.term')"
        min-width="280"
      >
        <template #default="{ row }">
          <div class="space-y-1.5 py-1">
            <p class="text-sm font-semibold text-slate-900">{{ row.term }}</p>
            <p class="text-sm leading-relaxed text-slate-500">{{ row.standard_translation }}</p>
          </div>
        </template>
      </el-table-column>
      <el-table-column :label="t('glossary.table.languageDirection')" width="130" align="center">
        <template #default="{ row }">
          <el-tag size="small" effect="light" round>
            {{ row.source_lang }} → {{ row.target_lang }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('glossary.table.rule')" min-width="220">
        <template #default="{ row }">
          <div class="space-y-1.5 py-1">
            <p class="text-sm font-medium text-slate-700">{{ startCase(row.domain) }}</p>
            <p class="text-xs text-slate-500">P{{ row.priority ?? '--' }}</p>
          </div>
        </template>
      </el-table-column>
      <el-table-column :label="t('glossary.table.status')" width="128" align="center" class-name="status-chip-cell">
        <template #default="{ row }">
          <el-tag size="small" effect="light" round :type="row.status === 'active' ? 'success' : 'info'">
            {{ row.status === 'active' ? t('statuses.active') : t('statuses.inactive') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('common.actions')" width="150" fixed="right" align="center">
        <template #default="{ row }">
          <div class="flex items-center justify-center gap-1">
            <el-button size="small" type="primary" text @click="emit('edit', row)">{{ t('common.edit') }}</el-button>
            <el-popconfirm
              :title="t('glossary.deletePrompt', { term: row.term })"
              @confirm="emit('delete', row)"
            >
              <template #reference>
                <el-button
                  size="small"
                  type="danger"
                  text
                  :loading="deletingId === row.id"
                >
                  {{ t('common.delete') }}
                </el-button>
              </template>
            </el-popconfirm>
          </div>
        </template>
      </el-table-column>
    </el-table>
    <div class="hidden">
      <span v-for="row in rows" :key="row.id">{{ row.term }} {{ row.standard_translation }}</span>
    </div>
  </div>
</template>
