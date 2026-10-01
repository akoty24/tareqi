/**
 * Takes screenshots of every page (mobile + desktop) against the running dev
 * servers (php artisan serve + npm run dev) and the seeded demo data.
 *
 *   node scripts/screenshots.mjs [outDir] [filter]
 */
import { chromium } from 'playwright'
import fs from 'node:fs'
import path from 'node:path'

const BASE = process.env.APP_URL || 'http://127.0.0.1:5173'
const OUT = process.argv[2] || 'screenshots'
const FILTER = process.argv[3] || ''
fs.mkdirSync(OUT, { recursive: true })

async function token(login) {
  const res = await fetch(`${BASE}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ login, password: 'password' }),
  })
  return (await res.json()).data.token
}

const driver = await token('driver@mishwar.test')
const passenger = await token('passenger@mishwar.test')
const admin = await token('admin@mishwar.test')

// Pick real ids from the seeded data.
const api = async (t, p) => (await (await fetch(`${BASE}/api${p}`, { headers: { Authorization: `Bearer ${t}`, Accept: 'application/json' } })).json()).data
const driverTrips = await api(driver, '/trips?mine=1&scope=upcoming')
const ownTrip = driverTrips.find((t) => t.pending_bookings_count) || driverTrips[0]
const otherTrip = (await api(passenger, '/trips'))[0]
const request = (await api(passenger, '/trip-requests'))[0]

const pages = [
  ['guest', null, '/login'],
  ['guest', null, '/register'],
  ['passenger', passenger, '/'],
  ['passenger', passenger, '/search?origin=ميت خاقان'],
  ['passenger', passenger, `/trips/${otherTrip.id}`],
  ['passenger', passenger, '/my-bookings'],
  ['passenger', passenger, '/requests'],
  ['passenger', passenger, `/requests/${request.id}`],
  ['passenger', passenger, '/requests/new'],
  ['passenger', passenger, '/notifications'],
  ['passenger', passenger, '/profile'],
  ['passenger', passenger, '/ratings'],
  ['driver', driver, `/trips/${ownTrip.id}`],
  ['driver', driver, '/trips/new'],
  ['driver', driver, '/my-trips'],
  ['driver', driver, '/vehicles'],
  ['admin', admin, '/admin'],
  ['admin', admin, '/admin/users'],
  ['admin', admin, '/admin/reports'],
]

const viewports = { mobile: { width: 390, height: 844 }, desktop: { width: 1280, height: 860 } }
const browser = await chromium.launch()
const problems = []

for (const [who, t, url] of pages) {
  if (FILTER && !url.includes(FILTER) && !who.includes(FILTER)) continue
  for (const [vp, size] of Object.entries(viewports)) {
    const context = await browser.newContext({ viewport: size, deviceScaleFactor: 1, locale: 'ar-EG' })
    if (t) await context.addInitScript((tk) => localStorage.setItem('mishwar_token', tk), t)
    const pageObj = await context.newPage()
    pageObj.on('pageerror', (e) => problems.push(`${url} ${vp}: ${e.message}`))
    pageObj.on('console', (m) => m.type() === 'error' && problems.push(`${url} ${vp}: ${m.text()}`))
    await pageObj.goto(BASE + url, { waitUntil: 'networkidle' })
    await pageObj.waitForTimeout(300)
    // Horizontal overflow check (a common RTL/mobile bug).
    const overflow = await pageObj.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
    if (overflow > 1) problems.push(`${url} ${vp}: horizontal overflow ${overflow}px`)
    const name = `${who}_${url.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'home'}_${vp}.png`
    await pageObj.screenshot({ path: path.join(OUT, name), fullPage: true })
    await context.close()
  }
}

await browser.close()
console.log(problems.length ? `PROBLEMS:\n${problems.join('\n')}` : 'no console errors, no horizontal overflow')
