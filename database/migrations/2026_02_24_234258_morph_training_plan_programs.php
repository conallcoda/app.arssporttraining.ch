<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plan_programs', function (Blueprint $table) {
            $table->string('plannable_type')->after('id')->nullable();
            $table->unsignedBigInteger('plannable_id')->after('plannable_type')->nullable();
        });

        DB::table('training_plan_programs')->update([
            'plannable_type' => 'App\\Models\\TrainingPlan',
            'plannable_id' => DB::raw('training_plan_id'),
        ]);

        Schema::table('training_plan_programs', function (Blueprint $table) {
            $table->dropForeign(['training_plan_id']);
            $table->dropColumn('training_plan_id');
        });

        Schema::table('training_plan_programs', function (Blueprint $table) {
            $table->string('plannable_type')->nullable(false)->change();
            $table->unsignedBigInteger('plannable_id')->nullable(false)->change();
            $table->index(['plannable_type', 'plannable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('training_plan_programs', function (Blueprint $table) {
            $table->foreignId('training_plan_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::table('training_plan_programs')
            ->where('plannable_type', 'App\\Models\\TrainingPlan')
            ->update(['training_plan_id' => DB::raw('plannable_id')]);

        Schema::table('training_plan_programs', function (Blueprint $table) {
            $table->dropIndex(['plannable_type', 'plannable_id']);
            $table->dropColumn(['plannable_type', 'plannable_id']);
        });

        Schema::table('training_plan_programs', function (Blueprint $table) {
            $table->foreignId('training_plan_id')->nullable(false)->change();
        });
    }
};
