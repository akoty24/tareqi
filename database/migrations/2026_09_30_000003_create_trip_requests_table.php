<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('origin', 120);
            $table->string('origin_normalized', 120);
            $table->string('destination', 120);
            $table->string('destination_normalized', 120);
            $table->date('requested_date');
            $table->time('preferred_time_from')->nullable();
            $table->time('preferred_time_to')->nullable();
            $table->unsignedTinyInteger('passengers_count')->default(1);
            $table->text('notes')->nullable();
            // Value of App\Enums\TripRequestStatus.
            $table->string('status', 20)->default('active');
            $table->timestamps();

            // Matching a newly published trip: active requests on its date.
            $table->index(['status', 'requested_date']);
            $table->index(['origin_normalized', 'destination_normalized', 'requested_date'], 'trip_requests_route_date_index');
        });

        // Which trips were suggested to which requests. The unique key makes the
        // "notify once per request/trip pair" rule a database guarantee.
        Schema::create('trip_request_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->unique(['trip_request_id', 'trip_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_request_matches');
        Schema::dropIfExists('trip_requests');
    }
};
