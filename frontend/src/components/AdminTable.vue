<script setup>
import LoadingState from './LoadingState.vue'
import PaginationBar from './PaginationBar.vue'

/** Responsive table shell for admin lists: horizontal scroll on small screens. */
defineProps({
  columns: { type: Array, required: true }, // translation keys
  items: { type: Array, required: true },
  meta: { type: Object, default: null },
  loading: Boolean,
})
defineEmits(['page'])
</script>

<template>
  <div>
    <LoadingState v-if="loading" />
    <div v-else class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <table class="w-full min-w-[720px] text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th v-for="col in columns" :key="col" scope="col" class="px-3 py-2 text-start font-semibold">{{ $t(col) }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <slot />
          <tr v-if="!items.length">
            <td :colspan="columns.length" class="px-3 py-8 text-center text-slate-500">{{ $t('common.noResults') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <PaginationBar :meta="meta" @change="$emit('page', $event)" />
  </div>
</template>
