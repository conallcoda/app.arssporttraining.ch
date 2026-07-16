<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schema_facets', function (Blueprint $table) {
            $table->string('facet_group_key')->nullable()->after('key');
        });

        Schema::table('schema_facet_applicability_rules', function (Blueprint $table) {
            $table->string('segment_slug')->nullable()->after('taxonomy_term_id');
            $table->index('segment_slug');
        });
    }

    public function down(): void
    {
        Schema::table('schema_facet_applicability_rules', function (Blueprint $table) {
            $table->dropIndex(['segment_slug']);
            $table->dropColumn('segment_slug');
        });

        Schema::table('schema_facets', function (Blueprint $table) {
            $table->dropColumn('facet_group_key');
        });
    }
};
