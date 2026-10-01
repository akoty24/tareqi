<script setup>
import { nextTick, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
})
const emit = defineEmits(['close'])
const panel = ref(null)

// Move focus into the dialog when it opens (keyboard / screen reader users).
watch(() => props.open, async (open) => {
  if (open) {
    await nextTick()
    panel.value?.querySelector('input, textarea, select, button')?.focus()
  }
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 sm:items-center sm:p-4"
      @click.self="emit('close')"
      @keydown.esc="emit('close')"
    >
      <div
        ref="panel"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl"
      >
        <h2 class="mb-4 text-lg font-extrabold">{{ title }}</h2>
        <slot />
        <div v-if="$slots.actions" class="mt-5 flex flex-wrap justify-end gap-2">
          <slot name="actions" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
