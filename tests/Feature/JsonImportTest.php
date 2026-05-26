<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Madbox99\FilamentFormBuilder\Actions\ImportFormFromJson;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;
use Madbox99\FilamentFormBuilder\Support\FormBlueprintSchema;

uses(RefreshDatabase::class);

it('creates a form from a valid payload', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['name'] = 'Imported';
    $payload['slug'] = 'imported';

    $form = (new ImportFormFromJson)->execute($payload);

    expect($form)->toBeInstanceOf(RegistrationForm::class);
    expect($form->name)->toBe('Imported');
    expect($form->slug)->toBe('imported');
    expect($form->is_active)->toBeTrue();
    expect($form->submissions_count)->toBe(0);
    expect(RegistrationForm::count())->toBe(1);
});
