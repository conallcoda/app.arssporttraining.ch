<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('forename');
            $table->string('surname');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->schemalessAttributes('extra');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });



        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->schemalessAttributes('extra');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('user_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('user_group_id')
                ->constrained('user_groups')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'user_group_id']);
        });
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
