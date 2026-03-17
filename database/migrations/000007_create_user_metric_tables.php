<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_metric_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('metric');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('recorded_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'metric', 'recorded_at']);
        });

        Schema::create('user_metric_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('user_metric_submissions')->cascadeOnDelete();
            $table->string('field');
            $table->string('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_metric_values');
        Schema::dropIfExists('user_metric_submissions');
    }
};
