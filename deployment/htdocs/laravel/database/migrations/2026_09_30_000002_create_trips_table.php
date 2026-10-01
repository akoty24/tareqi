<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            // Self reference: a return trip points at its outbound trip. Unique so
            // an outbound trip has at most one return trip.
            $table->foreignId('parent_trip_id')->nullable()->unique()
                ->constrained('trips')->nullOnDelete();

            // Free-text places as typed by the user (displayed as-is) plus a
            // normalized form (Arabic letter variants unified, lower-cased) used
            // for searching and matching. Coordinates can be added later as
            // origin_latitude / origin_longitude / ... without changing this design.
            $table->string('origin', 120);
            $table->string('origin_normalized', 120);
            $table->string('destination', 120);
            $table->string('destination_normalized', 120);

            $table->date('departure_date');
            $table->time('departure_time');
            $table->unsignedTinyInteger('total_seats');
            $table->unsignedTinyInteger('available_seats');

            // Value of App\Enums\CostType.
            $table->string('cost_type', 20);
            $table->decimal('price_per_seat', 8, 2)->nullable();
            $table->decimal('estimated_cost_per_passenger', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('auto_confirm_bookings')->default(false);

            // Value of App\Enums\TripStatus.
            $table->string('status', 20)->default('draft');
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            // Search: route + date is the main passenger query.
            $table->index(['origin_normalized', 'destination_normalized', 'departure_date'], 'trips_route_date_index');
            $table->index('destination_normalized');
            // Listings / reminders: status + upcoming date and time.
            $table->index(['status', 'departure_date', 'departure_time'], 'trips_status_departure_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
