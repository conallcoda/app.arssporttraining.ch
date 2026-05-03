<?php

use App\Models\Exercise\ExerciseProgram;
use App\Models\Users\User;

it('renders the admin exercise program page', function () {
    $admin = User::factory()->admin()->create();
    $program = ExerciseProgram::factory()->create([
        'name' => 'Test Program',
    ]);

    $this->actingAs($admin)
        ->get('/admin/programs/'.$program->id)
        ->assertOk()
        ->assertSee('Test Program');
});
