<script setup>
import AppLogo from '@/components/AppLogo.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-brand-50 to-slate-50 px-4 py-10">
    <AppLogo class="mb-2" large />
    <p class="mb-8 text-center text-slate-600">{{ $t('app.tagline') }}</p>
    <main class="w-full max-w-md">
      <RouterView />
    </main>
  </div>
</template>
