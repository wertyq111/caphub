<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import AdminLocaleSwitch from '../../components/admin/AdminLocaleSwitch.vue';
import { useAdminI18n } from '../../composables/useAdminI18n';
import { useAuthStore } from '../../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const errorMessage = ref('');
const submitting = ref(false);
const { t } = useAdminI18n();

const form = reactive({
  email: 'admin@example.com',
  password: 'password',
});

/**
 * 提交登录请求并进入管理后台，参数：无。
 * @since 2026-04-02
 * @author zhouxufeng
 */
async function submit() {
  errorMessage.value = '';
  submitting.value = true;

  try {
    await auth.login(form);
    router.push('/admin/dashboard');
  } catch (error) {
    errorMessage.value = error?.response?.data?.message ?? t('login.error');
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-6xl items-center">
    <div class="grid w-full gap-8 lg:grid-cols-[minmax(0,1.08fr)_minmax(380px,440px)]">
      <section
        class="relative hidden overflow-hidden rounded-[36px] bg-slate-950 px-10 py-12 text-white shadow-[0_40px_120px_rgba(15,23,42,0.35)] lg:flex lg:flex-col lg:justify-between"
      >
        <div
          class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.18),transparent_22%),radial-gradient(circle_at_bottom_left,rgba(37,99,235,0.2),transparent_28%)]"
        />
        <div class="relative z-10">
          <span
            class="inline-flex rounded-full border border-sky-300/25 bg-white/6 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-[0.28em] text-sky-200"
          >
            {{ t('login.badge') }}
          </span>
          <h1 class="mt-6 max-w-lg text-5xl font-semibold leading-[1.08] tracking-tight">
            {{ t('login.title') }}
          </h1>
          <p class="mt-5 max-w-xl text-base leading-7 text-slate-300">
            {{ t('login.panelBody') }}
          </p>
        </div>

        <div class="relative z-10 grid gap-4">
          <div class="rounded-[26px] border border-white/10 bg-white/5 p-5 backdrop-blur">
            <p class="text-sm font-semibold text-white">{{ t('login.panelTitle') }}</p>
            <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-300">
              <li>{{ t('login.panelFeatureOne') }}</li>
              <li>{{ t('login.panelFeatureTwo') }}</li>
              <li>{{ t('login.panelFeatureThree') }}</li>
            </ul>
          </div>
        </div>
      </section>

      <section class="flex items-center justify-center">
        <div
          class="w-full rounded-[32px] border border-white/80 bg-white/92 p-8 shadow-[0_28px_90px_rgba(148,163,184,0.22)] backdrop-blur"
        >
          <div class="flex items-start justify-between gap-6">
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">
                {{ t('brand.name') }}
              </p>
              <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">
                {{ t('login.formTitle') }}
              </h2>
              <p class="mt-2 text-sm leading-6 text-slate-500">
                {{ t('login.formSubtitle') }}
              </p>
            </div>
            <AdminLocaleSwitch />
          </div>

          <p class="mt-6 text-sm leading-6 text-slate-500">
            {{ t('login.helper') }}
          </p>

          <el-alert
            v-if="errorMessage"
            class="mt-6"
            type="error"
            :closable="false"
            :title="errorMessage"
          />

          <el-form class="mt-6" label-position="top">
            <el-form-item :label="t('login.email')">
              <el-input v-model="form.email" size="large" />
            </el-form-item>
            <el-form-item :label="t('login.password')">
              <el-input v-model="form.password" size="large" type="password" show-password />
            </el-form-item>
            <el-form-item class="mb-0 mt-4">
              <el-button type="primary" size="large" class="!w-full" :loading="submitting" @click="submit">
                {{ t('login.submit') }}
              </el-button>
            </el-form-item>
          </el-form>
        </div>
      </section>
    </div>
  </div>
</template>
