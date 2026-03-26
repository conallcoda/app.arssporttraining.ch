<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type');
                $table->string('forename');
                $table->string('surname')->nullable();
                $table->string('email')->nullable()->unique();
                $table->string('phone')->nullable();
                $table->string('password')->nullable();
                $table->tinyInteger('gender')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('color')->nullable();
                $table->schemalessAttributes('config');
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (! Schema::hasTable('user_groups')) {
            Schema::create('user_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->schemalessAttributes('config');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('user_group_memberships')) {
            Schema::create('user_group_memberships', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->foreignId('user_group_id')
                    ->constrained('user_groups')
                    ->cascadeOnDelete();
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'user_group_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_group_memberships');
        Schema::dropIfExists('user_groups');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
