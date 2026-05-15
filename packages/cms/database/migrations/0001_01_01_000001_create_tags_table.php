<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userTable = config('cms.tables.user', 'users');
        $ownerForeignKey = config('cms.columns.owner_foreign_key', 'owner_id');

        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table) use ($ownerForeignKey, $userTable) {
                $table->id();
                $table->foreignId($ownerForeignKey)->nullable()->constrained($userTable)->nullOnDelete();
                $table->string('scope')->index();
                $table->string('name');
                $table->string('short_name')->nullable();
                $table->string('slug');
                $table->foreignId('parent_id')->nullable()->constrained('tags')->nullOnDelete();
                $table->string('color')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['slug', 'scope']);
            });
        }

        if (! Schema::hasTable('taggables')) {
            Schema::create('taggables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('taggable_id');
                $table->string('taggable_type');
                $table->unsignedInteger('sort')->default(0);
                $table->decimal('score', 5, 2)->nullable();
                $table->json('extra')->nullable();
                $table->timestamps();

                $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
                $table->index(['taggable_id', 'taggable_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
