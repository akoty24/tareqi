<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { tripsApi, vehiclesApi } from '@/api'
import { useForm } from '@/composables/useForm'
import { todayISO } from '@/utils/format'
import PlaceInput from '@/components/PlaceInput.vue'
import FormField from '@/components/FormField.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'
import AppIcon from '@/components/AppIcon.vue'

/** Create (no id) or edit (id) a trip. */
const props = defineProps({ id: { type: String, default: null } })
const router = useRouter()
const isEdit = computed(() => !!props.id)

const vehicles = ref([])
const loading = ref(true)
const withReturn = ref(false)
const hasBookings = ref(false)

const { fields, error, submitting, submit } = useForm({
  vehicle_id: '',
  origin: '',
  destination: '',
  departure_date: todayISO(1),
  departure_time: '07:00',
  total_seats: 3,
  cost_type: 'cost_sharing',
  price_per_seat: '',
  estimated_cost_per_passenger: '',
  notes: '',
  auto_confirm_bookings: false,
  publish: true,
  return_date: todayISO(1),
  return_time: '17:00',
})

const costTypes = ['free', 'cost_sharing', 'fixed_price']

onMounted(async () => {
  try {
    vehicles.value = (await vehiclesApi.list()).data
    if (vehicles.value.length && !fields.vehicle_id) fields.vehicle_id = vehicles.value[0].id

    if (isEdit.value) {
      const trip = (await tripsApi.show(props.id)).data
      hasBookings.value = trip.booked_seats > 0
      Object.assign(fields, {
        vehicle_id: trip.vehicle?.id ?? '',
        origin: trip.origin,
        destination: trip.destination,
        departure_date: trip.departure_date,
        departure_time: trip.departure_time,
        total_seats: trip.total_seats,
        cost_type: trip.cost_type,
        price_per_seat: trip.price_per_seat ?? '',
        estimated_cost_per_passenger: trip.estimated_cost_per_passenger ?? '',
        notes: trip.notes ?? '',
        auto_confirm_bookings: trip.auto_confirm_bookings,
      })
    }
  } finally {
    loading.value = false
  }
})

function payload(data) {
  const body = {
    vehicle_id: data.vehicle_id,
    total_seats: Number(data.total_seats),
    cost_type: data.cost_type,
    price_per_seat: data.cost_type === 'fixed_price' ? data.price_per_seat || null : null,
    estimated_cost_per_passenger: data.cost_type === 'cost_sharing' ? data.estimated_cost_per_passenger || null : null,
    notes: data.notes || null,
    auto_confirm_bookings: data.auto_confirm_bookings,
  }
  // Route/time are locked once a trip has bookings (backend enforces it too).
  if (!hasBookings.value) {
    Object.assign(body, {
      origin: data.origin,
      destination: data.destination,
      departure_date: data.departure_date,
      departure_time: data.departure_time,
    })
  }
  if (!isEdit.value) {
    body.publish = data.publish
    if (withReturn.value) body.return_trip = { departure_date: data.return_date, departure_time: data.return_time }
  }
  return body
}

async function onSubmit() {
  const response = await submit((data) =>
    isEdit.value ? tripsApi.update(props.id, payload(data)) : tripsApi.create(payload(data)),
  )
  router.replace({ name: 'trip', params: { id: response.data.id } })
}
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="page-title">{{ isEdit ? $t('trip.edit') : $t('trip.create') }}</h1>

    <LoadingState v-if="loading" />

    <EmptyState v-else-if="!vehicles.length" icon="car" :title="$t('trip.needVehicle')" :text="$t('trip.needVehicleHint')">
      <RouterLink :to="{ name: 'vehicles' }" class="btn-primary">{{ $t('vehicle.add') }}</RouterLink>
    </EmptyState>

    <form v-else class="space-y-5" novalidate @submit.prevent="onSubmit">
      <section class="card space-y-4">
        <h2 class="font-extrabold">{{ $t('trip.routeSection') }}</h2>
        <p v-if="hasBookings" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ $t('trip.routeLocked') }}</p>
        <div class="grid gap-4 sm:grid-cols-2">
          <PlaceInput v-model="fields.origin" :label="$t('home.from')" :placeholder="$t('home.fromPlaceholder')" :error="error('origin')" required :class="{ 'pointer-events-none opacity-60': hasBookings }" />
          <PlaceInput v-model="fields.destination" :label="$t('home.to')" :placeholder="$t('home.toPlaceholder')" :error="error('destination')" required :class="{ 'pointer-events-none opacity-60': hasBookings }" />
          <FormField v-slot="{ id, invalid }" :label="$t('home.date')" :error="error('departure_date')">
            <input :id="id" v-model="fields.departure_date" type="date" :min="todayISO()" class="input" :class="{ 'input-error': invalid }" :disabled="hasBookings" required />
          </FormField>
          <FormField v-slot="{ id, invalid }" :label="$t('trip.time')" :error="error('departure_time')">
            <input :id="id" v-model="fields.departure_time" type="time" class="input" :class="{ 'input-error': invalid }" :disabled="hasBookings" required />
          </FormField>
        </div>
      </section>

      <section class="card space-y-4">
        <h2 class="font-extrabold">{{ $t('trip.carSection') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <FormField v-slot="{ id, invalid }" :label="$t('trip.vehicle')" :error="error('vehicle_id')">
            <select :id="id" v-model="fields.vehicle_id" class="input" :class="{ 'input-error': invalid }">
              <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.model }} · {{ v.color }}</option>
            </select>
          </FormField>
          <FormField v-slot="{ id, invalid }" :label="$t('trip.totalSeats')" :error="error('total_seats')">
            <input :id="id" v-model.number="fields.total_seats" type="number" min="1" max="14" class="input" :class="{ 'input-error': invalid }" required />
          </FormField>
        </div>
      </section>

      <section class="card space-y-4">
        <h2 class="font-extrabold">{{ $t('trip.costTypeLabel') }}</h2>
        <div class="grid gap-2 sm:grid-cols-3" role="radiogroup" :aria-label="$t('trip.costTypeLabel')">
          <label
            v-for="c in costTypes"
            :key="c"
            class="flex cursor-pointer flex-col rounded-xl border-2 p-3"
            :class="fields.cost_type === c ? 'border-brand-600 bg-brand-50' : 'border-slate-200'"
          >
            <span class="flex items-center gap-2 font-bold">
              <input v-model="fields.cost_type" type="radio" :value="c" class="accent-brand-700" /> {{ $t(`trip.costType.${c}`) }}
            </span>
            <span class="mt-1 text-xs text-slate-600">{{ $t(`trip.costTypeHint.${c}`) }}</span>
          </label>
        </div>
        <FormField v-if="fields.cost_type === 'fixed_price'" v-slot="{ id, invalid }" :label="$t('trip.pricePerSeat')" :error="error('price_per_seat')">
          <input :id="id" v-model="fields.price_per_seat" type="number" min="1" step="0.5" inputmode="decimal" class="input" :class="{ 'input-error': invalid }" />
        </FormField>
        <FormField v-if="fields.cost_type === 'cost_sharing'" v-slot="{ id, invalid }" :label="$t('trip.estimatedCost')" :error="error('estimated_cost_per_passenger')">
          <input :id="id" v-model="fields.estimated_cost_per_passenger" type="number" min="1" step="0.5" inputmode="decimal" class="input" :class="{ 'input-error': invalid }" />
        </FormField>
      </section>

      <section class="card space-y-4">
        <FormField v-slot="{ id }" :label="`${$t('trip.notes')} (${$t('common.optional')})`" :error="error('notes')">
          <textarea :id="id" v-model="fields.notes" rows="3" maxlength="1000" class="input" :placeholder="$t('trip.notesPlaceholder')" />
        </FormField>
        <label class="flex items-start gap-3">
          <input v-model="fields.auto_confirm_bookings" type="checkbox" class="mt-1 size-5 accent-brand-700" />
          <span><strong>{{ $t('trip.autoConfirm') }}</strong><br /><span class="text-sm text-slate-600">{{ $t('trip.autoConfirmHint') }}</span></span>
        </label>
      </section>

      <section v-if="!isEdit" class="card space-y-4">
        <label class="flex items-start gap-3">
          <input v-model="withReturn" type="checkbox" class="mt-1 size-5 accent-brand-700" />
          <span><strong class="inline-flex items-center gap-1"><AppIcon name="return" class="size-4" />{{ $t('trip.addReturn') }}</strong><br />
            <span class="text-sm text-slate-600">{{ $t('trip.returnHint', { from: fields.destination || '…', to: fields.origin || '…' }) }}</span></span>
        </label>
        <div v-if="withReturn" class="grid gap-4 sm:grid-cols-2">
          <FormField v-slot="{ id, invalid }" :label="$t('trip.returnDate')" :error="error('return_trip.departure_date')">
            <input :id="id" v-model="fields.return_date" type="date" :min="fields.departure_date" class="input" :class="{ 'input-error': invalid }" />
          </FormField>
          <FormField v-slot="{ id, invalid }" :label="$t('trip.returnTime')" :error="error('return_trip.departure_time')">
            <input :id="id" v-model="fields.return_time" type="time" class="input" :class="{ 'input-error': invalid }" />
          </FormField>
        </div>
        <label class="flex items-center gap-3 border-t border-slate-100 pt-4">
          <input v-model="fields.publish" type="checkbox" class="size-5 accent-brand-700" />
          <span>{{ $t('trip.publishNow') }}</span>
        </label>
      </section>

      <div class="flex gap-3">
        <button type="submit" class="btn-primary flex-1" :disabled="submitting">{{ isEdit ? $t('common.save') : fields.publish ? $t('trip.createBtn') : $t('trip.saveDraft') }}</button>
        <button type="button" class="btn-secondary" @click="router.back()">{{ $t('common.cancel') }}</button>
      </div>
    </form>
  </div>
</template>
