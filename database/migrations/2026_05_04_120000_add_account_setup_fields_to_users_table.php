<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('account_setup_uuid')->nullable()->after('remember_token');
            $table->string('account_setup_token_hash', 64)->nullable()->after('account_setup_uuid');
            $table->timestamp('account_setup_sent_at')->nullable()->after('account_setup_token_hash');
            $table->timestamp('account_setup_expires_at')->nullable()->after('account_setup_sent_at');
            $table->timestamp('account_setup_completed_at')->nullable()->after('account_setup_expires_at');
        });

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('users')
                        ->where('id', $row->id)
                        ->update(['account_setup_uuid' => (string) Str::uuid()]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('account_setup_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['account_setup_uuid']);
            $table->dropColumn([
                'account_setup_uuid',
                'account_setup_token_hash',
                'account_setup_sent_at',
                'account_setup_expires_at',
                'account_setup_completed_at',
            ]);
        });
    }
};
