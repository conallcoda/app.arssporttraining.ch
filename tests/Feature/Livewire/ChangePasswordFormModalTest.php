<?php

use Coda\Cms\Form\Forms\ChangePasswordForm;
use Coda\Cms\Livewire\FormModal;
use Livewire\Livewire;

it('accepts matching change password form modal passwords', function () {
    Livewire::test(FormModal::class, [
        'name' => 'change-password',
        'title' => 'Change Password',
        'formDataClass' => ChangePasswordForm::class,
    ])
        ->set('data.password', 'NewSecurePass123!')
        ->set('data.password_confirmation', 'NewSecurePass123!')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('change-password.submitted');
});

it('rejects mismatched change password form modal passwords', function () {
    Livewire::test(FormModal::class, [
        'name' => 'change-password',
        'title' => 'Change Password',
        'formDataClass' => ChangePasswordForm::class,
    ])
        ->set('data.password', 'NewSecurePass123!')
        ->set('data.password_confirmation', 'DifferentPass123!')
        ->call('submit')
        ->assertHasErrors(['data.password_confirmation' => 'same']);
});
