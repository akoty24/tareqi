<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatShortDate } from '@/utils/format'
import AdminTable from '@/components/AdminTable.vue'
import PromptDialog from '@/components/PromptDialog.vue'
import StarRating from '@/components/StarRating.vue'

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()
const filters = reactive({ search: '', stars: '', with_review: '' })
const search = ref('')
const deleting = ref(null)
const { items, meta, loading, load, page } = usePaginated((p) => adminApi.ratings(p), filters)

async function remove() {
  try {
    toast.success((await adminApi.deleteRating(deleting.value.id)).message)
    await load(page.value)
  } catch (e) {
    toast.error(e.message)
  } finally {
    deleting.value = null
  }
}

onMounted(() => load(1))
</script>

<template>
  <div>
    <h1 class="page-title">{{ $t('admin.ratings') }}</h1>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="filters.search = search">
      <input v-model="search" type="search" class="input !w-64" :placeholder="$t('admin.searchUsers')" :aria-label="$t('common.search')" />
      <select v-model="filters.stars" class="input !w-auto" :aria-label="$t('admin.stars')">
        <option value="">{{ $t('admin.allStars') }}</option>
        <option v-for="n in [1, 2, 3, 4, 5]" :key="n" :value="String(n)">{{ '★'.repeat(n) }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="filters.with_review" type="checkbox" true-value="1" false-value="" class="size-4 accent-brand-700" />
        {{ $t('admin.withReview') }}
      </label>
      <button type="submit" class="btn-primary btn-sm">{{ $t('common.search') }}</button>
    </form>

    <AdminTable :columns="['admin.rater', 'admin.ratedUser', 'admin.stars', 'admin.reviewText', 'admin.route', 'admin.created', 'common.actions']" :items="items" :meta="meta" :loading="loading" @page="load">
      <tr v-for="r in items" :key="r.id">
        <td class="px-3 py-2">{{ r.rater?.name }}</td>
        <td class="px-3 py-2 font-semibold">{{ r.rated_user?.name }}</td>
        <td class="px-3 py-2"><StarRating :model-value="r.stars" readonly size="size-4" /></td>
        <td class="max-w-xs px-3 py-2"><p class="line-clamp-2" :title="r.review">{{ r.review || '—' }}</p></td>
        <td class="px-3 py-2">{{ r.trip ? `${r.trip.origin} ← ${r.trip.destination}` : '—' }}</td>
        <td class="px-3 py-2">{{ formatShortDate(r.created_at) }}</td>
        <td class="px-3 py-2">
          <button v-if="auth.can('ratings.manage')" type="button" class="btn-danger btn-sm" @click="deleting = r">{{ $t('common.delete') }}</button>
        </td>
      </tr>
    </AdminTable>

    <PromptDialog
      :open="!!deleting"
      :title="t('admin.deleteRatingTitle')"
      :message="t('admin.deleteRatingMessage')"
      :confirm-label="t('common.delete')"
      danger
      @close="deleting = null"
      @confirm="remove"
    />
  </div>
</template>
