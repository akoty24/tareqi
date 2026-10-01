<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { vehiclesApi } from '@/api'
import { useForm } from '@/composables/useForm'
import { useToastStore } from '@/stores/toast'
import { formatNumber } from '@/utils/format'
import ModalDialog from '@/components/ModalDialog.vue'
import PromptDialog from '@/components/PromptDialog.vue'
import FormField from '@/components/FormField.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import AppIcon from '@/components/AppIcon.vue'

const { t } = useI18n()
const toast = useToastStore()
const vehicles = ref([])
const loading = ref(true)
const editing = ref(null) // null = closed, {} = new, vehicle = edit
const deleting = ref(null)
const types = ['sedan', 'hatchback', 'suv', 'minivan', 'microbus', 'pickup']
const empty = { vehicle_type: 'sedan', model: '', color: '', plate_number: '' }

const { fields, error, submitting, submit, reset } = useForm(empty)

async function load() {
  loading.value = true
  try {
    vehicles.value = (await vehiclesApi.list()).data
  } finally {
    loading.value = false
  }
}

function openForm(vehicle = null) {
  editing.value = vehicle || {}
  reset(vehicle ? { vehicle_type: vehicle.vehicle_type, model: vehicle.model, color: vehicle.color, plate_number: vehicle.plate_number } : empty)
}

async function save() {
  await submit((data) => (editing.value.id ? vehiclesApi.update(editing.value.id, data) : vehiclesApi.create(data)))
  editing.value = null
  await load()
}

async function remove() {
  try {
    toast.success((await vehiclesApi.remove(deleting.value.id)).message)
    await load()
  } catch (e) {
    toast.error(e.message)
  } finally {
    deleting.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <div class="mb-4 flex items-center justify-between">
      <h1 class="page-title !mb-0">{{ $t('nav.vehicles') }}</h1>
      <button type="button" class="btn-primary btn-sm" @click="openForm()"><AppIcon name="plus" class="size-4" />{{ $t('vehicle.add') }}</button>
    </div>

    <LoadingState v-if="loading" />
    <ul v-else-if="vehicles.length" class="space-y-3">
      <li v-for="v in vehicles" :key="v.id" class="card flex flex-wrap items-center gap-3">
        <AppIcon name="car" class="size-10 text-brand-700" />
        <div class="min-w-0 flex-1">
          <p class="font-extrabold">{{ v.model }}</p>
          <p class="text-sm text-slate-600">{{ $t(`vehicle.types.${v.vehicle_type}`) }} · {{ v.color }} · <span dir="auto">{{ v.plate_number }}</span></p>
          <p class="text-xs text-slate-400">{{ $t('vehicle.tripsCount', { n: formatNumber(v.trips_count) }) }}</p>
        </div>
        <button type="button" class="btn-secondary btn-sm" @click="openForm(v)"><AppIcon name="edit" class="size-4" />{{ $t('common.edit') }}</button>
        <button type="button" class="btn-ghost btn-sm text-red-600" :aria-label="$t('common.delete')" @click="deleting = v"><AppIcon name="trash" class="size-4" /></button>
      </li>
    </ul>
    <EmptyState v-else icon="car" :title="$t('vehicle.empty')" :text="$t('vehicle.emptyHint')">
      <button type="button" class="btn-primary" @click="openForm()">{{ $t('vehicle.add') }}</button>
    </EmptyState>

    <ModalDialog :open="!!editing" :title="editing?.id ? $t('vehicle.edit') : $t('vehicle.add')" @close="editing = null">
      <form id="vehicle-form" class="space-y-4" novalidate @submit.prevent="save">
        <FormField v-slot="{ id, invalid }" :label="$t('vehicle.type')" :error="error('vehicle_type')">
          <select :id="id" v-model="fields.vehicle_type" class="input" :class="{ 'input-error': invalid }">
            <option v-for="type in types" :key="type" :value="type">{{ $t(`vehicle.types.${type}`) }}</option>
          </select>
        </FormField>
        <FormField v-slot="{ id, invalid }" :label="$t('vehicle.model')" :error="error('model')">
          <input :id="id" v-model.trim="fields.model" type="text" maxlength="80" class="input" :class="{ 'input-error': invalid }" :placeholder="$t('vehicle.modelPlaceholder')" />
        </FormField>
        <div class="grid grid-cols-2 gap-4">
          <FormField v-slot="{ id, invalid }" :label="$t('vehicle.color')" :error="error('color')">
            <input :id="id" v-model.trim="fields.color" type="text" maxlength="40" class="input" :class="{ 'input-error': invalid }" />
          </FormField>
          <FormField v-slot="{ id, invalid }" :label="$t('vehicle.plate')" :error="error('plate_number')">
            <input :id="id" v-model.trim="fields.plate_number" type="text" maxlength="20" class="input" :class="{ 'input-error': invalid }" />
          </FormField>
        </div>
      </form>
      <template #actions>
        <button type="button" class="btn-secondary" @click="editing = null">{{ $t('common.cancel') }}</button>
        <button type="submit" form="vehicle-form" class="btn-primary" :disabled="submitting">{{ $t('common.save') }}</button>
      </template>
    </ModalDialog>

    <PromptDialog
      :open="!!deleting"
      :title="t('vehicle.deleteTitle')"
      :message="deleting ? `${deleting.model} · ${deleting.plate_number}` : ''"
      :confirm-label="t('common.delete')"
      danger
      @close="deleting = null"
      @confirm="remove"
    />
  </div>
</template>
