<script setup>
import { computed, reactive, watch } from 'vue';
import { useAdminI18n } from '../../composables/useAdminI18n';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  initialValue: {
    type: Object,
    default: () => ({}),
  },
  submitting: {
    type: Boolean,
    default: false,
  },
  errorMessage: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue', 'submit']);
const { t } = useAdminI18n();

const form = reactive({
  term: '',
  source_lang: 'en',
  target_lang: 'zh',
  standard_translation: '',
  domain: 'chemical_news',
  priority: 100,
  status: 'active',
  notes: '',
});

watch(
  () => props.initialValue,
  (value) => {
    form.term = value?.term ?? '';
    form.source_lang = value?.source_lang ?? 'en';
    form.target_lang = value?.target_lang ?? 'zh';
    form.standard_translation = value?.standard_translation ?? '';
    form.domain = value?.domain ?? 'chemical_news';
    form.priority = value?.priority ?? 100;
    form.status = value?.status ?? 'active';
    form.notes = value?.notes ?? '';
  },
  { immediate: true },
);

const dialogTitle = computed(() =>
  props.initialValue?.id ? t('glossary.form.titleEdit') : t('glossary.form.titleCreate'),
);

function close() {
  emit('update:modelValue', false);
}

function submit() {
  emit('submit', { ...form });
}
</script>

<template>
  <el-dialog
    :model-value="modelValue"
    :title="dialogTitle"
    width="720"
    destroy-on-close
    align-center
    @close="close"
  >
    <el-alert
      v-if="errorMessage"
      class="mb-5"
      type="error"
      :closable="false"
      :title="errorMessage"
    />

    <el-form label-position="top">
      <div class="grid gap-4 md:grid-cols-2">
        <el-form-item :label="t('glossary.form.term')">
          <el-input v-model="form.term" />
        </el-form-item>
        <el-form-item :label="t('glossary.form.standardTranslation')">
          <el-input v-model="form.standard_translation" />
        </el-form-item>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <el-form-item :label="t('glossary.form.sourceLanguage')">
          <el-input v-model="form.source_lang" />
        </el-form-item>
        <el-form-item :label="t('glossary.form.targetLanguage')">
          <el-input v-model="form.target_lang" />
        </el-form-item>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <el-form-item :label="t('glossary.form.domain')">
          <el-input v-model="form.domain" />
        </el-form-item>
        <el-form-item :label="t('glossary.form.priority')">
          <el-input-number v-model="form.priority" class="!w-full" :min="1" :max="999999" />
        </el-form-item>
      </div>

      <el-form-item :label="t('glossary.form.status')">
        <el-select v-model="form.status">
          <el-option value="active" :label="t('statuses.active')" />
          <el-option value="inactive" :label="t('statuses.inactive')" />
        </el-select>
      </el-form-item>

      <el-form-item :label="t('glossary.form.notes')">
        <el-input v-model="form.notes" type="textarea" :rows="3" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="close">{{ t('common.cancel') }}</el-button>
      <el-button type="primary" :loading="submitting" @click="submit">
        {{ t('common.save') }}
      </el-button>
    </template>
  </el-dialog>
</template>
