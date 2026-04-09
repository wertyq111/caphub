import axios from 'axios';
import { useAuthStore } from '../stores/auth';

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8090/api',
  timeout: 30000,
});

/**
 * 请求拦截器：自动注入登录令牌，参数：config 当前请求配置。
 * @since 2026-04-02
 * @author zhouxufeng
 */
http.interceptors.request.use((config) => {
  const auth = useAuthStore();

  if (auth.token) {
    config.headers = {
      ...config.headers,
      Authorization: `Bearer ${auth.token}`,
    };
  }

  return config;
});

export default http;
