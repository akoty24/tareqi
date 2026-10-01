<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->text('review')->nullable();
            $table->timestamps();

            // A booking has exactly two parties (passenger and trip owner), so one
            // rating per rater per booking means "rate the same person only once".
            $table->unique(['booking_id', 'rater_id']);
            $table->index(['rated_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
