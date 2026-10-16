<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('scope', 20);
            $table->foreignId('restaurant_table_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_all_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
    }
};
