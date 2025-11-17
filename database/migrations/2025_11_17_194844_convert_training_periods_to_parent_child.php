<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_periods', function (Blueprint $table) {
            $table->dropColumn(['_lft', '_rgt']);
        });

        Schema::table('training_periods', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->change();
        });

        Schema::table('training_periods', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('training_periods')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_periods', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::table('training_periods', function (Blueprint $table) {
            $table->unsignedInteger('_lft')->default(0)->after('extra');
            $table->unsignedInteger('_rgt')->default(0)->after('_lft');
        });
    }
};
