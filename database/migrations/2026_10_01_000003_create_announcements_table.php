<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notifications broadcast by the admin team (history + delivery count). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('message');
            $table->string('link', 255)->nullable();
            // Value of App\Enums\AnnouncementAudience.
            $table->string('audience', 20);
            $table->json('user_ids')->nullable();
            $table->boolean('send_email')->default(false);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
