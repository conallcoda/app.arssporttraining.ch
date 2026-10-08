<?php

use App\Form\Fields\Athlete\Email as AthleteEmail;
use App\Form\Fields\Coach\Email as CoachEmail;
use App\Models\Users\User;
use Coda\FormKit\Field;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

it('releases an email when an account is soft deleted', function (string $type) {
    $user = User::factory()->{$type}()->create(['email' => 'conall@example.com']);
    $user->delete();

    $deleted = User::withTrashed()->findOrFail($user->id);

    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->email)->toStartWith('conall@example.com-deleted-')
        ->and(Str::isUuid(Str::afterLast($deleted->email, '-deleted-')))->toBeTrue();

    foreach ([AthleteEmail::class, CoachEmail::class] as $fieldClass) {
        $rules = Field::buildValidationRules([$fieldClass::make('email')]);

        expect(Validator::make(['email' => 'conall@example.com'], $rules)->passes())->toBeTrue();
    }

    $replacement = User::factory()->athlete()->create(['email' => 'conall@example.com']);

    expect($replacement->id)->not->toBe($deleted->id)
        ->and(User::withTrashed()->findOrFail($deleted->id)->email)->toBe($deleted->email);
})->with(['athlete', 'coach', 'admin']);

it('continues to reject emails used by active accounts and allows editing their own email', function () {
    $user = User::factory()->coach()->create(['email' => 'conall@example.com']);

    foreach ([AthleteEmail::class, CoachEmail::class] as $fieldClass) {
        $field = $fieldClass::make('email');

        expect(Validator::make(['email' => $user->email], Field::buildValidationRules([$field]))->fails())->toBeTrue()
            ->and(Validator::make(['email' => $user->email], Field::buildValidationRules([$field], data: ['id' => $user->id]))->passes())->toBeTrue();
    }

    expect(fn () => User::factory()->athlete()->create(['email' => $user->email]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('leaves missing emails unchanged when an account is soft deleted', function (?string $email) {
    $user = User::factory()->athlete()->create(['email' => $email]);
    $user->delete();

    expect(User::withTrashed()->findOrFail($user->id)->email)->toBe($email);
})->with([null, '']);

it('keeps suffixed emails within the database column length', function () {
    $user = User::factory()->athlete()->create(['email' => str_repeat('a', 240).'@example.com']);
    $user->delete();

    $email = User::withTrashed()->findOrFail($user->id)->email;

    expect(mb_strlen($email))->toBe(255)
        ->and(Str::isUuid(Str::afterLast($email, '-deleted-')))->toBeTrue();
});

it('backfills existing deleted accounts without changing active accounts or other account data', function () {
    $deleted = User::factory()->athlete()->create([
        'email' => 'julien.landolt@sportgymnasium.ch',
        'deleted_at' => now()->subMonth(),
    ]);
    $active = User::factory()->coach()->create(['email' => 'active@example.com']);
    $blank = User::factory()->athlete()->create(['email' => null, 'deleted_at' => now()]);
    $long = User::factory()->athlete()->create([
        'email' => str_repeat('a', 240).'@example.com',
        'deleted_at' => now(),
    ]);
    $originalAttributes = $deleted->fresh()->getAttributes();
    $migration = require database_path('migrations/2026_10_08_120834_release_soft_deleted_user_emails.php');

    $migration->up();
    $deleted->refresh();

    expect($deleted->email)->toStartWith('julien.landolt@sportgymnasium.ch-deleted-')
        ->and(Str::isUuid(Str::afterLast($deleted->email, '-deleted-')))->toBeTrue()
        ->and(array_diff_key($deleted->getAttributes(), ['email' => true]))->toBe(array_diff_key($originalAttributes, ['email' => true]))
        ->and($active->fresh()->email)->toBe('active@example.com')
        ->and($blank->fresh()->email)->toBeNull()
        ->and(mb_strlen($long->fresh()->email))->toBe(255);

    $anonymisedEmail = $deleted->email;
    $migration->up();

    expect($deleted->fresh()->email)->toBe($anonymisedEmail);

    User::factory()->athlete()->create(['email' => 'julien.landolt@sportgymnasium.ch']);
});
