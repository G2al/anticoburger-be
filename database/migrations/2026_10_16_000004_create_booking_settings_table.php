<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('min_lead_minutes')->default(30);
            $table->unsignedSmallInteger('max_advance_days')->default(30);
            $table->unsignedSmallInteger('max_reservations_per_phone_per_day')->default(2);
            $table->boolean('prevent_duplicate_bookings')->default(true);
            $table->timestamps();
        });

        DB::table('booking_settings')->insertOrIgnore([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_settings');
    }
};
