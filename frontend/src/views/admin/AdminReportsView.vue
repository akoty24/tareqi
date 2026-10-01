<script setup>
import { onMounted, reactive, ref } from 'vue'
import { adminApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { usePaginated } from '@/composables/usePaginated'
import { useForm } from '@/composables/useForm'
import { formatDateTime } from '@/utils/format'
import AdminTable from '@/components/AdminTable.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import ModalDialog from '@/components/ModalDialog.vue'

const auth = useAuthStore()
const filters = reactive({ status: 'pending', reason: '' })
const { items, meta, loading, load, page } = usePaginated((p) => adminApi.reports(p), filters)
const reasons = ['unsafe_driving', 'harassment', 'no_show', 'fraud', 'inappropriate_content', 'other']
const statuses = ['pending', 'under_review', 'resolved', 'dismissed']

const selected = ref(null)
const review = useForm({ status: 'under_review', admin_notes: '' })

async function openReport(report) {
  selected.value = (await adminApi.report(report.id)).data
  review.reset({ status: selected.value.status, admin_notes: selected.value.admin_notes || '' })
}

async function saveReview() {
  await review.submit((data) => adminApi.updateReport(selected.value.id, data))
  selected.value = null
  await load(page.value)
}

onMounted(() => load(1))
</script>

<template>
  <div>
    <h1 class="page-title">{{ $t('admin.reports') }}</h1>
    <div class="mb-4 flex flex-wrap gap-2">
      <select v-model="filters.status" class="input !w-auto" :aria-label="$t('common.status')">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ $t(`status.report.${s}`) }}</option>
      </select>
      <select v-model="filters.reason" class="input !w-auto" :aria-label="$t('report.reason')">
        <option value="">{{ $t('admin.allReasons') }}</option>
        <option v-for="r in reasons" :key="r" :value="r">{{ $t(`report.reasons.${r}`) }}</option>
      </select>
    </div>

    <AdminTable :columns="['admin.reporter', 'admin.reportedUser', 'report.reason', 'report.description', 'common.status', 'admin.created', 'common.actions']" :items="items" :meta="meta" :loading="loading" @page="load">
      <tr v-for="r in items" :key="r.id">
        <td class="px-3 py-2">{{ r.reporter?.name }}</td>
        <td class="px-3 py-2 font-semibold">{{ r.reported_user?.name }}</td>
        <td class="px-3 py-2">{{ $t(`report.reasons.${r.reason}`) }}</td>
        <td class="max-w-xs truncate px-3 py-2" :title="r.description">{{ r.description || '—' }}</td>
        <td class="px-3 py-2"><StatusBadge kind="report" :status="r.status" /></td>
        <td class="px-3 py-2">{{ formatDateTime(r.created_at) }}</td>
        <td class="px-3 py-2"><button type="button" class="btn-secondary btn-sm" @click="openReport(r)">{{ auth.can('reports.manage') ? $t('admin.review') : $t('admin.view') }}</button></td>
      </tr>
    </AdminTable>

    <ModalDialog :open="!!selected" :title="$t('admin.reviewReport')" @close="selected = null">
      <div v-if="selected" class="space-y-3 text-sm">
        <p><strong>{{ $t('admin.reporter') }}:</strong> {{ selected.reporter?.name }} (<span dir="ltr">{{ selected.reporter?.phone }}</span>)</p>
        <p>
          <strong>{{ $t('admin.reportedUser') }}:</strong> {{ selected.reported_user?.name }} (<span dir="ltr">{{ selected.reported_user?.phone }}</span>)
          <StatusBadge kind="user" :status="selected.reported_user?.status" />
        </p>
        <p><strong>{{ $t('report.reason') }}:</strong> {{ $t(`report.reasons.${selected.reason}`) }}</p>
        <p v-if="selected.description" class="rounded-xl bg-slate-50 p-3">{{ selected.description }}</p>
        <p v-if="selected.trip_id">
          <RouterLink :to="{ name: 'trip', params: { id: selected.trip_id } }" class="font-semibold text-brand-700 hover:underline">{{ $t('admin.viewTrip') }}</RouterLink>
        </p>
        <form v-if="auth.can('reports.manage')" id="review-form" class="space-y-3 border-t border-slate-100 pt-3" @submit.prevent="saveReview">
          <div>
            <label for="rv-status" class="label">{{ $t('common.status') }}</label>
            <select id="rv-status" v-model="review.fields.status" class="input">
              <option v-for="s in statuses" :key="s" :value="s">{{ $t(`status.report.${s}`) }}</option>
            </select>
          </div>
          <div>
            <label for="rv-notes" class="label">{{ $t('admin.notes') }}</label>
            <textarea id="rv-notes" v-model="review.fields.admin_notes" rows="3" class="input" />
          </div>
          <p class="text-xs text-slate-500">{{ $t('admin.blockHint') }}</p>
        </form>
        <p v-if="selected.reported_user && auth.can('users.view')">
          <RouterLink :to="{ name: 'admin-user', params: { id: selected.reported_user.id } }" class="font-semibold text-brand-700 hover:underline">{{ $t('admin.manageReportedUser') }}</RouterLink>
        </p>
      </div>
      <template #actions>
        <button type="button" class="btn-secondary" @click="selected = null">{{ $t('common.cancel') }}</button>
        <button v-if="auth.can('reports.manage')" type="submit" form="review-form" class="btn-primary" :disabled="review.submitting.value">{{ $t('common.save') }}</button>
      </template>
    </ModalDialog>
  </div>
</template>
