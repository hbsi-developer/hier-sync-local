<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);   // push, device_sync, pull_users, test, settings, skipped
            $table->string('level', 10);  // success, error, warning, info
            // Kept as text too, so the entry still reads right after a device is removed.
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_name')->nullable();
            $table->string('device_serial')->nullable();
            $table->string('message', 500);
            $table->unsignedInteger('records')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['type', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
