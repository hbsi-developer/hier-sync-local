<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('auto_sync')->default(true);
            $table->timestamp('last_sync_attempt_at')->nullable();
            $table->boolean('last_sync_ok')->nullable();
            $table->string('last_sync_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['auto_sync', 'last_sync_attempt_at', 'last_sync_ok', 'last_sync_message']);
        });
    }
};