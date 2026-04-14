<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { ElConfigProvider } from 'element-plus';
import { CloseBold, Expand, SwitchButton } from '@element-plus/icons-vue';
import { adminNavigationItems, getAdminPageMeta } from '../admin/navigation';
import AdminLocaleSwitch from '../components/admin/AdminLocaleSwitch.vue';
import { useAdminI18n } from '../composables/useAdminI18n';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const sidebarOpen = ref(false);

const { elementLocale, t } = useAdminI18n();

const currentPageMeta = computed(() => getAdminPageMeta(route.name));

const userLabel = computed(() => auth.user?.name || auth.user?.email || 'Admin');
const userInitials = computed(() =>
  userLabel.value
    .split(/[\s@._-]+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((segment) => segment[0]?.toUpperCase() ?? '')
    .join(''),
);

watch(
  () => route.fullPath,
  () => {
    sidebarOpen.value = false;
  },
);

function isNavItemActive(item) {
  return item.matchNames.includes(route.name);
}

function signOut() {
  auth.logout();
  router.push('/admin/login');
}
</script>

<template>
  <el-config-provider :locale="elementLocale">
    <div class="min-h-screen bg-slate-100 text-slate-900">
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-sm lg:hidden"
        @click="sidebarOpen = false"
      />

      <aside
        class="fixed inset-y-0 left-0 z-50 w-72 border-r border-slate-800/80 bg-slate-950 text-slate-200 shadow-[0_18px_60px_rgba(15,23,42,0.3)] transition-transform duration-300 lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
      >
        <div class="flex h-full flex-col">
          <div class="border-b border-white/10 px-6 pb-6 pt-7">
            <RouterLink to="/admin/dashboard" class="block">
              <p class="text-[11px] font-semibold uppercase tracking-[0.35em] text-sky-300/80">
                CapHub
              </p>
              <h2 class="mt-3 text-2xl font-semibold text-white">
                {{ t('brand.name') }}
              </h2>
              <p class="mt-2 text-sm leading-6 text-slate-400">
                {{ t('brand.tagline') }}
              </p>
            </RouterLink>
          </div>

          <nav class="flex-1 space-y-2 px-4 py-5">
            <RouterLink
              v-for="item in adminNavigationItems"
              :key="item.routeName"
              :to="item.path"
              class="flex items-start gap-3 rounded-[20px] px-4 py-3 transition"
              :class="
                isNavItemActive(item)
                  ? 'bg-sky-400/15 text-white shadow-[inset_0_0_0_1px_rgba(125,211,252,0.2)]'
                  : 'text-slate-400 hover:bg-white/5 hover:text-white'
              "
            >
              <span
                class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl"
                :class="
                  isNavItemActive(item)
                    ? 'bg-sky-400/15 text-sky-200'
                    : 'bg-white/5 text-slate-400'
                "
              >
                <el-icon size="18">
                  <component :is="item.icon" />
                </el-icon>
              </span>
              <span class="min-w-0">
                <span class="block text-sm font-semibold">{{ t(item.labelKey) }}</span>
                <span class="mt-1 block text-xs leading-5 text-slate-500">
                  {{ t(item.descriptionKey) }}
                </span>
              </span>
            </RouterLink>
          </nav>
        </div>
      </aside>

      <div class="lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-[#f4f7fb]/88 backdrop-blur-xl">
          <div class="flex min-h-20 items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
              <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm shadow-slate-900/5 transition hover:text-slate-950 lg:hidden"
                :aria-label="t('toolbar.openNavigation')"
                @click="sidebarOpen = true"
              >
                <el-icon size="18"><Expand /></el-icon>
              </button>

              <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">
                  {{ t('toolbar.sectionLabel') }}
                </p>
                <p class="mt-1 truncate text-sm text-slate-500">
                  {{ t(currentPageMeta.titleKey) }} · {{ t(currentPageMeta.descriptionKey) }}
                </p>
              </div>
            </div>

            <div class="flex shrink-0 items-center gap-3">
              <AdminLocaleSwitch />

              <div class="hidden items-center gap-3 rounded-full border border-slate-200 bg-white px-2 py-2 shadow-sm shadow-slate-900/5 sm:flex">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-950 text-xs font-semibold text-white">
                  {{ userInitials || 'A' }}
                </span>
                <div class="min-w-0 pr-2">
                  <p class="text-xs text-slate-400">{{ t('toolbar.signedInAs') }}</p>
                  <p class="max-w-[180px] truncate text-sm font-semibold text-slate-950">
                    {{ userLabel }}
                  </p>
                </div>
                <button
                  type="button"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-950"
                  :aria-label="t('toolbar.signOut')"
                  @click="signOut"
                >
                  <el-icon size="18"><SwitchButton /></el-icon>
                </button>
              </div>

              <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 shadow-sm shadow-slate-900/5 transition hover:text-slate-950 sm:hidden"
                :aria-label="t('toolbar.signOut')"
                @click="signOut"
              >
                <el-icon size="18"><SwitchButton /></el-icon>
              </button>
            </div>
          </div>
        </header>

        <main class="min-h-[calc(100vh-81px)] bg-slate-100 px-4 py-6 sm:px-6 lg:px-8">
          <slot />
        </main>
      </div>

      <button
        v-if="sidebarOpen"
        type="button"
        class="fixed right-4 top-4 z-[60] inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/20 bg-slate-950/70 text-white shadow-lg lg:hidden"
        @click="sidebarOpen = false"
      >
        <el-icon size="18"><CloseBold /></el-icon>
      </button>
    </div>
  </el-config-provider>
</template>

<style scoped>
:deep(.admin-table) {
  padding: 0 1rem 1rem;
}

:deep(.el-table) {
  --el-table-border-color: #e2e8f0;
  --el-table-header-bg-color: #f8fafc;
  --el-table-row-hover-bg-color: #f0f7ff;
  font-size: 13px;
}

:deep(.el-table th.el-table__cell) {
  color: #475569;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 14px 12px;
}

:deep(.el-table td.el-table__cell) {
  color: #1e293b;
  padding: 12px 12px;
}

:deep(.el-table--striped .el-table__body tr.el-table__row--striped td.el-table__cell) {
  background: #fafbfc;
}

:deep(.el-table .el-table__row:hover > td.el-table__cell) {
  background-color: #f0f7ff !important;
}

:deep(.el-tag--small) {
  font-size: 11px;
  height: 22px;
  line-height: 20px;
  padding: 0 8px;
}

:deep(.el-descriptions__label) {
  width: 42%;
  color: #64748b;
  font-weight: 600;
}

:deep(.el-descriptions__content) {
  color: #0f172a;
}

@media (min-width: 640px) {
  :deep(.admin-table) {
    padding-inline: 1.5rem;
  }
}
</style>
