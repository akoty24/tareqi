<script setup>
import { useRoute, useRouter } from 'vue-router'
import { tripRequestsApi } from '@/api'
import { useForm } from '@/composables/useForm'
import { todayISO } from '@/utils/format'
import PlaceInput from '@/components/PlaceInput.vue'
import FormField from '@/components/FormField.vue'

const route = useRoute()
const router = useRouter()

const { fields, error, submitting, submit } = useForm({
  origin: String(route.query.origin || ''),
  destination: String(route.query.destination || ''),
  requested_date: String(route.query.date || todayISO(1)),
  preferred_time_from: '',
  preferred_time_to: '',
  passengers_count: 1,
  notes: '',
})

async function onSubmit() {
  const response = await submit((data) => tripRequestsApi.create({
    ...data,
    preferred_time_from: data.preferred_time_from || null,
    preferred_time_to: data.preferred_time_to || null,
    notes: data.notes || null,
  }))
  router.replace({ name: 'request', params: { id: response.data.trip_request.id } })
}
</script>

<template>
  <div class="mx-auto max-w-xl">
    <h1 class="page-title">{{ $t('request.create') }}</h1>
    <p class="mb-4 text-slate-600">{{ $t('request.createHint') }}</p>

    <form class="card space-y-4" novalidate @submit.prevent="onSubmit">
      <div class="grid gap-4 sm:grid-cols-2">
        <PlaceInput v-model="fields.origin" :label="$t('home.from')" :placeholder="$t('home.fromPlaceholder')" :error="error('origin')" required />
        <PlaceInput v-model="fields.destination" :label="$t('home.to')" :placeholder="$t('home.toPlaceholder')" :error="error('destination')" required />
      </div>
      <FormField v-slot="{ id, invalid }" :label="$t('home.date')" :error="error('requested_date')">
        <input :id="id" v-model="fields.requested_date" type="date" :min="todayISO()" class="input" :class="{ 'input-error': invalid }" required />
      </FormField>
      <div class="grid grid-cols-2 gap-4">
        <FormField v-slot="{ id, invalid }" :label="$t('search.timeFrom')" :error="error('preferred_time_from')">
          <input :id="id" v-model="fields.preferred_time_from" type="time" class="input" :class="{ 'input-error': invalid }" />
        </FormField>
        <FormField v-slot="{ id, invalid }" :label="$t('search.timeTo')" :error="error('preferred_time_to')">
          <input :id="id" v-model="fields.preferred_time_to" type="time" class="input" :class="{ 'input-error': invalid }" />
        </FormField>
      </div>
      <FormField v-slot="{ id, invalid }" :label="$t('search.passengers')" :error="error('passengers_count')">
        <input :id="id" v-model.number="fields.passengers_count" type="number" min="1" max="14" class="input" :class="{ 'input-error': invalid }" required />
      </FormField>
      <FormField v-slot="{ id }" :label="`${$t('trip.notes')} (${$t('common.optional')})`" :error="error('notes')">
        <textarea :id="id" v-model="fields.notes" rows="3" maxlength="1000" class="input" />
      </FormField>
      <button type="submit" class="btn-primary w-full" :disabled="submitting">{{ $t('request.submit') }}</button>
    </form>
  </div>
</template>
