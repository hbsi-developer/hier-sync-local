<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_users', function (Blueprint $table) {
            $table->string('card_number', 20)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('device_users', function (Blueprint $table) {
            $table->dropColumn('card_number');
        });
    }
};
