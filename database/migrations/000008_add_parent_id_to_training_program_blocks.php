<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_program_blocks', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('training_program_blocks')->nullOnDelete();
            $table->boolean('active')->default(true)->after('config');
        });
    }

    public function down(): void
    {
        Schema::table('training_program_blocks', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'active']);
        });
    }
};
