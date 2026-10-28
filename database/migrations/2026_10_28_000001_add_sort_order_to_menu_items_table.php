<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_special');
            $table->index(['menu_category_id', 'sort_order']);
        });

        // Existing products keep the order they were shown in (alphabetical within each category).
        $position = [];

        DB::table('menu_items')->orderBy('menu_category_id')->orderBy('name')->orderBy('id')
            ->get(['id', 'menu_category_id'])
            ->each(function (object $item) use (&$position) {
                $position[$item->menu_category_id] = ($position[$item->menu_category_id] ?? 0) + 1;

                DB::table('menu_items')->where('id', $item->id)->update(['sort_order' => $position[$item->menu_category_id]]);
            });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['menu_category_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
