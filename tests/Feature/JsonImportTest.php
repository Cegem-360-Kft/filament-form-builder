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

it('uniquifies the slug on collision', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'lead-capture';

    (new ImportFormFromJson)->execute($payload);
    $second = (new ImportFormFromJson)->execute($payload);

    expect($second->slug)->toBe('lead-capture-2');
    expect($second->name)->toBe($payload['name']);
});

it('handles multiple collisions in sequence', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'collide';

    (new ImportFormFromJson)->execute($payload);
    (new ImportFormFromJson)->execute($payload);
    $third = (new ImportFormFromJson)->execute($payload);

    expect($third->slug)->toBe('collide-3');
});
