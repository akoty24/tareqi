<script setup>
import { computed } from 'vue'

const props = defineProps({
  user: { type: Object, default: null },
  size: { type: String, default: 'md' },
})

const initials = computed(() => (props.user?.name || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0]).join(''))
const sizeClass = computed(() => ({ sm: 'size-9 text-sm', md: 'size-12 text-base', lg: 'size-20 text-2xl' })[props.size])
</script>

<template>
  <img
    v-if="user?.profile_photo_url"
    :src="user.profile_photo_url"
    :alt="user.name"
    class="shrink-0 rounded-full object-cover"
    :class="sizeClass"
  />
  <span
    v-else
    class="inline-flex shrink-0 items-center justify-center rounded-full bg-brand-100 font-bold text-brand-800"
    :class="sizeClass"
    aria-hidden="true"
  >{{ initials }}</span>
</template>
