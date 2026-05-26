<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Models\RegistrationForm;
use Madbox99\FilamentFormBuilder\Support\FormBlueprint;

it('extracts only the data-scope keys plus schema_version', function (): void {
    $form = RegistrationForm::factory()->make([
        'name' => 'Lead capture',
        'slug' => 'lead-capture',
        'description' => 'desc',
        'thank_you_message' => 'thanks',
        'redirect_url' => null,
        'custom_css' => '.x{color:red}',
        'is_active' => true,
    ]);
    $form->id = 999;
    $form->submissions_count = 42;

    $payload = FormBlueprint::fromModel($form);

    expect($payload)
        ->toHaveKey('schema_version', 1)
        ->toHaveKey('name', 'Lead capture')
        ->toHaveKey('slug', 'lead-capture')
        ->toHaveKey('description', 'desc')
        ->toHaveKey('thank_you_message', 'thanks')
        ->toHaveKey('redirect_url', null)
        ->toHaveKey('custom_css', '.x{color:red}')
        ->toHaveKey('is_active', true)
        ->toHaveKey('fields')
        ->not->toHaveKey('id')
        ->not->toHaveKey('submissions_count')
        ->not->toHaveKey('created_at')
        ->not->toHaveKey('updated_at')
        ->not->toHaveKey('deleted_at');
});
