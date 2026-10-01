import { createI18n } from 'vue-i18n'
import ar from './locales/ar'
import en from './locales/en'

/**
 * Arabic is the primary language. To add/enable another language, add a
 * locale file and an entry in SUPPORTED; `setLocale` updates <html lang/dir>
 * and the Accept-Language header sent to the API.
 */
export const SUPPORTED = {
  ar: { dir: 'rtl', intl: 'ar-EG' },
  en: { dir: 'ltr', intl: 'en-GB' },
}

const saved = (() => {
  try {
    return localStorage.getItem('mishwar_locale')
  } catch {
    return null
  }
})()

export const i18n = createI18n({
  legacy: false,
  locale: SUPPORTED[saved] ? saved : 'ar',
  fallbackLocale: 'ar',
  messages: { ar, en },
})

export function setLocale(locale) {
  if (!SUPPORTED[locale]) return
  i18n.global.locale.value = locale
  document.documentElement.lang = locale
  document.documentElement.dir = SUPPORTED[locale].dir
  try {
    localStorage.setItem('mishwar_locale', locale)
  } catch {
    /* storage unavailable */
  }
}

export const intlLocale = () => SUPPORTED[i18n.global.locale.value]?.intl ?? 'ar-EG'

setLocale(i18n.global.locale.value)
