<script setup>
import AppIcon from './AppIcon.vue'

/** Read-only when `readonly`, otherwise an accessible 1-5 radio group. */
const model = defineModel({ type: Number, default: 0 })
defineProps({ readonly: Boolean, size: { type: String, default: 'size-7' } })
</script>

<template>
  <div v-if="readonly" class="inline-flex text-amber-500" :aria-label="$t('rating.stars', { n: model })">
    <AppIcon v-for="n in 5" :key="n" name="star" :class="[size, n <= Math.round(model) ? 'fill-current' : 'text-slate-300']" />
  </div>
  <div v-else class="inline-flex gap-1" role="radiogroup" :aria-label="$t('rating.choose')">
    <button
      v-for="n in 5"
      :key="n"
      type="button"
      role="radio"
      :aria-checked="model === n"
      :aria-label="$t('rating.stars', { n })"
      class="rounded p-0.5 text-amber-500"
      @click="model = n"
    >
      <AppIcon name="star" :class="[size, n <= model ? 'fill-current' : 'text-slate-300']" />
    </button>
  </div>
</template>
