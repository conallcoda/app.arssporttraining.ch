<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_segment_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schema_definition_id')->constrained('schema_definitions')->cascadeOnDelete();
            $table->string('slug');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['schema_definition_id', 'slug']);
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('schema_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schema_definition_id')->constrained('schema_definitions')->cascadeOnDelete();
            $table->foreignId('segment_group_id')->nullable()->constrained('schema_segment_groups')->nullOnDelete();
            $table->string('slug');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->text('predicate')->nullable();
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('definition_source')->default('user');
            $table->boolean('is_editable')->default(true);
            $table->boolean('is_deletable')->default(true);
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['schema_definition_id', 'slug']);
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('schema_segment_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schema_segment_id')->constrained('schema_segments')->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->timestamps();
            $table->unique(['schema_segment_id', 'entity_type', 'entity_id']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_segment_members');
        Schema::dropIfExists('schema_segments');
        Schema::dropIfExists('schema_segment_groups');
    }
};
