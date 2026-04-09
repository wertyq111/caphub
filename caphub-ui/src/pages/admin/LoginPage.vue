<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const errorMessage = ref('');
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

  try {
    await auth.login(form);
    router.push('/admin/dashboard');
  } catch (error) {
    errorMessage.value = error?.response?.data?.message ?? 'Login failed.';
  }
}
</script>

<template>
  <div class="mx-auto max-w-xl rounded-xl border border-slate-200 bg-white p-6">
    <h1 class="mb-4 text-2xl font-semibold">Admin Login</h1>
    <el-form label-width="100px">
      <el-form-item label="Email"><el-input v-model="form.email" /></el-form-item>
      <el-form-item label="Password"><el-input v-model="form.password" type="password" show-password /></el-form-item>
      <el-form-item>
        <el-button type="primary" @click="submit">Login</el-button>
      </el-form-item>
    </el-form>
    <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
  </div>
</template>
