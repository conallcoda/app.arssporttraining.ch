<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')->constrained('tags')->nullOnDelete();
        });

        Schema::table('exercises_external', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')->constrained('tags')->nullOnDelete();
        });

        $this->migrateTaggables('exercises', 'App\\Models\\Exercise\\Exercise');
        $this->migrateTaggables('exercises_external', 'App\\Models\\Exercise\\ExerciseExternal');
    }

    private function migrateTaggables(string $table, string $taggableType): void
    {
        $rows = DB::table('taggables')
            ->join('tags', 'tags.id', '=', 'taggables.tag_id')
            ->where('taggables.taggable_type', $taggableType)
            ->where('tags.scope', 'exercise_category')
            ->select('taggables.taggable_id', 'taggables.tag_id', 'taggables.id as taggable_row_id')
            ->get();

        foreach ($rows as $row) {
            DB::table($table)
                ->where('id', $row->taggable_id)
                ->update(['category_id' => $row->tag_id]);
        }

        DB::table('taggables')
            ->whereIn('id', $rows->pluck('taggable_row_id'))
            ->delete();
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('exercises_external', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
