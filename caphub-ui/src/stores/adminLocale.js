import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import en from 'element-plus/es/locale/lang/en.mjs';
import zhCn from 'element-plus/es/locale/lang/zh-cn.mjs';
import {
  ADMIN_LOCALE_STORAGE_KEY,
  DEFAULT_ADMIN_LOCALE,
  isSupportedAdminLocale,
} from '../locales/admin';

function getStorage() {
  const storage = globalThis.localStorage;

  if (
    storage
    && typeof storage.getItem === 'function'
    && typeof storage.setItem === 'function'
  ) {
    return storage;
  }

  return null;
}

function normalizeLocale(value) {
  return isSupportedAdminLocale(value) ? value : DEFAULT_ADMIN_LOCALE;
}

export const useAdminLocaleStore = defineStore('adminLocale', () => {
  const storage = getStorage();
  const locale = ref(normalizeLocale(storage?.getItem(ADMIN_LOCALE_STORAGE_KEY)));

  const elementPlusLocale = computed(() => (locale.value === 'en' ? en : zhCn));

  function setLocale(nextLocale) {
    locale.value = normalizeLocale(nextLocale);
    storage?.setItem(ADMIN_LOCALE_STORAGE_KEY, locale.value);
  }

  function toggleLocale() {
    setLocale(locale.value === 'zh-CN' ? 'en' : 'zh-CN');
  }

  return {
    locale,
    elementPlusLocale,
    setLocale,
    toggleLocale,
  };
});
