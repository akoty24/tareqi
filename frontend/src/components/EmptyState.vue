<script setup>
import AppIcon from './AppIcon.vue'

defineProps({
  icon: { type: String, default: 'info' },
  title: { type: String, required: true },
  text: { type: String, default: '' },
})
</script>

<template>
  <div class="card flex flex-col items-center py-10 text-center">
    <AppIcon :name="icon" class="mb-3 size-12 text-slate-300" />
    <p class="text-lg font-bold text-slate-700">{{ title }}</p>
    <p v-if="text" class="mt-1 max-w-sm text-slate-500">{{ text }}</p>
    <div v-if="$slots.default" class="mt-4 flex flex-wrap justify-center gap-2"><slot /></div>
  </div>
</template>
