<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { adminSections } from '@/router/adminSections'
import AppLogo from '@/components/AppLogo.vue'
import AppIcon from '@/components/AppIcon.vue'

const auth = useAuthStore()
// Only the sections the staff member's role allows.
const links = computed(() => adminSections.filter((s) => auth.can(s.permission)))
</script>

<template>
  <div class="min-h-screen lg:flex">
    <!-- Desktop sidebar -->
    <aside class="sticky top-0 hidden h-screen w-60 shrink-0 flex-col bg-slate-900 text-white lg:flex">
      <div class="flex items-center gap-2 px-4 py-4">
        <AppLogo dark />
        <span class="rounded bg-amber-400 px-2 text-xs font-bold text-slate-900">{{ $t('admin.badge') }}</span>
      </div>
      <nav class="flex-1 space-y-0.5 overflow-y-auto px-2" :aria-label="$t('admin.nav')">
        <RouterLink
          v-for="link in links"
          :key="link.name"
          :to="{ name: link.name }"
          class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-slate-800 hover:text-white"
          :exact-active-class="link.name === 'admin' ? '!bg-white !text-slate-900 font-bold' : ''"
          :active-class="link.name === 'admin' ? '' : '!bg-white !text-slate-900 font-bold'"
        >
          <AppIcon :name="link.icon" class="size-4" /> {{ $t(link.label) }}
        </RouterLink>
      </nav>
      <div class="border-t border-slate-800 p-3 text-sm">
        <p class="truncate font-semibold">{{ auth.user?.name }}</p>
        <p class="truncate text-xs text-amber-300">{{ auth.user?.role?.display_name }}</p>
        <RouterLink :to="{ name: 'home' }" class="mt-2 inline-flex items-center gap-1 text-slate-300 hover:text-white">
          <AppIcon name="arrow" class="size-4 rtl:rotate-180" /> {{ $t('admin.backToApp') }}
        </RouterLink>
      </div>
    </aside>

    <div class="min-w-0 flex-1">
      <!-- Mobile / tablet header -->
      <header class="safe-top sticky top-0 z-30 bg-slate-900 text-white lg:hidden">
        <div class="flex h-14 items-center gap-3 px-4">
          <AppLogo dark />
          <span class="rounded bg-amber-400 px-2 text-xs font-bold text-slate-900">{{ $t('admin.badge') }}</span>
          <RouterLink :to="{ name: 'home' }" class="ms-auto text-sm text-slate-300 hover:text-white">
            {{ $t('admin.backToApp') }}
          </RouterLink>
        </div>
        <nav class="flex gap-1 overflow-x-auto px-2 pb-2" :aria-label="$t('admin.nav')">
          <RouterLink
            v-for="link in links"
            :key="link.name"
            :to="{ name: link.name }"
            class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white"
            :exact-active-class="link.name === 'admin' ? '!bg-white !text-slate-900 font-bold' : ''"
            :active-class="link.name === 'admin' ? '' : '!bg-white !text-slate-900 font-bold'"
          >
            <AppIcon :name="link.icon" class="size-4" /> {{ $t(link.label) }}
          </RouterLink>
        </nav>
      </header>
      <main class="mx-auto max-w-7xl px-4 py-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
