<script setup>
import { ref, useId } from 'vue'
import { tripsApi } from '@/api'

/** Free-text place with suggestions from places already used in trips. */
const model = defineModel({ type: String, default: '' })
defineProps({
  label: { type: String, required: true },
  placeholder: { type: String, default: '' },
  error: { type: String, default: '' },
  required: Boolean,
})

const id = useId()
const listId = `${id}-list`
const suggestions = ref([])
let timer = null

function onInput() {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    try {
      suggestions.value = (await tripsApi.places(model.value)).data
    } catch {
      suggestions.value = []
    }
  }, 250)
}
</script>

<template>
  <div>
    <label :for="id" class="label">{{ label }}</label>
    <input
      :id="id"
      v-model.trim="model"
      type="text"
      class="input"
      :class="{ 'input-error': error }"
      :placeholder="placeholder"
      :list="listId"
      :required="required"
      :aria-invalid="!!error"
      autocomplete="off"
      maxlength="120"
      @input="onInput"
      @focus="onInput"
    />
    <datalist :id="listId">
      <option v-for="s in suggestions" :key="s" :value="s" />
    </datalist>
    <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
  </div>
</template>
