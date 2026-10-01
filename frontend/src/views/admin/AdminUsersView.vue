<script setup>
import { onMounted, reactive, ref } from 'vue'
import { adminApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import { formatNumber, formatShortDate } from '@/utils/format'
import AdminTable from '@/components/AdminTable.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const toast = useToastStore()
const auth = useAuthStore()
const filters = reactive({ search: '', status: '', role: '' })
const search = ref('')
const { items, meta, loading, load, page } = usePaginated((p) => adminApi.users(p), filters)

async function toggle(user) {
  try {
    const response = user.status === 'active' ? await adminApi.blockUser(user.id) : await adminApi.unblockUser(user.id)
    toast.success(response.message)
    await load(page.value)
  } catch (e) {
    toast.error(e.message)
  }
}

onMounted(() => load(1))
</script>

<template>
  <div>
    <h1 class="page-title">{{ $t('admin.users') }}</h1>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="filters.search = search">
      <input v-model="search" type="search" class="input !w-64" :placeholder="$t('admin.searchUsers')" :aria-label="$t('common.search')" />
      <select v-model="filters.status" class="input !w-auto" :aria-label="$t('common.status')">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option value="active">{{ $t('status.user.active') }}</option>
        <option value="blocked">{{ $t('status.user.blocked') }}</option>
      </select>
      <select v-model="filters.role" class="input !w-auto" :aria-label="$t('admin.role')">
        <option value="">{{ $t('admin.allRoles') }}</option>
        <option value="user">{{ $t('admin.roles.user') }}</option>
        <option value="admin">{{ $t('admin.roles.admin') }}</option>
      </select>
      <button type="submit" class="btn-primary btn-sm">{{ $t('common.search') }}</button>
    </form>

    <AdminTable :columns="['auth.name', 'auth.phone', 'auth.email', 'admin.rating', 'admin.completed', 'common.status', 'admin.joined', 'common.actions']" :items="items" :meta="meta" :loading="loading" @page="load">
      <tr v-for="u in items" :key="u.id">
        <td class="px-3 py-2 font-semibold">
          <RouterLink :to="{ name: 'user', params: { id: u.id } }" class="hover:underline">{{ u.name }}</RouterLink>
          <span v-if="u.role === 'admin'" class="ms-1 rounded bg-amber-100 px-1 text-xs text-amber-800">{{ $t('admin.roles.admin') }}</span>
        </td>
        <td class="px-3 py-2" dir="ltr">{{ u.phone }}</td>
        <td class="px-3 py-2" dir="ltr">{{ u.email }}</td>
        <td class="px-3 py-2">{{ u.ratings_count ? `${formatNumber(u.rating_average)} (${formatNumber(u.ratings_count)})` : '—' }}</td>
        <td class="px-3 py-2">{{ formatNumber(u.completed_trips_as_owner) }} / {{ formatNumber(u.completed_trips_as_passenger) }}</td>
        <td class="px-3 py-2"><StatusBadge kind="user" :status="u.status" /></td>
        <td class="px-3 py-2">{{ formatShortDate(u.created_at) }}</td>
        <td class="px-3 py-2">
          <button
            v-if="u.role !== 'admin' && u.id !== auth.user?.id"
            type="button"
            class="btn-sm"
            :class="u.status === 'active' ? 'btn-danger' : 'btn-secondary'"
            @click="toggle(u)"
          >{{ u.status === 'active' ? $t('admin.block') : $t('admin.unblock') }}</button>
        </td>
      </tr>
    </AdminTable>
  </div>
</template>
