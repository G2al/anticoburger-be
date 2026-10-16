<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_reference')->unique();
            $table->foreignId('restaurant_table_id')->constrained()->restrictOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 30);
            $table->string('phone_normalized', 20);
            $table->string('email', 190)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('party_size');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('occupied_until');
            $table->string('status', 20);
            $table->dateTime('cancelled_at')->nullable();
            $table->char('cancellation_token_hash', 64)->nullable();
            $table->string('created_via', 20);
            $table->timestamps();

            $table->index(['restaurant_table_id', 'starts_at']);
            $table->index(['phone_normalized', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
