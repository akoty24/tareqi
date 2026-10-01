<script setup>
import { formatNumber } from '@/utils/format'

defineProps({ meta: { type: Object, default: null } })
defineEmits(['change'])
</script>

<template>
  <nav v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-between gap-2" :aria-label="$t('common.pagination')">
    <button type="button" class="btn-secondary btn-sm" :disabled="meta.current_page <= 1" @click="$emit('change', meta.current_page - 1)">
      {{ $t('common.prev') }}
    </button>
    <span class="text-sm text-slate-600">
      {{ $t('common.pageOf', { page: formatNumber(meta.current_page), total: formatNumber(meta.last_page) }) }}
    </span>
    <button type="button" class="btn-secondary btn-sm" :disabled="meta.current_page >= meta.last_page" @click="$emit('change', meta.current_page + 1)">
      {{ $t('common.next') }}
    </button>
  </nav>
</template>
