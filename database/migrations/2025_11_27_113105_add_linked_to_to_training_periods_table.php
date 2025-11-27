<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_periods', function (Blueprint $table) {
            $table->uuid('linked_to')->nullable()->after('parent_id');
            $table->foreign('linked_to')->references('uuid')->on('training_periods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_periods', function (Blueprint $table) {
            $table->dropForeign(['linked_to']);
            $table->dropColumn('linked_to');
        });
    }
};
