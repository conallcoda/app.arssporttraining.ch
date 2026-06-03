<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taggables')) {
            return;
        }

        Schema::table('taggables', function (Blueprint $table) {
            if (! Schema::hasColumn('taggables', 'score')) {
                $table->decimal('score', 5, 2)->nullable()->after('sort');
            }

            if (! Schema::hasColumn('taggables', 'extra')) {
                $table->json('extra')->nullable()->after('score');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('taggables')) {
            return;
        }

        Schema::table('taggables', function (Blueprint $table) {
            if (Schema::hasColumn('taggables', 'extra')) {
                $table->dropColumn('extra');
            }

            if (Schema::hasColumn('taggables', 'score')) {
                $table->dropColumn('score');
            }
        });
    }
};
