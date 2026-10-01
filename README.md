# مشوار · Mishwar — Community Carpooling MVP

Mishwar connects people from the same village who are travelling in the same direction at about the same time. Someone driving their own car publishes a trip with free seats. Neighbours search, book a seat, or post a trip request and get notified when a matching trip appears.

> This is **not** a taxi app. There is no wallet, no payment gateway, no commission, no dynamic pricing and no GPS tracking. Any registered user can be both a **passenger** and a **trip owner** (driver).

---

## Contents
1. [Features](#features)
2. [Technology stack](#technology-stack)
3. [Requirements](#requirements)
4. [Installation (backend)](#installation-backend)
5. [Frontend setup](#frontend-setup)
6. [Test credentials](#test-credentials)
7. [Running tests](#running-tests)
8. [Architecture](#architecture)
9. [Database](#database)
10. [Matching engine](#matching-engine)
11. [Business rules](#business-rules)
12. [Authentication](#authentication)
13. [API documentation](#api-documentation)
14. [Project structure](#project-structure)
15. [Deployment considerations](#deployment-considerations)
16. [Assumptions](#assumptions)
17. [Known limitations](#known-limitations)
18. [Future improvements](#future-improvements)

---

## Features

- **Accounts:** register, log in with email **or** Egyptian mobile number, log out, password reset by email, email verification, profile and profile photo.
- **Vehicles:** each user can register several cars (soft deleted so trip history keeps them).
- **Trips:**
  - Three pricing models: `FREE`, `COST_SHARING` (estimated cost per passenger) and `FIXED_PRICE` (price per seat).
  - Draft or publish, edit, cancel, start, complete and delete (delete only when the trip has no bookings).
  - An optional **return trip**, which is a separate trip that is booked separately.
- **Search:** server-side, filtered in SQL and ranked by a deterministic **matching score**. Arabic spelling variants match (الإسكندرية = الاسكندريه).
- **Trip requests:**
  - A passenger who can't find a trip creates a request.
  - When a matching trip is **published**, the passenger is notified once. No booking is made for them.
  - Booking a matched trip marks the request as fulfilled. Requests expire automatically after their date.
- **Bookings:**
  - Auto-confirm or owner approval. The owner can confirm, reject or cancel; the passenger can cancel.
  - A **price snapshot** is stored on each booking.
  - **Overbooking is prevented** with row-level locks.
  - Trips switch to `FULL` automatically, and back to `PUBLISHED` when seats free up.
- **Ratings:** after completion, passenger → owner and owner → passenger, 1–5 stars with an optional review, at most once per booking.
- **Reports:** report a user, trip or booking. Admins review them.
- **Notifications:** stored in the database, emailed where useful, queued. There are reminders before departure, including a separate return-trip reminder.
- **Admin:** dashboard statistics, users (search, filter, block, unblock), trips (filter, cancel inappropriate trips), bookings, trip requests and reports (filter and review).
- **Frontend:**
  - A separate Vue 3 SPA, in Arabic and RTL, mobile first with a bottom tab bar.
  - English is included and can be switched from the profile page.

## Technology stack

| Layer | Stack (versions already in the project) |
|---|---|
| Backend | PHP 8.2, Laravel 11.55, Laravel Sanctum 4.3, PHPUnit 10.5 |
| Database | MySQL 5.7+/8 or MariaDB 10.4+ (tested on XAMPP MariaDB 10.4). SQLite for tests. |
| Frontend | Vue 3.5 (Composition API), Vue Router 5, Pinia 4, Axios, vue-i18n 11, Tailwind CSS 4, Vite 8 |

The existing Laravel and PHP dependencies were **not upgraded**. `composer install` uses the committed `composer.lock`. The frontend is a new, separate app in `frontend/` with its own `package.json`. The root `package.json` (the Laravel Vite scaffold) is untouched.

## Requirements

- PHP 8.2+ with the `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo` and `bcmath` extensions
- Composer 2
- MySQL or MariaDB
- Node.js 20+ and npm

On Windows with XAMPP, use `C:\xampp\php\php.exe` wherever this guide says `php`.

## Installation (backend)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create the database. The name must match `DB_DATABASE` in `.env` (default `mishwar`):

```bash
mysql -uroot -e "CREATE DATABASE mishwar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Then build the schema and development data, link public storage, and start the server:

```bash
php artisan migrate:fresh --seed     # full schema + realistic demo data
php artisan storage:link             # public URLs for profile photos
php artisan serve                    # http://127.0.0.1:8000
```

Run these in separate terminals as well:

```bash
php artisan queue:work               # notifications + trip-request matching (QUEUE_CONNECTION=database)
php artisan schedule:work            # trip reminders (every 15 min) + request expiry (daily)
```

### Environment configuration

| Key | Default | Notes |
|---|---|---|
| `DB_CONNECTION` / `DB_DATABASE` | `mysql` / `mishwar` | |
| `DB_COLLATION` | `utf8mb4_unicode_ci` | Required for MariaDB. Laravel 11's default `utf8mb4_0900_ai_ci` only exists on MySQL 8. |
| `APP_LOCALE` | `ar` | The API also honours `Accept-Language: en`. |
| `APP_TIMEZONE` | `Africa/Cairo` | Departure dates and times are local Cairo time. |
| `FRONTEND_URL` | `http://localhost:5173` | Used in password-reset, verification and notification links. |
| `QUEUE_CONNECTION` | `database` | Set it to `sync` if you don't want to run a worker locally. |
| `MAIL_MAILER` | `log` | Emails are written to `storage/logs/laravel.log`. |

## Frontend setup

```bash
cd frontend
cp .env.example .env        # optional; the defaults work with `php artisan serve`
npm install
npm run dev                 # http://localhost:5173
```

The Vite dev server proxies `/api` and `/storage` to `VITE_BACKEND_URL` (default `http://127.0.0.1:8000`), so no CORS setup is needed.

For production:

```bash
npm run build               # static files in frontend/dist
```

Serve `frontend/dist` from the same domain as Laravel. Route `/api` to Laravel and send every other path to `index.html`. If the SPA is on another domain, set `VITE_API_URL=https://api.example.com/api` and configure CORS.

## Test credentials

All seeded accounts use the password **`password`**.

| Role | Email | Mobile | Notes |
|---|---|---|---|
| Admin | `admin@mishwar.test` | `01000000000` | Opens `/admin` |
| Driver (demo) | `driver@mishwar.test` | `01000000001` | Has a car and a microbus. Owns the daily Mit Khaqan → Shebin trip with a return trip, a draft, a cancelled trip and completed trips. |
| Passenger (demo) | `passenger@mishwar.test` | `01000000002` | Has an upcoming booking, completed bookings (one still unrated), trip requests and notifications. |
| Blocked | `blocked@mishwar.test` | `01000000003` | Login is refused with `account_blocked`. |

The seeders also create 12 random community members, 9 vehicles, about 40 trips (including 9 return trips), about 55 bookings, ratings, 12 trip requests and 5 reports.

## Running tests

```bash
php artisan test                                   # SQLite in memory (fast, default)
```

To run the full suite on MySQL/MariaDB, including the **real row-lock concurrency test**:

```bash
mysql -uroot -e "CREATE DATABASE mishwar_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
DB_CONNECTION=mysql DB_DATABASE=mishwar_testing php artisan test
```

Current results: **107 passed + 1 skipped on SQLite** (the lock test needs MySQL), and **108 passed on MariaDB**.

### Frontend tests

```bash
cd frontend
npm test            # Vitest + Vue Test Utils + happy-dom — 34 tests
```

- **`src/__tests__/pages.test.js`** mounts the real app (router, guards, layouts, Pinia, i18n) with a mocked API and renders **every route**.
  - A page fails the test if it throws, emits a Vue warning, logs an error or uses a missing translation key.
  - It also covers interactions: approving a booking, cancelling a trip through the confirmation dialog, booking seats, search filters written to the URL, and logout.
- **`src/__tests__/guards.test.js`** covers:
  - the guest → login redirect with `redirect` back after login
  - server validation errors shown next to the field
  - the guest-only and admin-only guards

| Area | File |
|---|---|
| Auth (register, login by email or phone, blocked user, logout, password reset, rate limiting) | `tests/Feature/AuthTest.php` |
| Trip creation, pricing rules, return trips, update authorization, cancel, delete, lifecycle | `tests/Feature/TripManagementTest.php` |
| Search (filters, exclusions, Arabic normalization, ordering, pagination, N+1 guard) | `tests/Feature/TripSearchTest.php` |
| Matching score rules | `tests/Unit/TripMatchingServiceTest.php` |
| Trip requests, matching notifications, no duplicates, fulfilment, expiry | `tests/Feature/TripRequestMatchingTest.php` |
| Bookings (auto/pending, price snapshot, FULL and restore, overbooking with stale models, authorization, blocked users) | `tests/Feature/BookingTest.php` |
| Real `SELECT … FOR UPDATE` contention between two DB connections | `tests/Feature/BookingConcurrencyTest.php` |
| Ratings (completion, duplicates, participants, averages) | `tests/Feature/RatingTest.php` |
| Reports and admin (dashboard, block/unblock, cancel trip, review reports) | `tests/Feature/ReportAndAdminTest.php` |
| Notifications and reminders | `tests/Feature/NotificationTest.php` |
| Vehicles and profile | `tests/Feature/VehicleAndProfileTest.php` |

## Architecture

```
HTTP request
  → routes/api.php
      middleware: SetLocale, throttle, auth:sanctum, active, can:access-admin
  → Form Request (validation)
  → Controller (thin: authorize via Policy, call a service, return an API Resource)
  → Service (business rules, transactions, locking)
  → Eloquent models (casts to enums, relationships, query scopes)
  → Events → Listeners → Notifications (queued; database + mail channels)
```

- **Services** hold the business logic:
  - `TripService` — create (with return trip), update, publish, cancel, start, complete, delete
  - `BookingService` — create, confirm, reject, cancel; every seat change happens under a lock
  - `Matching\TripMatchingService` — search and scoring
  - `Matching\TripRequestMatchingService` — matches requests to newly published trips
  - `RatingService`, `ReportService`, `AuthService`, `UserModerationService`, `AdminStatsService`
- **Policies** (`app/Policies`) decide *who* may act. Services decide *whether the state allows it*.
- **Errors:**
  - A business-rule violation throws `BusinessRuleException` (with a stable `error_code`). It becomes `422`, `403` or `409`.
  - Every error uses the same JSON envelope. Stack traces are hidden unless `APP_DEBUG=true`.
- **Events:**
  - `TripPublished` → `MatchTripRequests` (queued)
  - `BookingCreated`, `BookingStatusChanged` and `TripCancelled` → notification listeners
- **Jobs:** `SendTripReminders` (every 15 minutes) and `ExpireTripRequests` (daily), scheduled in `routes/console.php`.
- **Notifications:**
  - Everything extends `AppNotification`, which picks the channels.
  - Adding push or WhatsApp later means adding a channel and a `toX()` method in one place.
  - Texts come from `lang/{ar,en}/notifications.php`.
- **No repositories.** The old task-management `UserRepository` was not used by the new code. Eloquent plus services is enough here.

### Response format

```json
{ "success": true, "message": "تم إنشاء الرحلة بنجاح.", "data": { } }
{ "success": true, "message": "…", "data": [ ], "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42, "from": 1, "to": 15 } }
{ "success": false, "message": "البيانات المدخلة غير صحيحة.", "errors": { "origin": ["…"] }, "error_code": "validation_failed" }
{ "success": false, "message": "عدد المقاعد المتاحة غير كافٍ (المتاح: 1).", "error_code": "insufficient_seats" }
```

Messages are in Arabic by default. Send `Accept-Language: en` for English.

## Database

Everything is created by **migrations**; seeders only insert data. The whole database is reproducible with `php artisan migrate:fresh --seed`.

| Table | Purpose / key design points |
|---|---|
| `users` | `phone` unique, `email` unique, `role` (`user`/`admin`), `status` (`active`/`blocked`, indexed), `blocked_at`, `profile_photo_path`, and denormalized `rating_average` / `ratings_count`. |
| `vehicles` | Belongs to a user. **Soft deletes** so past trips keep their vehicle. |
| `trips` | `owner_id`, `vehicle_id`, `parent_trip_id` (self-referencing, **unique**, `nullOnDelete`), origin and destination plus normalized copies, date and time, `total_seats` / `available_seats`, `cost_type` and prices, `auto_confirm_bookings`, `status`, cancellation info, `reminder_sent_at`. |
| `trip_requests` | Passenger requests with normalized places, date, optional time window, passenger count, status. |
| `trip_request_matches` | **Unique `(trip_request_id, trip_id)`**, which guarantees one notification per request/trip pair. |
| `bookings` | `trip_id`, `passenger_id`, `seats`, `status`, **snapshot** `price_per_seat` / `total_price`, `confirmed_at`, `cancelled_at`, reason. |
| `ratings` | `booking_id`, `trip_id`, `rater_id`, `rated_user_id`, `stars`, `review`. **Unique `(booking_id, rater_id)`**. |
| `reports` | Reporter, reported user, optional trip or booking, `reason`, `status`, admin notes, `reviewed_by`, `reviewed_at`. |
| `notifications` | Standard Laravel database notifications. |
| System | `personal_access_tokens`, `password_reset_tokens`, `sessions`, `cache`, `jobs`, `job_batches`, `failed_jobs`. |

### Indexes

- **`trips`:**
  - `(origin_normalized, destination_normalized, departure_date)` — the main route search
  - `destination_normalized` — destination-only search
  - `(status, departure_date, departure_time)` — upcoming bookable trips and reminders
  - `owner_id` — through its foreign key
- **`bookings`:** `(trip_id, status)` and `(passenger_id, status)`.
- **`trip_requests`:** `(status, requested_date)` for matching a published trip, and a route + date index.

### Design decisions

- **Enum columns:** status and type columns are `string(20)` holding PHP backed-enum values (`app/Enums`). They are portable (MySQL, MariaDB, SQLite), and adding a value needs no `ALTER TABLE ... ENUM`. Eloquent casts them to enums.
- **Place names:** the normalized columns (أ/إ/آ→ا, ة→ه, ى→ي, no diacritics, lower case, single spaces) are indexed instead of the raw text, so differently spelled searches still use the index. The raw text is what users see.
- **Stored seat count:** `available_seats` is stored rather than computed. It is only changed inside a transaction that holds a `lockForUpdate()` on the trip row. That is what makes the overbooking guarantee cheap. Pending and confirmed bookings both hold seats.
- **Denormalized ratings:** `rating_average` and `ratings_count` are shown on every trip card. `RatingService::recalculate()` recomputes them from `ratings` under a user-row lock whenever a rating is created. Completed-trip counts are **not** stored; they are calculated with `withCount`.
- **Future coordinates:** `origin_latitude`, `origin_longitude`, `destination_latitude` and `destination_longitude` can be added in a new migration without changing the text-based design.

## Matching engine

`App\Services\Matching\TripMatchingService` is deterministic (no AI):

| Criterion | Points | Rule |
|---|---|---|
| Origin | 30 | Normalized names equal → 30. One contains the other → 15. Otherwise 0. |
| Destination | 30 | Same rule as origin. |
| Date | 20 | Same day → 20. ±1 day → 10. Otherwise 0. |
| Time proximity | 15 | Inside the requested window → 15. Otherwise it drops linearly to 0 at 3 hours outside the window. |
| Availability | 5 | `available_seats` ≥ requested passengers. |

A criterion the user did not specify awards its full points.

Search filters candidates in SQL: published, upcoming, enough seats, place `LIKE` on the normalized columns, date, time range, cost type, and never the viewer's own trips. That returns at most 300 rows. The service then scores them, sorts by score and paginates.

`TripRequestMatchingService` treats a score of **≥ 70** as a match. For example, both places partially matching on the right date and within the time window scores exactly 70.

To replace the matcher, bind a subclass in `AppServiceProvider`.

## Business rules

1. Every user can both create trips and book trips. There are no separate driver accounts.
2. Only the trip owner can edit, publish, start, complete, cancel or delete the trip, or manage its bookings. Admins can cancel inappropriate trips.
3. Only `PUBLISHED` trips accept bookings. `DRAFT`, `FULL`, `STARTED`, `COMPLETED` and `CANCELLED` trips reject them, and so does any trip whose departure time has passed.
4. A trip becomes `FULL` when `available_seats` reaches 0, and goes back to `PUBLISHED` when seats are released by a cancelled or rejected booking, or when the owner adds seats.
5. A booking can never exceed `available_seats`. The check and the decrement happen under a row lock. The owner cannot book their own trip, and a passenger can hold only one active booking per trip.
6. With auto-confirm on, bookings are `CONFIRMED` immediately. Otherwise they are `PENDING` (still holding seats) until the owner confirms or rejects them.
7. **Price snapshot:** a booking stores `price_per_seat` and `total_price` at the moment of booking. Price per seat is `0` for FREE, `estimated_cost_per_passenger` for COST_SHARING and `price_per_seat` for FIXED_PRICE. Later price changes never affect existing bookings.
8. Pricing depends on the cost type. FREE clears both price fields. COST_SHARING requires `estimated_cost_per_passenger`. FIXED_PRICE requires `price_per_seat`.
9. **Return trips** are independent trips linked by `parent_trip_id`. They have the reversed route and the same vehicle, and must depart after the outbound trip. Booking one never books the other, cancelling one never cancels the other, and a return trip cannot have its own return trip.
10. Once a trip has active bookings, its route, date and time can't change; cancel it and create a new one instead. Seats can't be reduced below the number already booked.
11. A trip with any booking can't be deleted; it can only be cancelled. Cancelling a trip cancels its active bookings and notifies the passengers (and the owner too when an admin cancels it).
12. Passengers and owners can cancel a booking until the trip is started.
13. Starting a trip is allowed from 2 hours before departure and rejects any pending bookings. Completing a trip turns confirmed bookings into `COMPLETED`.
14. Ratings are only allowed on `COMPLETED` bookings and only by the two parties. Each party rates the other at most once per booking (enforced by a unique index), and nobody can rate themselves.
15. Blocked users are rejected at login. All their tokens are revoked, and the `active` middleware rejects every other request with `403 account_blocked`.
16. Trip requests stay `ACTIVE` until the user cancels them (`CANCELLED`), until they book a trip the request was matched with (`FULFILLED`), or until the day after `requested_date`, when the daily job marks them `EXPIRED`. Matching never creates a booking.
17. Contact details are private. A trip owner's phone is visible only to their confirmed passengers, and a passenger's phone only to the owner of a trip they are confirmed on.

## Authentication

Authentication uses Sanctum personal access tokens (`Authorization: Bearer <token>`). The SPA stores the token in `localStorage`. Because the API uses bearer tokens instead of cookies, CSRF does not apply to it.

**Rate limits:**

| Endpoint | Limit |
|---|---|
| Login | 5 per minute per login + IP, and 20 per minute per IP |
| Register | 10 per hour per IP |
| Password reset | 3 per minute |
| Trip creation | 30 per hour |
| Booking | 10 per minute |
| Reports | 10 per hour |
| Everything else | 120 per minute |

## API documentation

All endpoints are prefixed with `/api`. 🔒 = requires a token. 👑 = admin only.

### Auth
| Method | Path | Body / notes |
|---|---|---|
| POST | `/auth/register` | `name, phone (01[0125]xxxxxxxx), email, password, password_confirmation` → `{user, token}` |
| POST | `/auth/login` | `login` (email or phone), `password` → `{user, token}` |
| POST | `/auth/logout` 🔒 | Revokes the current token |
| GET | `/auth/me` 🔒 | `{user, unread_notifications}` |
| POST | `/auth/forgot-password` | `email` (same answer whether or not the email exists) |
| POST | `/auth/reset-password` | `token, email, password, password_confirmation` |
| POST | `/auth/email/verification-notification` 🔒 | Resends the verification email |
| GET | `/auth/email/verify/{id}/{hash}` | Signed link from the email; redirects to the SPA |

### Profile, users and vehicles 🔒
| Method | Path | Notes |
|---|---|---|
| GET / PUT | `/profile` | `name, phone, email` (changing the email resets verification) |
| PUT | `/profile/password` | `current_password, password, password_confirmation` |
| POST | `/profile/photo` | multipart `photo` (jpg, png or webp, ≤ 2 MB) |
| GET | `/users/{id}` | Public profile and the 10 most recent ratings |
| GET / POST | `/vehicles` | `vehicle_type (sedan, hatchback, suv, minivan, microbus, pickup), model, color, plate_number` |
| PUT / DELETE | `/vehicles/{id}` | Owner only. Delete returns `409` if the vehicle is used by an active trip. |

### Trips 🔒
| Method | Path | Notes |
|---|---|---|
| GET | `/trips` | Upcoming bookable trips (others'). `?mine=1&status=&scope=upcoming\|past` lists your own trips. |
| GET | `/trips/search` | `origin, destination, date, time_from, time_to, passengers, cost_type, flexible_dates, page, per_page` → each result includes `match.score` and `match.breakdown` |
| GET | `/places?q=` | Autocomplete from known place names |
| POST | `/trips` | `vehicle_id, origin, destination, departure_date, departure_time, total_seats, cost_type, price_per_seat?, estimated_cost_per_passenger?, notes?, auto_confirm_bookings?, publish?` and optionally `return_trip{departure_date, departure_time, total_seats?, notes?}` |
| GET | `/trips/{id}` | Includes the return or parent trip and `my_booking`. The owner also gets `bookings`. |
| PUT | `/trips/{id}` | Partial update (see business rule 10) |
| DELETE | `/trips/{id}` | Only when there are no bookings (otherwise `409`) |
| PATCH | `/trips/{id}/publish` · `/cancel` (`reason?`) · `/start` · `/complete` | Lifecycle |
| POST | `/trips/{id}/return-trip` | `departure_date, departure_time` plus optional seats and pricing |

### Bookings and ratings 🔒
| Method | Path | Notes |
|---|---|---|
| POST | `/trips/{id}/bookings` | `seats, notes?` → `CONFIRMED` or `PENDING` |
| GET | `/bookings` | `?role=passenger` (default) or `owner`, plus `&status=` |
| GET | `/bookings/{id}` | Passenger, trip owner or admin |
| PATCH | `/bookings/{id}/confirm` · `/reject` (`reason?`) | Trip owner only |
| PATCH | `/bookings/{id}/cancel` | Passenger or trip owner (`reason?`) |
| POST | `/bookings/{id}/rating` | `stars (1–5), review?`. Rates the other party. |
| GET | `/ratings?type=received\|given` | |

### Trip requests, notifications and reports 🔒
| Method | Path | Notes |
|---|---|---|
| GET / POST | `/trip-requests` | `origin, destination, requested_date, preferred_time_from?, preferred_time_to?, passengers_count, notes?`. POST also returns `matching_trips`. |
| GET / PUT / DELETE | `/trip-requests/{id}` | GET includes current `matching_trips`. DELETE sets the status to `cancelled`. |
| GET | `/notifications?unread=1` | `meta.unread_count` |
| PATCH | `/notifications/{id}/read` · `/notifications/read-all` | |
| GET / POST | `/reports` | POST takes `reason, description?` plus at least one of `reported_user_id`, `trip_id` or `booking_id`. The reported user is derived from the trip or booking when not given. |

### Admin 🔒👑
| Method | Path | Notes |
|---|---|---|
| GET | `/admin/dashboard` | Users, trips, bookings, requests and reports counts |
| GET | `/admin/users?search=&status=&role=` | |
| PATCH | `/admin/users/{id}/block` · `/unblock` | Can't target admins or yourself |
| GET | `/admin/trips?search=&status=&date=` | |
| PATCH | `/admin/trips/{id}/cancel` | `reason?`. Notifies the owner and passengers. |
| GET | `/admin/bookings?status=&search=&trip_id=` | |
| GET | `/admin/trip-requests?status=&search=` | |
| GET | `/admin/reports?status=&reason=` · `/admin/reports/{id}` | |
| PATCH | `/admin/reports/{id}` | `status (pending, under_review, resolved, dismissed), admin_notes?` |

## Project structure

```
app/
  Enums/                 UserRole, UserStatus, VehicleType, CostType, TripStatus, TripRequestStatus,
                         BookingStatus, ReportStatus, ReportReason
  Events/  Listeners/    TripPublished, TripCancelled, BookingCreated, BookingStatusChanged (+ listeners)
  Exceptions/            BusinessRuleException
  Helpers/  Traits/      ApiResponse envelope (kept from the original project)
  Http/Controllers/Api/  Auth, Profile, User, Vehicle, Trip, Booking, TripRequest, Rating, Report,
                         Notification, Admin/*
  Http/Middleware/       EnsureUserIsActive, SetLocale
  Http/Requests/  Http/Resources/
  Jobs/                  SendTripReminders, ExpireTripRequests
  Models/                User, Vehicle, Trip, TripRequest, Booking, Rating, Report
  Notifications/         AppNotification base + 9 notification types
  Policies/              Trip, Booking, Rating, Vehicle, TripRequest, Report, User
  Services/              Trip, Booking, Rating, Report, Auth, UserModeration, AdminStats, Matching/*
  Support/PlaceName.php  Arabic place-name normalizer
database/migrations | factories | seeders
lang/ar  lang/en         API messages, business errors, notifications, validation (ar)
routes/api.php  routes/console.php (scheduler)
tests/Feature  tests/Unit
frontend/                Vue 3 SPA
  src/api                axios client + endpoint wrappers
  src/stores             auth, toast (Pinia)
  src/router             routes and guards (guest, auth, admin)
  src/i18n               ar (default, RTL) + en
  src/layouts            AppLayout (header + mobile tab bar), AuthLayout, AdminLayout
  src/components         TripCard, StatusBadge, ModalDialog, PromptDialog, RatingDialog, ReportDialog, ...
  src/views              Home, Search, TripDetails, TripForm, MyTrips, MyBookings, TripRequests,
                         TripRequestForm/Details, Notifications, Profile, Vehicles, Ratings,
                         UserProfile, auth/*, admin/*
```

## Deployment considerations

- Set `APP_ENV=production`, `APP_DEBUG=false`, a real `MAIL_*` configuration and `FRONTEND_URL`.
- Run `php artisan config:cache route:cache`.
- Keep a queue worker running under Supervisor or systemd (`php artisan queue:work --tries=3`).
- Add a cron entry: `* * * * * php /path/artisan schedule:run`.
- Use Redis for cache, queue and rate limiting at scale. The database drivers are fine for the MVP.
- Serve `frontend/dist` behind the same domain (nginx `try_files $uri /index.html`) and proxy `/api` and `/storage` to PHP-FPM.
- Use HTTPS everywhere, because tokens are bearer credentials.
- Run `php artisan storage:link` for profile photos.
- Back up the database. The booking concurrency guarantee relies on InnoDB row locks, so use MySQL or MariaDB in production (not SQLite).

## Assumptions

- **Location:** users are Egyptian. Phones are validated as `01[0125]` followed by 8 digits. The timezone is `Africa/Cairo`.
- **Seats:** pending bookings reserve seats, so an owner can never approve more passengers than fit in the car.
- **Pricing:** for COST_SHARING the snapshot uses the estimated cost per passenger as the per-seat price. Money is informational only; payment happens offline.
- **Login and email:** users log in with email or phone. Email is required (for password reset). Email verification is sent but **not enforced**, because many village users may not verify their email.
- **Deletion:**
  - Deleting a trip request is a soft cancellation, kept for history and statistics.
  - Trips with bookings can't be hard deleted.
  - Vehicles are soft deleted.
- **Draft return trips:** a return trip created with a draft outbound trip is also a draft.
- **Starting a trip:** allowed from 2 hours before departure, and completing is allowed after departure even without starting, because drivers forget to press "start".
- **Reminders:** sent once per trip, about 3 hours before departure, to the owner and confirmed passengers.
- **Blocking:** blocking a user doesn't cancel their future trips automatically; the admin can cancel them from the trips page.
- **Existing project:** it was a task-management API whose modules had already been removed. The base users migration was extended (fresh database). The existing `ApiResponse` helper, response trait and `UserSeeder` file were reused.

## Known limitations

- Places are free text. There are no maps or coordinates, and a village spelled very differently won't match.
- Search scores at most 300 SQL candidates per query. That's fine for a village-sized community, but larger scale needs scoring in SQL or a search engine.
- Request matching loads active requests for the trip's date and scores them in PHP (again fine at MVP scale).
- There is no real-time push. The unread badge is refreshed by polling `/auth/me` every 60 s while the tab is visible, and when the tab regains focus.
- There is no chat between passenger and driver; contact is by phone after confirmation.
- No payments (by design).
- Frontend: English exists, but some content (place names, reviews) is user generated.
- The frontend tests run in happy-dom with a mocked API. They don't cover visual layout or real browsers; add Playwright for that.
- The concurrency test needs MySQL/MariaDB and is skipped on SQLite.

## Future improvements

- Map picker plus coordinates, and distance-based matching (the `TripMatchingService` is replaceable).
- Push notifications (FCM) and WhatsApp via new notification channels.
- In-app chat and a verified phone number (OTP).
- Recurring trips (a daily commute).
- Trust signals: ID or driving-licence verification, women-only trips.
- Real-time updates with Laravel Reverb or websockets.
- Browser E2E tests (Playwright) on real mobile viewports.
- API documentation generated with OpenAPI.
