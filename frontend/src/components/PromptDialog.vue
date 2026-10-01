<script setup>
import { ref, watch } from 'vue'
import ModalDialog from './ModalDialog.vue'

/** Confirmation dialog with an optional free-text reason. */
const props = defineProps({
  open: Boolean,
  title: { type: String, required: true },
  message: { type: String, default: '' },
  confirmLabel: { type: String, required: true },
  withReason: Boolean,
  danger: Boolean,
  busy: Boolean,
})
const emit = defineEmits(['close', 'confirm'])
const reason = ref('')

watch(() => props.open, () => (reason.value = ''))
</script>

<template>
  <ModalDialog :open="open" :title="title" @close="emit('close')">
    <p v-if="message" class="text-slate-600">{{ message }}</p>
    <div v-if="withReason" class="mt-4">
      <label for="prompt-reason" class="label">{{ $t('common.reason') }} <span class="font-normal text-slate-400">({{ $t('common.optional') }})</span></label>
      <input id="prompt-reason" v-model="reason" type="text" maxlength="255" class="input" />
    </div>
    <template #actions>
      <button type="button" class="btn-secondary" @click="emit('close')">{{ $t('common.back') }}</button>
      <button type="button" :class="danger ? 'btn-danger' : 'btn-primary'" :disabled="busy" @click="emit('confirm', reason)">
        {{ confirmLabel }}
      </button>
    </template>
  </ModalDialog>
</template>
