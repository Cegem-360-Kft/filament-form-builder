<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Madbox99\FilamentFormBuilder\Actions\ImportFormFromJson;
use Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables\Actions\ImportFormAction;
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

it('throws ValidationException on invalid payload', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'Not Valid';

    expect(fn () => (new ImportFormFromJson)->execute($payload))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(RegistrationForm::count())->toBe(0);
});

it('sanitizes custom_css before persisting', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'css-test';
    $payload['custom_css'] = '@import url("evil");';

    $form = (new ImportFormFromJson)->execute($payload);

    expect($form->custom_css)->not->toContain('@import');
});

it('ImportFormAction::make() returns a configured Filament action', function (): void {
    $action = ImportFormAction::make();

    expect($action)->toBeInstanceOf(Action::class);
    expect($action->getName())->toBe('import_json');
});

it('ImportFormAction::handle() creates a form from textarea JSON', function (): void {
    $payload = \Madbox99\FilamentFormBuilder\Support\FormBlueprintSchema::fullExample();
    $payload['slug'] = 'from-textarea';

    $form = ImportFormAction::handle(json: json_encode($payload), file: null);

    expect($form)->not->toBeNull();
    expect($form->slug)->toBe('from-textarea');
});

it('ImportFormAction::handle() rejects malformed JSON', function (): void {
    expect(fn () => ImportFormAction::handle(json: '{not-json', file: null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
