<script setup>
defineProps({ large: Boolean, dark: Boolean })
</script>

<template>
  <span class="inline-flex items-center gap-2 font-extrabold" :class="[large ? 'text-4xl' : 'text-xl', dark ? 'text-white' : 'text-brand-800']">
    <span aria-hidden="true" :class="large ? 'text-4xl' : 'text-2xl'">🚗</span>
    {{ $t('app.name') }}
  </span>
</template>
