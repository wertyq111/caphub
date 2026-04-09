import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

export const routes = [
  {
    path: '/',
    name: 'demo-home',
    component: () => import('../pages/demo/HomePage.vue'),
    meta: {
      layout: 'demo',
    },
  },
  {
    path: '/demo',
    redirect: '/demo/translate',
    meta: {
      layout: 'demo',
    },
  },
  {
    path: '/demo/translate',
    name: 'demo-translate',
    component: () => import('../pages/demo/TranslatePage.vue'),
    meta: {
      layout: 'demo',
    },
  },
  {
    path: '/demo/jobs/:jobId',
    name: 'demo-job',
    component: () => import('../pages/demo/JobPage.vue'),
    meta: {
      layout: 'demo',
    },
  },
  {
    path: '/demo/results/:jobId',
    name: 'demo-result',
    component: () => import('../pages/demo/ResultPage.vue'),
    meta: {
      layout: 'demo',
    },
  },
  {
    path: '/admin/login',
    name: 'admin-login',
    component: () => import('../pages/admin/LoginPage.vue'),
    meta: {
      layout: 'admin-auth',
      requiresAuth: false,
    },
  },
  {
    path: '/admin',
    redirect: '/admin/dashboard',
    meta: {
      layout: 'admin',
      requiresAuth: true,
    },
  },
  {
    path: '/admin/dashboard',
    name: 'admin-dashboard',
    component: () => import('../pages/admin/DashboardPage.vue'),
    meta: {
      layout: 'admin',
      requiresAuth: true,
    },
  },
  {
    path: '/admin/glossaries',
    name: 'admin-glossaries',
    component: () => import('../pages/admin/GlossaryPage.vue'),
    meta: {
      layout: 'admin',
      requiresAuth: true,
    },
  },
  {
    path: '/admin/jobs',
    name: 'admin-jobs',
    component: () => import('../pages/admin/JobsPage.vue'),
    meta: {
      layout: 'admin',
      requiresAuth: true,
    },
  },
  {
    path: '/admin/jobs/:jobId',
    name: 'admin-job-detail',
    component: () => import('../pages/admin/JobDetailPage.vue'),
    meta: {
      layout: 'admin',
      requiresAuth: true,
    },
  },
  {
    path: '/admin/invocations',
    name: 'admin-invocations',
    component: () => import('../pages/admin/InvocationsPage.vue'),
    meta: {
      layout: 'admin',
      requiresAuth: true,
    },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to) => {
  if (!to.meta.requiresAuth) {
    return true;
  }

  const auth = useAuthStore();
  if (auth.isAuthenticated) {
    return true;
  }

  return '/admin/login';
});

export default router;
