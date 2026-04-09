import { storeToRefs } from 'pinia';
import { useAdminLocaleStore } from '../stores/adminLocale';
import { resolveAdminMessage } from '../locales/admin';
import { startCase } from '../utils/adminPresentation';

export function useAdminI18n() {
  const localeStore = useAdminLocaleStore();
  const { locale } = storeToRefs(localeStore);

  function t(key, fallback = key) {
    return resolveAdminMessage(locale.value, key, fallback);
  }

  function tValue(group, value, fallback) {
    if (!value) {
      return fallback ?? t('common.noData', '--');
    }

    return t(`values.${group}.${value}`, fallback ?? startCase(value));
  }

  return {
    locale,
    setLocale: localeStore.setLocale,
    toggleLocale: localeStore.toggleLocale,
    t,
    tStatus: (value) => tValue('status', value, startCase(value)),
    tMode: (value) => tValue('mode', value, startCase(value)),
    tDocumentType: (value) => tValue('documentType', value, startCase(value)),
    tInputType: (value) => tValue('inputType', value, startCase(value)),
    tSignal: (value) => tValue('signals', value, startCase(value)),
  };
}
