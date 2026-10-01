<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { adminApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import { formatNumber, formatRating, formatShortDate } from '@/utils/format'
import AdminTable from '@/components/AdminTable.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const toast = useToastStore()
const auth = useAuthStore()
const route = useRoute()
const filters = reactive({ search: '', status: '', role: route.query.role ? String(route.query.role) : '' })
const search = ref('')
const roles = ref([])
const { items, meta, loading, load, page } = usePaginated((p) => adminApi.users(p), filters)

/** Same rules as the backend UserPolicy, to hide buttons that would be refused. */
const canModerate = (u) => u.id !== auth.user?.id && !u.role?.is_super && (!u.role || auth.isSuperAdmin)

async function toggle(user) {
  try {
    const response = user.status === 'active' ? await adminApi.blockUser(user.id) : await adminApi.unblockUser(user.id)
    toast.success(response.message)
    await load(page.value)
  } catch (e) {
    toast.error(e.message)
  }
}

onMounted(async () => {
  load(1)
  if (auth.can('roles.manage')) roles.value = (await adminApi.roles()).data
})
</script>

<template>
  <div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <h1 class="page-title !mb-0">{{ $t('admin.users') }}</h1>
      <RouterLink v-if="auth.can('roles.manage')" :to="{ name: 'admin-roles' }" class="btn-secondary btn-sm">
        {{ $t('admin.rolesNav') }}
      </RouterLink>
    </div>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="filters.search = search">
      <input v-model="search" type="search" class="input !w-64" :placeholder="$t('admin.searchUsers')" :aria-label="$t('common.search')" />
      <select v-model="filters.status" class="input !w-auto" :aria-label="$t('common.status')">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option value="active">{{ $t('status.user.active') }}</option>
        <option value="blocked">{{ $t('status.user.blocked') }}</option>
      </select>
      <select v-model="filters.role" class="input !w-auto" :aria-label="$t('admin.role')">
        <option value="">{{ $t('admin.allRoles') }}</option>
        <option value="member">{{ $t('admin.members') }}</option>
        <option value="staff">{{ $t('admin.staff') }}</option>
        <option v-for="r in roles" :key="r.id" :value="String(r.id)">{{ r.display_name }}</option>
      </select>
      <button type="submit" class="btn-primary btn-sm">{{ $t('common.search') }}</button>
    </form>

    <AdminTable :columns="['auth.name', 'auth.phone', 'admin.role', 'admin.rating', 'admin.completed', 'common.status', 'admin.joined', 'common.actions']" :items="items" :meta="meta" :loading="loading" @page="load">
      <tr v-for="u in items" :key="u.id">
        <td class="px-3 py-2">
          <RouterLink :to="{ name: 'admin-user', params: { id: u.id } }" class="font-semibold hover:underline">{{ u.name }}</RouterLink>
          <span class="block text-xs text-slate-500" dir="ltr">{{ u.email }}</span>
        </td>
        <td class="px-3 py-2" dir="ltr">{{ u.phone }}</td>
        <td class="px-3 py-2">
          <span v-if="u.role" class="rounded px-1.5 py-0.5 text-xs font-semibold" :class="u.role.is_super ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800'">{{ u.role.display_name }}</span>
          <span v-else class="text-xs text-slate-500">{{ $t('admin.member') }}</span>
        </td>
        <td class="px-3 py-2">{{ u.ratings_count ? `${formatRating(u.rating_average)} (${formatNumber(u.ratings_count)})` : '—' }}</td>
        <td class="px-3 py-2">{{ formatNumber(u.completed_trips_as_owner) }} / {{ formatNumber(u.completed_trips_as_passenger) }}</td>
        <td class="px-3 py-2"><StatusBadge kind="user" :status="u.status" /></td>
        <td class="px-3 py-2">{{ formatShortDate(u.created_at) }}</td>
        <td class="px-3 py-2">
          <div class="flex gap-1">
            <RouterLink :to="{ name: 'admin-user', params: { id: u.id } }" class="btn-secondary btn-sm">{{ $t('admin.manage') }}</RouterLink>
            <button
              v-if="auth.can('users.block') && canModerate(u)"
              type="button"
              class="btn-sm"
              :class="u.status === 'active' ? 'btn-danger' : 'btn-secondary'"
              @click="toggle(u)"
            >{{ u.status === 'active' ? $t('admin.block') : $t('admin.unblock') }}</button>
          </div>
        </td>
      </tr>
    </AdminTable>
  </div>
</template>
