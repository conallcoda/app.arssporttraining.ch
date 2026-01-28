<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plan_user', function (Blueprint $table) {
            $table->unsignedInteger('sort')->default(0)->after('user_id');
        });

        Schema::table('training_plan_user_group', function (Blueprint $table) {
            $table->unsignedInteger('sort')->default(0)->after('user_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('training_plan_user', function (Blueprint $table) {
            $table->dropColumn('sort');
        });

        Schema::table('training_plan_user_group', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }
};
