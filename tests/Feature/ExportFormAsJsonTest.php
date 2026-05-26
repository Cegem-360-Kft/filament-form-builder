<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Actions\ExportFormAsJson;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;

it('returns FormBlueprint::fromModel output', function (): void {
    $form = RegistrationForm::factory()->make(['name' => 'X', 'slug' => 'x']);

    $payload = (new ExportFormAsJson)->execute($form);

    expect($payload)
        ->toHaveKey('schema_version', 1)
        ->toHaveKey('name', 'X')
        ->toHaveKey('slug', 'x');
});
