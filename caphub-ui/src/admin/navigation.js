import {
  CollectionTag,
  HomeFilled,
  Operation,
  Tickets,
} from '@element-plus/icons-vue';

export const adminNavigationItems = [
  {
    routeName: 'admin-dashboard',
    path: '/admin/dashboard',
    icon: HomeFilled,
    labelKey: 'navigation.dashboard',
    descriptionKey: 'navigationDescriptions.dashboard',
    matchNames: ['admin-dashboard'],
  },
  {
    routeName: 'admin-glossaries',
    path: '/admin/glossaries',
    icon: CollectionTag,
    labelKey: 'navigation.glossaries',
    descriptionKey: 'navigationDescriptions.glossaries',
    matchNames: ['admin-glossaries'],
  },
  {
    routeName: 'admin-jobs',
    path: '/admin/jobs',
    icon: Tickets,
    labelKey: 'navigation.jobs',
    descriptionKey: 'navigationDescriptions.jobs',
    matchNames: ['admin-jobs', 'admin-job-detail'],
  },
  {
    routeName: 'admin-invocations',
    path: '/admin/invocations',
    icon: Operation,
    labelKey: 'navigation.invocations',
    descriptionKey: 'navigationDescriptions.invocations',
    matchNames: ['admin-invocations'],
  },
];

export const adminPageMetaByRouteName = {
  'admin-dashboard': {
    titleKey: 'pages.dashboard.title',
    descriptionKey: 'pages.dashboard.description',
  },
  'admin-glossaries': {
    titleKey: 'pages.glossaries.title',
    descriptionKey: 'pages.glossaries.description',
  },
  'admin-jobs': {
    titleKey: 'pages.jobs.title',
    descriptionKey: 'pages.jobs.description',
  },
  'admin-job-detail': {
    titleKey: 'pages.jobDetail.title',
    descriptionKey: 'pages.jobDetail.description',
  },
  'admin-invocations': {
    titleKey: 'pages.invocations.title',
    descriptionKey: 'pages.invocations.description',
  },
};

export function getAdminPageMeta(routeName) {
  return adminPageMetaByRouteName[routeName] ?? {
    titleKey: 'brand.name',
    descriptionKey: 'toolbar.subtitle',
  };
}
