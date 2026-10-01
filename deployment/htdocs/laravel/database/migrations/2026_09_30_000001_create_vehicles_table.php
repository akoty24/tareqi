<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Value of App\Enums\VehicleType.
            $table->string('vehicle_type', 20);
            $table->string('model', 80);
            $table->string('color', 40);
            $table->string('plate_number', 20);
            $table->timestamps();
            // Soft deletes keep historical trips pointing at the vehicle used.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
