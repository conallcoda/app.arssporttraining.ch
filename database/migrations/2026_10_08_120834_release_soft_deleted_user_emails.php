<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    if (blank($user->email) || Str::isUuid(Str::afterLast($user->email, '-deleted-'))) {
                        continue;
                    }

                    DB::table('users')
                        ->where('id', $user->id)
                        ->whereNotNull('deleted_at')
                        ->where('email', $user->email)
                        ->update([
                            'email' => mb_substr($user->email, 0, 210).'-deleted-'.Str::uuid(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        throw new RuntimeException('Deleted account email anonymisation cannot be rolled back safely after addresses have been reused.');
    }
};
