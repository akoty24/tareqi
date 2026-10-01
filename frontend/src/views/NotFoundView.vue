<template>
  <div class="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
    <p class="text-6xl" aria-hidden="true">🚗💨</p>
    <h1 class="text-2xl font-extrabold">{{ $t('notFound.title') }}</h1>
    <p class="text-slate-600">{{ $t('notFound.text') }}</p>
    <RouterLink :to="{ name: 'home' }" class="btn-primary">{{ $t('nav.home') }}</RouterLink>
  </div>
</template>
