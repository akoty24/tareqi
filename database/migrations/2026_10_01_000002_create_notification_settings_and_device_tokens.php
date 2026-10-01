<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-user delivery preferences (in-app notifications are always kept).
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_email')->default(true)->after('ratings_count');
            $table->boolean('notify_push')->default(true)->after('notify_email');
        });

        // Firebase Cloud Messaging registration tokens of the mobile app.
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('platform', 20)->default('android');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_email', 'notify_push']);
        });
    }
};
