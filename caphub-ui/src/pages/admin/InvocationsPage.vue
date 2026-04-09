<script setup>
import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { fetchAiInvocations } from '../../api/admin';
import AdminPageHeader from '../../components/admin/AdminPageHeader.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import AdminStateBlock from '../../components/admin/AdminStateBlock.vue';
import InvocationTable from '../../components/admin/InvocationTable.vue';
import { useAdminI18n } from '../../composables/useAdminI18n';
import { resolveRequestError } from '../../utils/adminPresentation';

const { t } = useAdminI18n();

const invocationsQuery = useQuery({
  queryKey: ['admin', 'invocations'],
  queryFn: () => fetchAiInvocations({ per_page: 50 }),
});

const rows = computed(() => invocationsQuery.data.value?.data ?? []);
const errorMessage = computed(() =>
  invocationsQuery.error.value
    ? resolveRequestError(invocationsQuery.error.value, t('states.errorDescription'))
    : '',
);
</script>

<template>
  <div class="space-y-6">
    <AdminPageHeader
      eyebrow="CapHub"
      :title="t('pages.invocations.title')"
      :subtitle="t('pages.invocations.description')"
    />

    <AdminPanel :padded="false">
      <InvocationTable v-if="rows.length" :rows="rows" />
      <AdminStateBlock
        v-else-if="invocationsQuery.isPending.value"
        mode="loading"
        :title="t('states.loadingTitle')"
        :description="t('states.loadingDescription')"
      />
      <AdminStateBlock
        v-else-if="invocationsQuery.isError.value"
        mode="error"
        :title="t('states.errorTitle')"
        :description="errorMessage"
      >
        <el-button @click="invocationsQuery.refetch()">{{ t('common.retry') }}</el-button>
      </AdminStateBlock>
      <AdminStateBlock
        v-else
        mode="empty"
        :title="t('states.emptyTitle')"
        :description="t('states.emptyDescription')"
      />
    </AdminPanel>
  </div>
</template>
