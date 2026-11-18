<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            // Set only after Hier Payroll confirmed the row. NULL = still to send.
            $table->timestamp('sent_at')->nullable()->index();
            // Set when the server rejected the row (e.g. unknown employee) so it stops blocking the queue.
            $table->string('push_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex(['sent_at']);
            $table->dropColumn(['sent_at', 'push_error']);
        });
    }
};
