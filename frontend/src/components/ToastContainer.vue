<script setup>
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()
const styles = {
  success: 'bg-emerald-600',
  error: 'bg-red-600',
  info: 'bg-slate-800',
}
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite" role="status">
    <TransitionGroup
      enter-from-class="opacity-0 -translate-y-2"
      enter-active-class="transition duration-200"
      leave-to-class="opacity-0"
      leave-active-class="transition duration-200"
    >
      <button
        v-for="item in toast.items"
        :key="item.id"
        type="button"
        class="pointer-events-auto w-full max-w-md rounded-xl px-4 py-3 text-start font-semibold text-white shadow-lg"
        :class="styles[item.type]"
        @click="toast.dismiss(item.id)"
      >
        {{ item.message }}
      </button>
    </TransitionGroup>
  </div>
</template>
