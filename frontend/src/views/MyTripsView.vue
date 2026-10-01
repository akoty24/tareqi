<script setup>
import { onMounted, reactive } from 'vue'
import { tripsApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import TripCard from '@/components/TripCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationBar from '@/components/PaginationBar.vue'

const filters = reactive({ scope: 'upcoming', status: '' })
const { items, meta, loading, load } = usePaginated((params) => tripsApi.mine(params), filters)
const statuses = ['draft', 'published', 'full', 'started', 'completed', 'cancelled']

onMounted(() => load(1))
</script>

<template>
  <div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h1 class="page-title !mb-0">{{ $t('nav.myTrips') }}</h1>
      <RouterLink :to="{ name: 'trip-create' }" class="btn-primary btn-sm">{{ $t('nav.createTrip') }}</RouterLink>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
      <div class="inline-flex rounded-xl bg-white p-1 ring-1 ring-slate-200" role="tablist">
        <button
          v-for="s in ['upcoming', 'past']"
          :key="s"
          type="button"
          role="tab"
          :aria-selected="filters.scope === s"
          class="rounded-lg px-4 py-1.5 text-sm font-semibold"
          :class="filters.scope === s ? 'bg-brand-700 text-white' : 'text-slate-600'"
          @click="filters.scope = s"
        >{{ $t(`myTrips.${s}`) }}</button>
      </div>
      <label class="sr-only" for="mt-status">{{ $t('common.status') }}</label>
      <select id="mt-status" v-model="filters.status" class="input !w-auto !min-h-10 py-1">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ $t(`status.trip.${s}`) }}</option>
      </select>
    </div>

    <LoadingState v-if="loading" />
    <template v-else>
      <div v-if="items.length" class="grid gap-3 md:grid-cols-2">
        <TripCard v-for="trip in items" :key="trip.id" :trip="trip" show-status :show-owner="false" />
      </div>
      <EmptyState v-else icon="car" :title="$t('myTrips.empty')" :text="$t('myTrips.emptyHint')">
        <RouterLink :to="{ name: 'trip-create' }" class="btn-primary">{{ $t('home.addTrip') }}</RouterLink>
      </EmptyState>
      <PaginationBar :meta="meta" @change="load" />
    </template>
  </div>
</template>
