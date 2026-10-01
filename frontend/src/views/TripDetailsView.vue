<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { tripsApi, bookingsApi } from '@/api'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import { useRequireLogin } from '@/composables/useRequireLogin'
import { formatDate, formatTime, formatMoney, tripPriceLabel, formatNumber, formatRating, formatDateTime } from '@/utils/format'
import AppIcon from '@/components/AppIcon.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import LoadingState from '@/components/LoadingState.vue'
import PromptDialog from '@/components/PromptDialog.vue'
import RatingDialog from '@/components/RatingDialog.vue'
import ReportDialog from '@/components/ReportDialog.vue'
import ModalDialog from '@/components/ModalDialog.vue'

const props = defineProps({ id: { type: String, required: true } })
const { t } = useI18n()
const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const requireLogin = useRequireLogin()

const trip = ref(null)
const loading = ref(true)
const busy = ref(false)
const seats = ref(1)
const notes = ref('')

// Dialog state
const prompt = ref(null) // { title, message, confirmLabel, withReason, danger, action }
const ratingBooking = ref(null)
const reportOpen = ref(false)
const returnOpen = ref(false)
const returnForm = ref({ departure_date: '', departure_time: '' })

async function load() {
  loading.value = true
  try {
    trip.value = (await tripsApi.show(props.id)).data
    seats.value = 1
  } catch (e) {
    toast.error(e.message)
    router.replace({ name: 'home' })
  } finally {
    loading.value = false
  }
}

const myBooking = computed(() => trip.value?.my_booking)
const activeBooking = computed(() => ['pending', 'confirmed'].includes(myBooking.value?.status))
const canBook = computed(() => trip.value && !trip.value.is_mine && trip.value.status === 'published' && !activeBooking.value)
const canEdit = computed(() => trip.value?.is_mine && ['draft', 'published', 'full'].includes(trip.value.status))
// Mirror the backend rules so owners only see actions that will succeed:
// start from 2 hours before departure, complete once the trip has departed.
const departureMs = computed(() => (trip.value ? new Date(trip.value.departure_at).getTime() : 0))
const isOpen = computed(() => ['published', 'full'].includes(trip.value?.status))
const canStart = computed(() => isOpen.value && Date.now() >= departureMs.value - 2 * 3600_000)
const canComplete = computed(() => trip.value?.status === 'started' || (isOpen.value && Date.now() >= departureMs.value))
const totalPrice = computed(() => (trip.value ? trip.value.seat_price * seats.value : 0))
const pendingBookings = computed(() => trip.value?.bookings?.filter((b) => b.status === 'pending') ?? [])
const otherBookings = computed(() => trip.value?.bookings?.filter((b) => b.status !== 'pending') ?? [])

async function run(action, reload = true) {
  busy.value = true
  try {
    const response = await action()
    toast.success(response.message)
    prompt.value = null
    if (reload) await load()
    return response
  } catch (e) {
    toast.error(e.message)
  } finally {
    busy.value = false
  }
}

const book = () => requireLogin() && run(() => bookingsApi.create(trip.value.id, { seats: seats.value, notes: notes.value || null }))

function ask(kind, booking = null) {
  const configs = {
    cancelTrip: { title: t('trip.cancelTitle'), message: t('trip.cancelMessage'), confirmLabel: t('trip.cancel'), withReason: true, danger: true, action: (r) => tripsApi.cancel(trip.value.id, r) },
    deleteTrip: { title: t('trip.deleteTitle'), message: t('trip.deleteMessage'), confirmLabel: t('common.delete'), danger: true, action: () => tripsApi.remove(trip.value.id), after: () => router.replace({ name: 'my-trips' }) },
    publish: { title: t('trip.publish'), message: t('trip.publishMessage'), confirmLabel: t('trip.publish'), action: () => tripsApi.publish(trip.value.id) },
    start: { title: t('trip.start'), message: t('trip.startMessage'), confirmLabel: t('trip.start'), action: () => tripsApi.start(trip.value.id) },
    complete: { title: t('trip.complete'), message: t('trip.completeMessage'), confirmLabel: t('trip.complete'), action: () => tripsApi.complete(trip.value.id) },
    cancelBooking: { title: t('booking.cancelTitle'), message: t('booking.cancelMessage'), confirmLabel: t('booking.cancel'), withReason: true, danger: true, action: (r) => bookingsApi.cancel(booking.id, r) },
    rejectBooking: { title: t('booking.rejectTitle'), confirmLabel: t('booking.reject'), withReason: true, danger: true, action: (r) => bookingsApi.reject(booking.id, r) },
  }
  prompt.value = configs[kind]
}

async function confirmPrompt(reason) {
  const { action, after } = prompt.value
  const response = await run(() => action(reason), !after)
  if (response && after) after()
}

const confirmBooking = (booking) => run(() => bookingsApi.confirm(booking.id))

async function addReturn() {
  const response = await run(() => tripsApi.addReturn(trip.value.id, returnForm.value))
  if (response) returnOpen.value = false
}

watch(() => props.id, load)
onMounted(load)
</script>

<template>
  <LoadingState v-if="loading && !trip" />
  <div v-else-if="trip" class="grid gap-5 lg:grid-cols-3">
    <!-- Main info -->
    <div class="space-y-5 lg:col-span-2">
      <section class="card">
        <div class="mb-3 flex flex-wrap items-center gap-2">
          <StatusBadge kind="trip" :status="trip.status" />
          <span v-if="trip.is_return_trip" class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-xs font-bold text-sky-800">
            <AppIcon name="return" class="size-4" />{{ $t('trip.returnTrip') }}
          </span>
        </div>
        <h1 class="flex flex-wrap items-center gap-2 text-2xl font-extrabold sm:text-3xl">
          {{ trip.origin }} <AppIcon name="arrow" class="size-7 text-brand-600 ltr:rotate-180" /> {{ trip.destination }}
        </h1>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
          <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">{{ $t('home.date') }}</dt><dd class="font-bold">{{ formatDate(trip.departure_date) }}</dd></div>
          <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">{{ $t('trip.time') }}</dt><dd class="font-bold">{{ formatTime(trip.departure_time) }}</dd></div>
          <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">{{ $t('trip.availableSeats') }}</dt><dd class="font-bold">{{ formatNumber(trip.available_seats) }} / {{ formatNumber(trip.total_seats) }}</dd></div>
          <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">{{ $t('trip.costTypeLabel') }}</dt><dd class="font-bold">{{ tripPriceLabel(trip) }}</dd></div>
        </dl>

        <p v-if="trip.cost_type === 'cost_sharing'" class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ $t('trip.costSharingNote') }}</p>
        <p v-if="trip.notes" class="mt-3 whitespace-pre-line text-slate-700"><strong>{{ $t('trip.notes') }}:</strong> {{ trip.notes }}</p>
        <p v-if="trip.status === 'cancelled' && trip.cancellation_reason" class="mt-3 text-red-700">{{ $t('trip.cancelReason') }}: {{ trip.cancellation_reason }}</p>

        <div v-if="trip.vehicle" class="mt-4 flex items-center gap-2 text-slate-600">
          <AppIcon name="car" class="size-5" />
          {{ $t(`vehicle.types.${trip.vehicle.vehicle_type}`) }} · {{ trip.vehicle.model }} · {{ trip.vehicle.color }} · <span dir="auto">{{ trip.vehicle.plate_number }}</span>
        </div>
      </section>

      <!-- Linked outbound / return trip -->
      <RouterLink v-if="trip.return_trip" :to="{ name: 'trip', params: { id: trip.return_trip.id } }" class="card flex items-center gap-3 hover:ring-brand-200">
        <AppIcon name="return" class="size-7 text-sky-600" />
        <div class="flex-1">
          <p class="font-bold">{{ $t('trip.hasReturn') }}</p>
          <p class="text-sm text-slate-600">{{ formatDate(trip.return_trip.departure_date) }} · {{ formatTime(trip.return_trip.departure_time) }} · {{ $t('trip.bookSeparately') }}</p>
        </div>
      </RouterLink>
      <RouterLink v-if="trip.parent_trip" :to="{ name: 'trip', params: { id: trip.parent_trip.id } }" class="card flex items-center gap-3 hover:ring-brand-200">
        <AppIcon name="return" class="size-7 text-sky-600" />
        <div class="flex-1">
          <p class="font-bold">{{ $t('trip.outboundTrip') }}: {{ trip.parent_trip.origin }} ← {{ trip.parent_trip.destination }}</p>
          <p class="text-sm text-slate-600">{{ formatDate(trip.parent_trip.departure_date) }} · {{ formatTime(trip.parent_trip.departure_time) }}</p>
        </div>
      </RouterLink>

      <!-- Owner: bookings management -->
      <section v-if="trip.is_mine" class="card">
        <h2 class="mb-3 text-lg font-extrabold">{{ $t('trip.passengers') }}</h2>
        <p v-if="!trip.bookings?.length" class="text-slate-500">{{ $t('trip.noBookings') }}</p>

        <div v-if="pendingBookings.length" class="mb-4 space-y-2">
          <h3 class="font-bold text-amber-700">{{ $t('trip.pendingTitle') }}</h3>
          <div v-for="b in pendingBookings" :key="b.id" class="flex flex-wrap items-center gap-3 rounded-xl bg-amber-50 p-3">
            <UserAvatar :user="b.passenger" size="sm" />
            <div class="min-w-0 flex-1">
              <RouterLink :to="{ name: 'user', params: { id: b.passenger.id } }" class="font-bold hover:underline">{{ b.passenger.name }}</RouterLink>
              <p class="text-sm text-slate-600">{{ $t('booking.seatsCount', { n: formatNumber(b.seats) }, b.seats) }} · {{ formatMoney(b.total_price) }}</p>
              <p v-if="b.notes" class="text-sm text-slate-500">"{{ b.notes }}"</p>
            </div>
            <div class="flex gap-2">
              <button type="button" class="btn-primary btn-sm" :disabled="busy" @click="confirmBooking(b)"><AppIcon name="check" class="size-4" />{{ $t('booking.accept') }}</button>
              <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="ask('rejectBooking', b)">{{ $t('booking.reject') }}</button>
            </div>
          </div>
        </div>

        <ul class="divide-y divide-slate-100">
          <li v-for="b in otherBookings" :key="b.id" class="flex flex-wrap items-center gap-3 py-3">
            <UserAvatar :user="b.passenger" size="sm" />
            <div class="min-w-0 flex-1">
              <RouterLink :to="{ name: 'user', params: { id: b.passenger.id } }" class="font-bold hover:underline">{{ b.passenger.name }}</RouterLink>
              <p class="text-sm text-slate-600">
                {{ $t('booking.seatsCount', { n: formatNumber(b.seats) }, b.seats) }} · {{ formatMoney(b.total_price) }}
                <a v-if="b.passenger_phone" :href="`tel:${b.passenger_phone}`" class="ms-2 font-semibold text-brand-700" dir="ltr">{{ b.passenger_phone }}</a>
              </p>
            </div>
            <StatusBadge kind="booking" :status="b.status" />
            <button v-if="b.status === 'confirmed' && ['published', 'full'].includes(trip.status)" type="button" class="btn-ghost btn-sm text-red-600" @click="ask('cancelBooking', b)">{{ $t('booking.cancel') }}</button>
            <button v-if="b.can_rate" type="button" class="btn-secondary btn-sm" @click="ratingBooking = b"><AppIcon name="star" class="size-4" />{{ $t('rating.rate') }}</button>
          </li>
        </ul>
      </section>
    </div>

    <!-- Sidebar: owner card + actions -->
    <aside class="space-y-5">
      <section v-if="!trip.is_mine" class="card">
        <h2 class="mb-3 text-sm font-bold text-slate-500">{{ $t('trip.driver') }}</h2>
        <RouterLink :to="{ name: 'user', params: { id: trip.owner.id } }" class="flex items-center gap-3">
          <UserAvatar :user="trip.owner" />
          <div>
            <p class="font-extrabold">{{ trip.owner.name }}</p>
            <p class="flex items-center gap-1 text-sm text-slate-600">
              <AppIcon name="star" class="size-4 fill-current text-amber-500" />
              <template v-if="trip.owner.ratings_count">{{ formatRating(trip.owner.rating_average) }} ({{ formatNumber(trip.owner.ratings_count) }})</template>
              <template v-else>{{ $t('rating.new') }}</template>
            </p>
            <p v-if="trip.owner.completed_trips_as_owner !== undefined" class="text-xs text-slate-500">{{ $t('profile.completedTrips', { n: formatNumber(trip.owner.completed_trips_as_owner) }) }}</p>
          </div>
        </RouterLink>
        <a v-if="trip.owner_phone && !trip.is_mine" :href="`tel:${trip.owner_phone}`" class="btn-secondary mt-3 w-full">
          <AppIcon name="phone" class="size-5" /> <span dir="ltr">{{ trip.owner_phone }}</span>
        </a>
      </section>

      <!-- Passenger: my booking -->
      <section v-if="myBooking" class="card">
        <h2 class="mb-2 font-extrabold">{{ $t('booking.mine') }}</h2>
        <div class="flex items-center justify-between">
          <span>{{ $t('booking.seatsCount', { n: formatNumber(myBooking.seats) }, myBooking.seats) }} · {{ formatMoney(myBooking.total_price) }}</span>
          <StatusBadge kind="booking" :status="myBooking.status" />
        </div>
        <p v-if="myBooking.status === 'pending'" class="mt-2 text-sm text-amber-700">{{ $t('booking.pendingHint') }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <button v-if="activeBooking && ['published', 'full'].includes(trip.status)" type="button" class="btn-secondary btn-sm text-red-600" @click="ask('cancelBooking', myBooking)">{{ $t('booking.cancel') }}</button>
          <button v-if="myBooking.can_rate" type="button" class="btn-primary btn-sm" @click="ratingBooking = myBooking"><AppIcon name="star" class="size-4" />{{ $t('rating.rateDriver') }}</button>
        </div>
      </section>

      <!-- Passenger: booking form -->
      <section v-if="canBook" class="card">
        <h2 class="mb-3 font-extrabold">{{ $t('booking.bookNow') }}</h2>
        <form class="space-y-3" @submit.prevent="book">
          <div>
            <label for="book-seats" class="label">{{ $t('booking.seats') }}</label>
            <select id="book-seats" v-model.number="seats" class="input">
              <option v-for="n in trip.available_seats" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
          <div>
            <label for="book-notes" class="label">{{ $t('trip.notes') }} <span class="font-normal text-slate-400">({{ $t('common.optional') }})</span></label>
            <input id="book-notes" v-model="notes" type="text" maxlength="500" class="input" :placeholder="$t('booking.notesPlaceholder')" />
          </div>
          <p class="flex justify-between text-lg"><span>{{ $t('booking.total') }}</span><strong>{{ formatMoney(totalPrice) }}</strong></p>
          <p class="text-xs text-slate-500">{{ trip.auto_confirm_bookings ? $t('booking.instantHint') : $t('booking.approvalHint') }}</p>
          <button type="submit" class="btn-primary w-full" :disabled="busy">{{ auth.isAuthenticated ? $t('booking.confirmBooking') : $t('auth.loginToBook') }}</button>
        </form>
      </section>
      <p v-else-if="!trip.is_mine && !myBooking && trip.status !== 'published'" class="card text-center text-slate-600">{{ $t('booking.notAvailable') }}</p>

      <!-- Owner actions -->
      <section v-if="trip.is_mine && !['completed', 'cancelled'].includes(trip.status)" class="card space-y-2">
        <h2 class="mb-1 font-extrabold">{{ $t('trip.manage') }}</h2>
        <button v-if="trip.status === 'draft'" type="button" class="btn-primary w-full" @click="ask('publish')">{{ $t('trip.publish') }}</button>
        <button v-if="canStart" type="button" class="btn-primary w-full" @click="ask('start')">{{ $t('trip.start') }}</button>
        <button v-if="canComplete" type="button" class="btn-secondary w-full" @click="ask('complete')">{{ $t('trip.complete') }}</button>
        <RouterLink v-if="canEdit" :to="{ name: 'trip-edit', params: { id: trip.id } }" class="btn-secondary w-full"><AppIcon name="edit" class="size-4" />{{ $t('common.edit') }}</RouterLink>
        <button v-if="canEdit && !trip.is_return_trip && !trip.return_trip" type="button" class="btn-secondary w-full" @click="returnOpen = true">
          <AppIcon name="return" class="size-4" />{{ $t('trip.addReturn') }}
        </button>
        <button v-if="canEdit" type="button" class="btn-ghost w-full text-red-600" @click="ask('cancelTrip')">{{ $t('trip.cancel') }}</button>
        <button v-if="canEdit && !trip.bookings?.length" type="button" class="btn-ghost w-full text-red-600" @click="ask('deleteTrip')"><AppIcon name="trash" class="size-4" />{{ $t('common.delete') }}</button>
      </section>

      <button v-if="!trip.is_mine" type="button" class="w-full text-center text-sm text-slate-500 hover:text-red-600" @click="requireLogin() && (reportOpen = true)">
        <AppIcon name="flag" class="inline size-4" /> {{ $t('report.reportTrip') }}
      </button>
      <p class="text-center text-xs text-slate-400">{{ $t('trip.createdAt', { date: formatDateTime(trip.created_at) }) }}</p>
    </aside>

    <PromptDialog
      :open="!!prompt"
      :title="prompt?.title || ''"
      :message="prompt?.message || ''"
      :confirm-label="prompt?.confirmLabel || ''"
      :with-reason="!!prompt?.withReason"
      :danger="!!prompt?.danger"
      :busy="busy"
      @close="prompt = null"
      @confirm="confirmPrompt"
    />
    <RatingDialog
      :open="!!ratingBooking"
      :booking="ratingBooking"
      :person-name="ratingBooking?.passenger && trip.is_mine ? ratingBooking.passenger.name : trip.owner.name"
      @close="ratingBooking = null"
      @rated="load"
    />
    <ReportDialog :open="reportOpen" :target="{ trip_id: trip.id, booking_id: myBooking?.id }" @close="reportOpen = false" />

    <ModalDialog :open="returnOpen" :title="$t('trip.addReturn')" @close="returnOpen = false">
      <form id="return-form" class="grid gap-3 sm:grid-cols-2" @submit.prevent="addReturn">
        <p class="text-sm text-slate-600 sm:col-span-2">{{ $t('trip.returnHint', { from: trip.destination, to: trip.origin }) }}</p>
        <div>
          <label for="ret-date" class="label">{{ $t('home.date') }}</label>
          <input id="ret-date" v-model="returnForm.departure_date" type="date" :min="trip.departure_date" class="input" required />
        </div>
        <div>
          <label for="ret-time" class="label">{{ $t('trip.time') }}</label>
          <input id="ret-time" v-model="returnForm.departure_time" type="time" class="input" required />
        </div>
      </form>
      <template #actions>
        <button type="button" class="btn-secondary" @click="returnOpen = false">{{ $t('common.cancel') }}</button>
        <button type="submit" form="return-form" class="btn-primary" :disabled="busy">{{ $t('common.save') }}</button>
      </template>
    </ModalDialog>
  </div>
</template>
