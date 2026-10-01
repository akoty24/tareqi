<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\TripRequestController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public authentication endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
});

/*
|--------------------------------------------------------------------------
| Authenticated endpoints (blocked users are rejected by `active`)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::middleware('active')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/email/verification-notification', [AuthController::class, 'sendVerification'])
            ->middleware('throttle:6,1');

        // Profile
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);
        Route::post('profile/photo', [ProfileController::class, 'updatePhoto']);
        Route::get('users/{user}', [UserController::class, 'show'])->whereNumber('user');

        // Vehicles
        Route::apiResource('vehicles', VehicleController::class)->except('show');

        // Trips (search/places are declared before {trip} so they are not captured by it)
        Route::get('trips/search', [TripController::class, 'search']);
        Route::get('places', [TripController::class, 'places']);
        Route::get('trips', [TripController::class, 'index']);
        Route::post('trips', [TripController::class, 'store'])->middleware('throttle:trips');
        Route::get('trips/{trip}', [TripController::class, 'show']);
        Route::put('trips/{trip}', [TripController::class, 'update']);
        Route::delete('trips/{trip}', [TripController::class, 'destroy']);
        Route::patch('trips/{trip}/publish', [TripController::class, 'publish']);
        Route::patch('trips/{trip}/cancel', [TripController::class, 'cancel']);
        Route::patch('trips/{trip}/start', [TripController::class, 'start']);
        Route::patch('trips/{trip}/complete', [TripController::class, 'complete']);
        Route::post('trips/{trip}/return-trip', [TripController::class, 'storeReturn'])->middleware('throttle:trips');

        // Bookings
        Route::post('trips/{trip}/bookings', [BookingController::class, 'store'])->middleware('throttle:bookings');
        Route::get('bookings', [BookingController::class, 'index']);
        Route::get('bookings/{booking}', [BookingController::class, 'show']);
        Route::patch('bookings/{booking}/confirm', [BookingController::class, 'confirm']);
        Route::patch('bookings/{booking}/reject', [BookingController::class, 'reject']);
        Route::patch('bookings/{booking}/cancel', [BookingController::class, 'cancel']);

        // Ratings
        Route::get('ratings', [RatingController::class, 'index']);
        Route::post('bookings/{booking}/rating', [RatingController::class, 'store']);

        // Trip requests
        Route::apiResource('trip-requests', TripRequestController::class)
            ->parameters(['trip-requests' => 'tripRequest']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);

        // Reports
        Route::get('reports', [ReportController::class, 'index']);
        Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:reports');

        /*
        |------------------------------------------------------------------
        | Admin
        |------------------------------------------------------------------
        */
        Route::prefix('admin')->middleware('can:access-admin')->group(function () {
            Route::get('dashboard', Admin\DashboardController::class);

            Route::get('users', [Admin\UserController::class, 'index']);
            Route::patch('users/{user}/block', [Admin\UserController::class, 'block']);
            Route::patch('users/{user}/unblock', [Admin\UserController::class, 'unblock']);

            Route::get('trips', [Admin\TripController::class, 'index']);
            Route::patch('trips/{trip}/cancel', [Admin\TripController::class, 'cancel']);

            Route::get('bookings', [Admin\BookingController::class, 'index']);
            Route::get('trip-requests', [Admin\TripRequestController::class, 'index']);

            Route::get('reports', [Admin\ReportController::class, 'index']);
            Route::get('reports/{report}', [Admin\ReportController::class, 'show']);
            Route::patch('reports/{report}', [Admin\ReportController::class, 'update']);
        });
    });
});
