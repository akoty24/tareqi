<script setup>
import { ref, watch } from 'vue'
import ModalDialog from './ModalDialog.vue'
import StarRating from './StarRating.vue'
import { bookingsApi } from '@/api'
import { useForm } from '@/composables/useForm'

const props = defineProps({
  open: Boolean,
  booking: { type: Object, default: null },
  personName: { type: String, default: '' },
})
const emit = defineEmits(['close', 'rated'])

const { fields, error, submitting, submit, reset } = useForm({ stars: 5, review: '' })
const localError = ref('')

watch(() => props.open, (open) => open && reset({ stars: 5, review: '' }))

async function save() {
  if (!fields.stars) {
    localError.value = 'rating.choose'
    return
  }
  await submit((data) => bookingsApi.rate(props.booking.id, data))
  emit('rated')
  emit('close')
}
</script>

<template>
  <ModalDialog :open="open" :title="$t('rating.title', { name: personName })" @close="emit('close')">
    <form id="rating-form" class="space-y-4" @submit.prevent="save">
      <div class="text-center">
        <StarRating v-model="fields.stars" size="size-10" />
        <p v-if="error('stars') || localError" class="text-sm text-red-600">{{ error('stars') || $t(localError) }}</p>
      </div>
      <div>
        <label for="rating-review" class="label">{{ $t('rating.review') }} <span class="font-normal text-slate-400">({{ $t('common.optional') }})</span></label>
        <textarea id="rating-review" v-model="fields.review" rows="3" maxlength="1000" class="input" :placeholder="$t('rating.reviewPlaceholder')" />
      </div>
    </form>
    <template #actions>
      <button type="button" class="btn-secondary" @click="emit('close')">{{ $t('common.cancel') }}</button>
      <button type="submit" form="rating-form" class="btn-primary" :disabled="submitting">{{ $t('rating.submit') }}</button>
    </template>
  </ModalDialog>
</template>
