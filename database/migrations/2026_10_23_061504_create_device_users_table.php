<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('enroll_number');
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('privilege')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['device_id', 'enroll_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_users');
    }
};
