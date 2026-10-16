<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('slot_interval_minutes');
            $table->unsignedSmallInteger('turnaround_minutes')->default(0);
            $table->unsignedSmallInteger('assignment_priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('table_allowed_party_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_table_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('party_size');

            $table->unique(['restaurant_table_id', 'party_size']);
            $table->index('party_size');
        });

        Schema::create('table_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_table_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('first_booking_time');
            $table->time('last_booking_time');
            $table->timestamps();

            $table->index(['restaurant_table_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_schedules');
        Schema::dropIfExists('table_allowed_party_sizes');
        Schema::dropIfExists('restaurant_tables');
    }
};
