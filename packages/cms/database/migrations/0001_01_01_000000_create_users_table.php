<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userTable = config('cms.tables.user', 'users');
        $userGroupTable = config('cms.tables.user_group', 'user_groups');
        $userGroupMembershipTable = config('cms.tables.user_group_membership', 'user_group_memberships');
        $passwordResetTokenTable = config('cms.tables.password_reset_token', 'password_reset_tokens');
        $sessionTable = config('cms.tables.session', 'sessions');
        $ownerForeignKey = config('cms.columns.owner_foreign_key', 'owner_id');
        $userForeignKey = config('cms.columns.user_foreign_key', 'user_id');
        $userGroupForeignKey = config('cms.columns.user_group_foreign_key', 'user_group_id');

        if (! Schema::hasTable($userTable)) {
            Schema::create($userTable, function (Blueprint $table) use ($ownerForeignKey, $userTable) {
                $table->id();
                $table->foreignId($ownerForeignKey)->nullable()->constrained($userTable)->nullOnDelete();
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

        if (! Schema::hasTable($passwordResetTokenTable)) {
            Schema::create($passwordResetTokenTable, function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable($sessionTable)) {
            Schema::create($sessionTable, function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (! Schema::hasTable($userGroupTable)) {
            Schema::create($userGroupTable, function (Blueprint $table) use ($ownerForeignKey, $userTable) {
                $table->id();
                $table->foreignId($ownerForeignKey)->nullable()->constrained($userTable)->nullOnDelete();
                $table->string('name');
                $table->schemalessAttributes('config');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable($userGroupMembershipTable)) {
            Schema::create($userGroupMembershipTable, function (Blueprint $table) use ($userForeignKey, $userGroupForeignKey, $userTable, $userGroupTable) {
                $table->id();
                $table->foreignId($userForeignKey)
                    ->constrained($userTable)
                    ->cascadeOnDelete();
                $table->foreignId($userGroupForeignKey)
                    ->constrained($userGroupTable)
                    ->cascadeOnDelete();
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->unique([$userForeignKey, $userGroupForeignKey]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('cms.tables.user_group_membership', 'user_group_memberships'));
        Schema::dropIfExists(config('cms.tables.user_group', 'user_groups'));
        Schema::dropIfExists(config('cms.tables.session', 'sessions'));
        Schema::dropIfExists(config('cms.tables.password_reset_token', 'password_reset_tokens'));
        Schema::dropIfExists(config('cms.tables.user', 'users'));
    }
};
