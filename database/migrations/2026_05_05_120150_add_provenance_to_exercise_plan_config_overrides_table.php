<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_plan_config_overrides', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('value');
            $table->foreignId('updated_by')->nullable()->after('created_by');

            $table->foreign('created_by', 'epco_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'epco_updated_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exercise_plan_config_overrides', function (Blueprint $table) {
            $table->dropForeign('epco_created_by_fk');
            $table->dropForeign('epco_updated_by_fk');
            $table->dropColumn(['created_by', 'updated_by']);
        });
    }
};
