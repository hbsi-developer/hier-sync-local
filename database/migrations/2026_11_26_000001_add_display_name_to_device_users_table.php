<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The full name kept in this app when the terminal stores a shorter one (older models keep 8 characters).
        Schema::table('device_users', function (Blueprint $table) {
            $table->string('display_name', 60)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('device_users', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
