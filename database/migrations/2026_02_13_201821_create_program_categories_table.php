<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('training_plan_programs', function (Blueprint $table) {
            $table->foreignId('program_category_id')->nullable()->after('training_plan_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_plan_programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_category_id');
        });

        Schema::dropIfExists('program_categories');
    }
};
