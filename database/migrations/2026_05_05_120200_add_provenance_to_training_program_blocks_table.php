<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_program_blocks', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('active');
            $table->foreignId('updated_by')->nullable()->after('created_by');

            $table->foreign('created_by', 'tpb_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'tpb_updated_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_program_blocks', function (Blueprint $table) {
            $table->dropForeign('tpb_created_by_fk');
            $table->dropForeign('tpb_updated_by_fk');
            $table->dropColumn(['created_by', 'updated_by']);
        });
    }
};
