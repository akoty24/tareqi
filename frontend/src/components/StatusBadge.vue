<script setup>
import { computed } from 'vue'

/** kind: trip | booking | request | report | user */
const props = defineProps({
  kind: { type: String, required: true },
  status: { type: String, required: true },
})

const colors = {
  draft: 'bg-slate-100 text-slate-700',
  published: 'bg-emerald-100 text-emerald-800',
  full: 'bg-amber-100 text-amber-800',
  started: 'bg-sky-100 text-sky-800',
  completed: 'bg-indigo-100 text-indigo-800',
  cancelled: 'bg-red-100 text-red-700',
  pending: 'bg-amber-100 text-amber-800',
  confirmed: 'bg-emerald-100 text-emerald-800',
  rejected: 'bg-red-100 text-red-700',
  active: 'bg-emerald-100 text-emerald-800',
  fulfilled: 'bg-indigo-100 text-indigo-800',
  expired: 'bg-slate-100 text-slate-600',
  under_review: 'bg-sky-100 text-sky-800',
  resolved: 'bg-emerald-100 text-emerald-800',
  dismissed: 'bg-slate-100 text-slate-600',
  blocked: 'bg-red-100 text-red-700',
}

const color = computed(() => colors[props.status] || 'bg-slate-100 text-slate-700')
</script>

<template>
  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold" :class="color">
    {{ $t(`status.${kind}.${status}`) }}
  </span>
</template>
