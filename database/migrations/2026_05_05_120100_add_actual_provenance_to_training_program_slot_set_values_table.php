<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_program_slot_set_values', function (Blueprint $table) {
            $table->foreignId('actual_recorded_by')
                ->nullable()
                ->after('actual_json_value')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('actual_recorded_at')->nullable()->after('actual_recorded_by');
            $table->string('actual_source', 32)->nullable()->after('actual_recorded_at');
            $table->boolean('actual_is_explicit')->default(false)->after('actual_source');
        });
    }

    public function down(): void
    {
        Schema::table('training_program_slot_set_values', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actual_recorded_by');
            $table->dropColumn(['actual_recorded_at', 'actual_source', 'actual_is_explicit']);
        });
    }
};
