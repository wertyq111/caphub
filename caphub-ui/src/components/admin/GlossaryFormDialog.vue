<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  initialValue: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['update:modelValue', 'submit']);

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

function close() {
  emit('update:modelValue', false);
}

function submit() {
  emit('submit', { ...form });
}
</script>

<template>
  <el-dialog :model-value="modelValue" title="Glossary Entry" width="640" @close="close">
    <el-form label-width="160px">
      <el-form-item label="Term"><el-input v-model="form.term" /></el-form-item>
      <el-form-item label="Standard Translation"><el-input v-model="form.standard_translation" /></el-form-item>
      <el-form-item label="Source Language"><el-input v-model="form.source_lang" /></el-form-item>
      <el-form-item label="Target Language"><el-input v-model="form.target_lang" /></el-form-item>
      <el-form-item label="Domain"><el-input v-model="form.domain" /></el-form-item>
      <el-form-item label="Priority"><el-input-number v-model="form.priority" :min="1" :max="999999" /></el-form-item>
      <el-form-item label="Status">
        <el-select v-model="form.status">
          <el-option value="active" label="active" />
          <el-option value="inactive" label="inactive" />
        </el-select>
      </el-form-item>
      <el-form-item label="Notes"><el-input v-model="form.notes" type="textarea" rows="3" /></el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="close">Cancel</el-button>
      <el-button type="primary" @click="submit">Save</el-button>
    </template>
  </el-dialog>
</template>
