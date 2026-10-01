<script setup>
import { watch } from 'vue'
import ModalDialog from './ModalDialog.vue'
import { reportsApi } from '@/api'
import { useForm } from '@/composables/useForm'

/** target: { trip_id?, booking_id?, reported_user_id? } */
const props = defineProps({
  open: Boolean,
  target: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['close'])
const reasons = ['unsafe_driving', 'harassment', 'no_show', 'fraud', 'inappropriate_content', 'other']

const { fields, error, submitting, submit, reset } = useForm({ reason: 'other', description: '' })

watch(() => props.open, (open) => open && reset({ reason: 'other', description: '' }))

async function send() {
  await submit((data) => reportsApi.create({ ...props.target, ...data }))
  emit('close')
}
</script>

<template>
  <ModalDialog :open="open" :title="$t('report.title')" @close="emit('close')">
    <form id="report-form" class="space-y-4" @submit.prevent="send">
      <p class="text-sm text-slate-600">{{ $t('report.hint') }}</p>
      <div>
        <label for="report-reason" class="label">{{ $t('report.reason') }}</label>
        <select id="report-reason" v-model="fields.reason" class="input">
          <option v-for="r in reasons" :key="r" :value="r">{{ $t(`report.reasons.${r}`) }}</option>
        </select>
        <p v-if="error('reason')" class="mt-1 text-sm text-red-600">{{ error('reason') }}</p>
      </div>
      <div>
        <label for="report-description" class="label">{{ $t('report.description') }}</label>
        <textarea id="report-description" v-model="fields.description" rows="4" maxlength="2000" class="input" />
      </div>
    </form>
    <template #actions>
      <button type="button" class="btn-secondary" @click="emit('close')">{{ $t('common.cancel') }}</button>
      <button type="submit" form="report-form" class="btn-danger" :disabled="submitting">{{ $t('report.send') }}</button>
    </template>
  </ModalDialog>
</template>
