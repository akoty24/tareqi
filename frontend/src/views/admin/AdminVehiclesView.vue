<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatNumber, formatShortDate } from '@/utils/format'
import AdminTable from '@/components/AdminTable.vue'
import PromptDialog from '@/components/PromptDialog.vue'

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()
const filters = reactive({ search: '', vehicle_type: '', deleted: '' })
const search = ref('')
const deleting = ref(null)
const { items, meta, loading, load, page } = usePaginated((p) => adminApi.vehicles(p), filters)
const types = ['sedan', 'hatchback', 'suv', 'minivan', 'microbus', 'pickup']

async function remove() {
  try {
    toast.success((await adminApi.deleteVehicle(deleting.value.id)).message)
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
    <h1 class="page-title">{{ $t('admin.vehicles') }}</h1>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="filters.search = search">
      <input v-model="search" type="search" class="input !w-64" :placeholder="$t('admin.searchVehicles')" :aria-label="$t('common.search')" />
      <select v-model="filters.vehicle_type" class="input !w-auto" :aria-label="$t('vehicle.type')">
        <option value="">{{ $t('common.all') }}</option>
        <option v-for="type in types" :key="type" :value="type">{{ $t(`vehicle.types.${type}`) }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="filters.deleted" type="checkbox" true-value="1" false-value="" class="size-4 accent-brand-700" />
        {{ $t('admin.deletedOnly') }}
      </label>
      <button type="submit" class="btn-primary btn-sm">{{ $t('common.search') }}</button>
    </form>

    <AdminTable :columns="['vehicle.model', 'vehicle.type', 'vehicle.color', 'vehicle.plate', 'admin.owner', 'admin.tripsCol', 'admin.created', 'common.actions']" :items="items" :meta="meta" :loading="loading" @page="load">
      <tr v-for="v in items" :key="v.id" :class="{ 'opacity-60': v.deleted_at }">
        <td class="px-3 py-2 font-semibold">{{ v.model }}</td>
        <td class="px-3 py-2">{{ $t(`vehicle.types.${v.vehicle_type}`) }}</td>
        <td class="px-3 py-2">{{ v.color }}</td>
        <td class="px-3 py-2" dir="ltr">{{ v.plate_number }}</td>
        <td class="px-3 py-2">
          <RouterLink v-if="v.owner && auth.can('users.view')" :to="{ name: 'admin-user', params: { id: v.owner.id } }" class="hover:underline">{{ v.owner.name }}</RouterLink>
          <span v-else>{{ v.owner?.name }}</span>
        </td>
        <td class="px-3 py-2">{{ formatNumber(v.trips_count) }}</td>
        <td class="px-3 py-2">{{ formatShortDate(v.created_at) }}</td>
        <td class="px-3 py-2">
          <span v-if="v.deleted_at" class="text-xs text-slate-500">{{ $t('admin.deleted') }}</span>
          <button v-else-if="auth.can('vehicles.manage')" type="button" class="btn-danger btn-sm" @click="deleting = v">{{ $t('common.delete') }}</button>
        </td>
      </tr>
    </AdminTable>

    <PromptDialog
      :open="!!deleting"
      :title="t('vehicle.deleteTitle')"
      :message="t('admin.deleteVehicleMessage')"
      :confirm-label="t('common.delete')"
      danger
      @close="deleting = null"
      @confirm="remove"
    />
  </div>
</template>
