import { vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

/**
 * Collects Vue warnings, runtime errors and vue-i18n "missing key" messages
 * so a test can assert the page rendered cleanly.
 */
export function captureProblems() {
  const problems = []
  const record = (kind) => (...args) => {
    const text = args.map(String).join(' ')
    problems.push(`${kind}: ${text}`)
  }
  vi.spyOn(console, 'warn').mockImplementation(record('warn'))
  vi.spyOn(console, 'error').mockImplementation(record('error'))
  return problems
}

/** Mounts the real App (router, guards, layouts, i18n, pinia) once per test file. */
export async function mountApp() {
  const { createPinia, setActivePinia } = await import('pinia')
  const { default: App } = await import('@/App.vue')
  const { default: router } = await import('@/router')
  const { i18n } = await import('@/i18n')

  const pinia = createPinia()
  setActivePinia(pinia)
  const errors = []

  const wrapper = mount(App, {
    attachTo: document.body,
    global: {
      plugins: [pinia, i18n, router],
      config: {
        errorHandler: (err) => errors.push(err),
        warnHandler: (msg) => errors.push(new Error(`[Vue warn] ${msg}`)),
      },
    },
  })

  return {
    wrapper,
    router,
    errors,
    async visit(path) {
      await router.push(path)
      await flushPromises()
      await flushPromises()
      return wrapper
    },
  }
}

/** Text of the whole document (includes teleported dialogs). */
export const bodyText = () => document.body.textContent.replace(/\s+/g, ' ')
