<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { apiUrlStorage } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import ModalDialog from './ModalDialog.vue'
import AppIcon from './AppIcon.vue'

/** Android app only: choose the API server (dev PC on the LAN, staging, production). */
const toast = useToastStore()
const open = ref(false)
const url = ref(apiUrlStorage.get())
const checking = ref(false)
const status = ref(null) // 'ok' | 'fail'

function show() {
  url.value = apiUrlStorage.get()
  status.value = null
  open.value = true
}

async function test() {
  checking.value = true
  status.value = null
  try {
    // Any HTTP answer (a 401 without a token is expected) means the API is reachable.
    await axios.get(url.value.trim().replace(/\/+$/, '') + '/auth/me', { timeout: 6000, headers: { Accept: 'application/json' } })
    status.value = 'ok'
  } catch (e) {
    status.value = e.response ? 'ok' : 'fail'
  } finally {
    checking.value = false
  }
}

function save() {
  apiUrlStorage.set(url.value)
  open.value = false
  toast.success(apiUrlStorage.get())
}

function reset() {
  url.value = apiUrlStorage.default
  status.value = null
}
</script>

<template>
  <button type="button" class="mx-auto mt-6 flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700" @click="show">
    <AppIcon name="devices" class="size-4" /> {{ $t('server.title') }}: <span dir="ltr">{{ apiUrlStorage.get() }}</span>
  </button>

  <ModalDialog :open="open" :title="$t('server.title')" @close="open = false">
    <p class="mb-3 text-sm text-slate-600">{{ $t('server.hint') }}</p>
    <label for="server-url" class="label">{{ $t('server.url') }}</label>
    <input id="server-url" v-model="url" type="url" dir="ltr" class="input text-start" placeholder="http://192.168.1.10:8000/api" />
    <p v-if="status === 'ok'" class="mt-2 text-sm font-semibold text-green-700">{{ $t('server.ok') }}</p>
    <p v-else-if="status === 'fail'" class="mt-2 text-sm font-semibold text-red-600">{{ $t('server.fail') }}</p>
    <template #actions>
      <button type="button" class="btn-ghost" @click="reset">{{ $t('server.reset') }}</button>
      <button type="button" class="btn-secondary" :disabled="checking" @click="test">{{ checking ? $t('common.loading') : $t('server.test') }}</button>
      <button type="button" class="btn-primary" @click="save">{{ $t('common.save') }}</button>
    </template>
  </ModalDialog>
</template>
