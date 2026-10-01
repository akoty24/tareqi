<script setup>
import { useId } from 'vue'

/**
 * Label + control + error message. The default slot receives `{ id, invalid }`
 * so the control can be wired to the label and aria-invalid.
 */
defineProps({
  label: { type: String, required: true },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
})
const id = useId()
</script>

<template>
  <div>
    <label :for="id" class="label">{{ label }}</label>
    <slot :id="id" :invalid="!!error" />
    <p v-if="hint && !error" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
    <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
  </div>
</template>
