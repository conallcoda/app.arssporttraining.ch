<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('parent_id');
        });

        $groups = DB::table('tags')
            ->whereNull('deleted_at')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        foreach ($groups as $siblings) {
            foreach ($siblings->values() as $index => $tag) {
                DB::table('tags')->where('id', $tag->id)->update(['sort_order' => $index]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
