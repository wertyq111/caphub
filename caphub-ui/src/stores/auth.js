import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import http from '../api/http';

export const useAuthStore = defineStore('auth', () => {
  const persistedToken = localStorage.getItem('caphub_admin_token') ?? '';
  const persistedUser = localStorage.getItem('caphub_admin_user');

  const token = ref(persistedToken);
  const user = ref(persistedUser ? JSON.parse(persistedUser) : null);

  const isAuthenticated = computed(() => token.value.length > 0);

  /**
   * 调用后台登录接口并写入本地认证状态，参数：payload 登录参数（email、password）。
   * @since 2026-04-02
   * @author zhouxufeng
   */
  async function login(payload) {
    const { data } = await http.post('/admin/login', payload);
    token.value = data.token;
    user.value = data.user;
    localStorage.setItem('caphub_admin_token', data.token);
    localStorage.setItem('caphub_admin_user', JSON.stringify(data.user));
    return data;
  }

  /**
   * 清空本地登录态并退出后台，参数：无。
   * @since 2026-04-02
   * @author zhouxufeng
   */
  function logout() {
    token.value = '';
    user.value = null;
    localStorage.removeItem('caphub_admin_token');
    localStorage.removeItem('caphub_admin_user');
  }

  return {
    token,
    user,
    isAuthenticated,
    login,
    logout,
  };
});
