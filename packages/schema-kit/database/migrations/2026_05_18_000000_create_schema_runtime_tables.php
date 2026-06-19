<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label')->nullable();
            $table->string('plural_label')->nullable();
            $table->string('model_class')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('schema_facets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schema_definition_id')->constrained('schema_definitions')->cascadeOnDelete();
            $table->string('key');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->string('data_class')->nullable();
            $table->string('data_path')->nullable();
            $table->boolean('infer_fields')->default(true);
            $table->string('storage_mode')->nullable();
            $table->json('storage_config')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_dynamic')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->timestamps();
            $table->unique(['schema_definition_id', 'key']);
        });

        Schema::create('schema_facet_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facet_id')->constrained('schema_facets')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('content_hash');
            $table->json('definition_json');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['facet_id', 'content_hash']);
        });

        Schema::table('schema_facets', function (Blueprint $table) {
            $table->foreign('current_revision_id')->references('id')->on('schema_facet_revisions')->nullOnDelete();
        });

        Schema::create('schema_facet_applicability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facet_revision_id')->constrained('schema_facet_revisions')->cascadeOnDelete();
            $table->string('schema_key')->nullable();
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('taxonomy_type')->nullable();
            $table->unsignedBigInteger('taxonomy_term_id')->nullable();
            $table->integer('priority')->default(0);
            $table->string('mode')->default('include');
            $table->timestamps();
        });

        Schema::create('schema_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facet_id')->constrained('schema_facets')->cascadeOnDelete();
            $table->string('key');
            $table->string('label')->nullable();
            $table->string('definition_type')->default('field');
            $table->string('query_strategy')->default('none');
            $table->boolean('is_repeatable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->timestamps();
            $table->unique(['facet_id', 'key']);
        });

        Schema::create('schema_field_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('schema_fields')->cascadeOnDelete();
            $table->foreignId('facet_revision_id')->constrained('schema_facet_revisions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('content_hash');
            $table->string('field_type')->nullable();
            $table->json('type_config')->nullable();
            $table->string('storage_mode')->nullable();
            $table->json('storage_config')->nullable();
            $table->string('attribute_name')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('readable')->default(true);
            $table->boolean('writable')->default(true);
            $table->boolean('form_visible')->default(true);
            $table->text('help')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['field_id', 'content_hash']);
        });

        Schema::table('schema_fields', function (Blueprint $table) {
            $table->foreign('current_revision_id')->references('id')->on('schema_field_revisions')->nullOnDelete();
        });

        Schema::create('schema_field_values', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('field_revision_id')->constrained('schema_field_revisions')->cascadeOnDelete();
            $table->foreignId('facet_revision_id')->constrained('schema_facet_revisions')->cascadeOnDelete();
            $table->string('schema_key');
            $table->string('facet_key');
            $table->string('field_key');
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('taxonomy_type')->nullable();
            $table->unsignedBigInteger('taxonomy_term_id')->nullable();
            $table->string('value_kind')->nullable();
            $table->string('value_string')->nullable();
            $table->text('value_text')->nullable();
            $table->double('value_number')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->json('value_json')->nullable();
            $table->string('canonical_key')->nullable();
            $table->integer('ordinal_value')->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->string('source')->nullable();
            $table->json('provenance_json')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['schema_key', 'field_key']);
            $table->index(['facet_key']);
            $table->index(['scope_type', 'scope_id']);
            $table->index(['taxonomy_type', 'taxonomy_term_id']);
            $table->index(['canonical_key']);
            $table->index(['ordinal_value']);
        });

        Schema::create('schema_field_facts', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('schema_key');
            $table->string('facet_key');
            $table->string('field_key');
            $table->foreignId('field_revision_id')->constrained('schema_field_revisions')->cascadeOnDelete();
            $table->foreignId('facet_revision_id')->constrained('schema_facet_revisions')->cascadeOnDelete();
            $table->unsignedBigInteger('conference_edition_id')->nullable();
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('taxonomy_type')->nullable();
            $table->unsignedBigInteger('taxonomy_term_id')->nullable();
            $table->string('value_kind')->nullable();
            $table->string('canonical_key')->nullable();
            $table->integer('ordinal_value')->nullable();
            $table->string('value_string')->nullable();
            $table->double('value_number')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->text('value_text')->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['schema_key', 'field_key'], 'schema_fact_field_lookup');
            $table->index(['facet_key'], 'schema_fact_facet_lookup');
            $table->index(['canonical_key'], 'schema_fact_canonical_lookup');
            $table->index(['conference_edition_id', 'schema_key'], 'schema_fact_scope_lookup');
            $table->index(['taxonomy_type', 'taxonomy_term_id'], 'schema_fact_taxonomy_lookup');
            $table->index(['schema_key', 'field_key', 'ordinal_value'], 'schema_fact_ordinal_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_field_facts');
        Schema::dropIfExists('schema_field_values');
        Schema::table('schema_fields', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
        });
        Schema::dropIfExists('schema_field_revisions');
        Schema::dropIfExists('schema_fields');
        Schema::dropIfExists('schema_facet_applicability_rules');
        Schema::table('schema_facets', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
        });
        Schema::dropIfExists('schema_facet_revisions');
        Schema::dropIfExists('schema_facets');
        Schema::dropIfExists('schema_definitions');
    }
};
