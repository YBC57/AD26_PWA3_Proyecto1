<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('door_events', function (Blueprint $table) {
            $table->id();
            $table->enum('door_state', ['open', 'closed']);
            $table->enum('lock_state', ['locked', 'unlocked']);
            $table->boolean('vibration_detected')->default(false);
            $table->unsignedInteger('vibration_count')->default(0);
            $table->string('event_type', 50)->index();
            $table->timestamp('detected_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('door_events');
    }
};
