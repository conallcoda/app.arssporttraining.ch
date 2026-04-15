<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy warm-up program normalization now runs in DatabaseImportSeeder
        // so migrate:fresh --seed applies the import cleanup in one place.
    }

    public function down(): void
    {
        //
    }
};
