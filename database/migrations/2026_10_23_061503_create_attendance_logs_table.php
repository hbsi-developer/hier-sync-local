<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('enroll_number');
            $table->dateTime('punched_at');
            $table->unsignedSmallInteger('verify_mode')->default(0);
            $table->unsignedSmallInteger('in_out_mode')->default(0);
            $table->unsignedInteger('work_code')->default(0);
            $table->timestamps();

            $table->unique(['device_id', 'enroll_number', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
